<?php

namespace App\Services\FashionFit;

use App\Contracts\FashionFitMeasurementProvider;
use App\Models\FashionFitAnalysis;
use App\Models\FashionFitAuditEvent;
use App\Models\FashionFitMeasurement;
use App\Models\UserNotification;
use Illuminate\Support\Facades\DB;
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
            'retake_requirements' => ['nullable', 'array'],
            'retake_requirements.*.view' => ['required', Rule::in(['front', 'side', 'back'])],
            'retake_requirements.*.reason' => ['required', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            throw new RuntimeException('Fashion Fit AI structured response validation failed.');
        }

        return $validator->validated();
    }

    private function persist(FashionFitAnalysis $analysis, array $result): void
    {
        DB::transaction(function () use ($analysis, $result) {
            if ($result['status'] === 'needs_retake') {
                $analysis->update([
                    'status' => 'needs_retake',
                    'overall_confidence' => $result['overall_confidence'],
                    'retake_requirements' => $result['retake_requirements'] ?? [],
                    'model_name' => $result['model'],
                    'model_version' => $result['model_version'],
                    'response_hash' => hash('sha256', json_encode($result)),
                    'completed_at' => now(),
                ]);
                $analysis->profile->update(['status' => 'needs_retake']);
                $this->notifyCustomer($analysis->customer_id, 'fashion_fit_retake_required', 'Fashion Fit needs one or more photo retakes.');
                return;
            }

            FashionFitMeasurement::where('profile_id', $analysis->profile_id)->delete();
            foreach ($result['measurements'] as $measurement) {
                FashionFitMeasurement::create([
                    'profile_id' => $analysis->profile_id,
                    'analysis_id' => $analysis->id,
                    'name' => $measurement['name'],
                    'value' => $measurement['value'],
                    'unit' => $measurement['unit'],
                    'confidence' => $measurement['confidence'],
                    'source' => 'ai',
                    'requires_confirmation' => $measurement['requires_confirmation'],
                ]);
            }
            $analysis->update([
                'status' => 'completed',
                'overall_confidence' => $result['overall_confidence'],
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
            ]);
            $this->audit('system', null, 'analysis_completed', FashionFitAnalysis::class, $analysis->id, [
                'measurement_count' => count($result['measurements']),
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
