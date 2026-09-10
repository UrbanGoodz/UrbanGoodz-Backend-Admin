<?php

namespace App\Services\FashionFit;

use App\Contracts\FashionFitMeasurementProvider;
use App\Models\FashionFitAnalysis;
use App\Models\FashionFitAuditEvent;
use App\Models\FashionFitMeasurement;
use App\Models\FashionFitMeasurementVersion;
use App\Models\FashionFitProfile;
use App\Models\UserNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use RuntimeException;
use Throwable;

class FashionFitAnalysisService
{
    public function __construct(private readonly FashionFitMeasurementProvider $provider) {}

    public function process(FashionFitAnalysis $analysis): void
    {
        $analysis->load('profile');
        if (in_array($analysis->status, ['completed', 'needs_retake', 'failed'], true)) {
            return;
        }
        $consent = $analysis->profile->consents()
            ->whereNull('revoked_at')
            ->latest('accepted_at')
            ->first();
        if (! $consent?->ai_processing_allowed) {
            throw new RuntimeException('Fashion Fit AI consent is not active.');
        }
        $photos = $analysis->profile->photos()->with('file')->where('status', 'accepted')->get();
        $missingViews = array_diff(config('fashion_fit_ai.required_views'), $photos->pluck('view')->all());
        if ($missingViews !== []) {
            throw new RuntimeException('Fashion Fit required photo views are missing.');
        }
        $analysis->increment('attempts');
        $analysis->update([
            'status' => 'processing',
            'provider' => $this->provider->name(),
            'model_name' => config('fashion_fit_ai.model'),
            'model_version' => config('fashion_fit_ai.model_version'),
            'processing_started_at' => now(),
            'failure_code' => null,
            'failure_summary' => null,
        ]);

        try {
            $result = $this->validateResult($this->provider->analyze($analysis, $photos));
            $this->persist($analysis, $result);
        } catch (Throwable $exception) {
            $code = str_contains(strtolower($exception->getMessage()), 'not configured')
                ? 'provider_not_configured'
                : 'provider_or_validation_failure';
            $analysis->update([
                'status' => 'failed',
                'failure_code' => $code,
                'failure_summary' => $code,
                'completed_at' => now(),
            ]);
            $analysis->profile->update(['status' => 'analysis_failed']);
            $this->notifyCustomer($analysis->customer_id, 'fashion_fit_analysis_failed', 'Fashion Fit analysis needs attention.');
            throw $exception;
        }
    }

    public function validateResult(array $result): array
    {
        $validator = Validator::make($result, [
            'status' => ['required', Rule::in(['completed', 'needs_retake'])],
            'model' => ['required', 'string', 'max:191'],
            'model_version' => ['required', 'string', 'max:191'],
            'overall_confidence' => ['required', 'numeric', 'between:0,1'],
            'measurements' => ['required_if:status,completed', 'array'],
            'measurements.*.name' => ['required', Rule::in(config('fashion_fit_ai.allowed_measurements'))],
            'measurements.*.value' => ['required', 'numeric', 'gt:0'],
            'measurements.*.unit' => ['required', Rule::in(['in', 'cm'])],
            'measurements.*.confidence' => ['required', 'numeric', 'between:0,1'],
            'measurements.*.requires_confirmation' => ['required', 'boolean'],
            'measurements.*.provenance' => ['nullable', Rule::in(['detected', 'estimated', 'fallback', 'unknown', 'direct'])],
            'measurements.*.status' => ['nullable', Rule::in(['measured', 'needs_better_photo', 'customer_confirmation_required'])],
            'measurements.*.landmark_sources' => ['nullable', 'array'],
            'measurements.*.calculation' => ['nullable', 'array'],
            'unavailable_measurements' => ['nullable', 'array'],
            'unavailable_measurements.*.name' => ['required', Rule::in(config('fashion_fit_ai.allowed_measurements'))],
            'unavailable_measurements.*.reason' => ['required', 'string', 'max:500'],
            'unavailable_measurements.*.code' => ['required', Rule::in(['needs_better_photo', 'customer_confirmation_required'])],
            'quality_warnings' => ['nullable', 'array'],
            'quality_warnings.*' => ['string', 'max:500'],
            'retake_requirements' => ['nullable', 'array'],
            'retake_requirements.*.view' => ['required', Rule::in(['front', 'side', 'back'])],
            'retake_requirements.*.reason' => ['required', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            throw new RuntimeException('Fashion Fit AI structured response validation failed.');
        }

        return $validator->validated();
    }

    /**
     * Content signature of the accepted photos feeding an analysis. Two analyses
     * over identical photo contents hash to the same value, which is the
     * idempotency guard: re-submitting the same photos must not re-analyse.
     *
     * @return array{hash: string, files: array<string, array{file_id: int, hash: string}>}
     */
    public function sourceSignature(FashionFitProfile $profile): array
    {
        $files = [];
        $parts = [(string) $profile->id];
        foreach ($profile->photos()->with('file')->where('status', 'accepted')->orderBy('view')->get() as $photo) {
            $hash = hash('sha256', (string) Storage::disk($photo->file->disk)->get($photo->file->stored_path));
            $files[$photo->view] = ['file_id' => $photo->file_id, 'hash' => $hash];
            $parts[] = $photo->view.':'.$hash;
        }

        return ['hash' => hash('sha256', implode('|', $parts)), 'files' => $files];
    }

    /** @return ?FashionFitAnalysis An existing terminal analysis over the same photos. */
    public function findTerminalForSignature(FashionFitProfile $profile, string $hash): ?FashionFitAnalysis
    {
        return FashionFitAnalysis::where('profile_id', $profile->id)
            ->where('source_hash', $hash)
            ->whereIn('status', ['completed', 'needs_retake'])
            ->latest()
            ->first();
    }

    private function persist(FashionFitAnalysis $analysis, array $result): void
    {
        DB::transaction(function () use ($analysis, $result) {
            if ($result['status'] === 'needs_retake') {
                $analysis->update([
                    'status' => 'needs_retake',
                    'overall_confidence' => $result['overall_confidence'],
                    'retake_requirements' => $result['retake_requirements'] ?? [],
                    'quality_warnings' => $result['quality_warnings'] ?? [],
                    'unavailable_measurements' => $result['unavailable_measurements'] ?? [],
                    'model_name' => $result['model'],
                    'model_version' => $result['model_version'],
                    'response_hash' => hash('sha256', json_encode($result)),
                    'completed_at' => now(),
                ]);
                $analysis->profile->update(['status' => 'needs_retake']);
                $this->notifyCustomer($analysis->customer_id, 'fashion_fit_retake_required', 'Fashion Fit needs one or more photo retakes.');
                return;
            }

            $existing = FashionFitMeasurement::where('profile_id', $analysis->profile_id)->get()->keyBy('name');

            foreach ($result['measurements'] as $measurement) {
                $row = $existing->get($measurement['name']);
                $payload = [
                    'value' => $measurement['value'],
                    'unit' => $measurement['unit'],
                    'confidence' => $measurement['confidence'],
                    'source' => 'ai',
                    'requires_confirmation' => $measurement['requires_confirmation'],
                    'provenance' => $measurement['provenance'] ?? 'unknown',
                    'status' => $measurement['status'] ?? 'measured',
                    'landmark_sources' => $measurement['landmark_sources'] ?? null,
                    'calculation' => $measurement['calculation'] ?? null,
                    'original_value' => $row?->original_value,
                    'corrected_at' => null,
                    'approved_at' => null,
                ];

                if ($row && $row->isLocked()) {
                    continue;
                }

                if ($row) {
                    $row->update(array_merge($payload, ['analysis_id' => $analysis->id]));
                } else {
                    $row = FashionFitMeasurement::create(array_merge($payload, [
                        'profile_id' => $analysis->profile_id,
                        'analysis_id' => $analysis->id,
                        'name' => $measurement['name'],
                    ]));
                }

                FashionFitMeasurementVersion::create([
                    'measurement_id' => $row->id,
                    'analysis_id' => $analysis->id,
                    'value' => $measurement['value'],
                    'unit' => $measurement['unit'],
                    'source' => 'ai',
                    'provenance' => $measurement['provenance'] ?? 'unknown',
                    'confidence' => $measurement['confidence'],
                    'actor_type' => 'system',
                    'corrected' => false,
                    'approved' => false,
                ]);
            }

            foreach ($result['unavailable_measurements'] ?? [] as $unavailable) {
                $row = $existing->get($unavailable['name']);
                if (! $row || $row->isLocked()) {
                    continue;
                }
                $row->update([
                    'status' => $unavailable['code'] === 'customer_confirmation_required'
                        ? 'customer_confirmation_required'
                        : 'needs_better_photo',
                    'requires_confirmation' => true,
                    'analysis_id' => $analysis->id,
                ]);
            }

            $analysis->update([
                'status' => 'completed',
                'overall_confidence' => $result['overall_confidence'],
                'quality_warnings' => $result['quality_warnings'] ?? [],
                'unavailable_measurements' => $result['unavailable_measurements'] ?? [],
                'model_name' => $result['model'],
                'model_version' => $result['model_version'],
                'response_hash' => hash('sha256', json_encode($result)),
                'completed_at' => now(),
            ]);
            $analysis->profile->update([
                'status' => 'customer_review',
                'overall_confidence' => $result['overall_confidence'],
                'analysis_provider' => $this->provider->name(),
                'model_name' => $result['model'],
                'model_version' => $result['model_version'],
                'approved_at' => null,
            ]);
            $this->audit('system', null, 'analysis_completed', FashionFitAnalysis::class, $analysis->id, [
                'measurement_count' => count($result['measurements']),
                'unavailable_count' => count($result['unavailable_measurements'] ?? []),
                'model_version' => $result['model_version'],
            ]);
            $this->notifyCustomer($analysis->customer_id, 'fashion_fit_analysis_completed', 'Your Fashion Fit measurements are ready to review.');
        });
    }

    public function audit(string $actorType, ?int $actorId, string $event, string $type, int $id, array $metadata = []): void
    {
        FashionFitAuditEvent::create([
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'event' => $event,
            'auditable_type' => $type,
            'auditable_id' => $id,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }

    public function notifyCustomer(int $customerId, string $type, string $title): void
    {
        UserNotification::create([
            'user_id' => $customerId,
            'order_type' => 'fashion_fit',
            'data' => json_encode(['type' => $type, 'title' => $title]),
        ]);
    }
}
