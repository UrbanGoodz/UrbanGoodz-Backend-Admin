<?php

namespace App\Http\Controllers\Api\V1\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzStrandedVerification;
use App\Services\UrbanGoodzStrandedSafety;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Identity verification and consent for Urban Goodz Stranded.
 *
 * Licence images are written to the PRIVATE disk. There is deliberately no
 * endpoint here that returns one: a URL to a government ID, once issued,
 * cannot be taken back. Admin review reads them server-side.
 */
class UrbanGoodzStrandedSafetyController extends Controller
{
    /** The safety terms, privacy explanation and Samaritan pledge. */
    public function documents(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'documents' => UrbanGoodzStrandedSafety::documents(),
        ]);
    }

    public function status(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;
        $role = $this->role($request);

        $verification = UrbanGoodzStrandedVerification::where('user_id', $userId)
            ->where('role', $role)
            ->first();

        $gate = UrbanGoodzStrandedSafety::gate($userId, $role);

        return response()->json([
            'status' => 'success',
            'role' => $role,
            'allowed' => $gate['allowed'],
            'code' => $gate['code'],
            'message' => $gate['message'],
            'verification' => $verification ? [
                'status' => $verification->status,
                'license_last_four' => $verification->license_last_four,
                'license_state' => $verification->license_state,
                'license_expires_on' => $verification->license_expires_on?->toDateString(),
                'phone_verified' => $verification->phone_verified,
                'rejection_reason' => $verification->rejection_reason,
            ] : null,
            'outstanding_documents' => collect(UrbanGoodzStrandedSafety::requiredDocumentsFor($role))
                ->reject(fn ($doc) => $userId && UrbanGoodzStrandedSafety::hasConsented($userId, $role, $doc))
                ->values(),
        ]);
    }

    public function accept(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'document' => 'required|string|max:60',
            'version' => 'required|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $document = $request->input('document');
        $expected = UrbanGoodzStrandedSafety::VERSIONS[$document] ?? null;

        if ($expected === null) {
            return response()->json(['status' => 'error', 'message' => 'Unknown document.'], 404);
        }

        // The client must acknowledge the version it actually displayed. If it
        // is behind, accepting would record agreement to text the user never
        // saw.
        if ($request->input('version') !== $expected) {
            return response()->json([
                'status' => 'error',
                'code' => 'version_mismatch',
                'message' => 'These terms have been updated. Please review the current version.',
                'current_version' => $expected,
            ], 409);
        }

        UrbanGoodzStrandedSafety::recordConsent(
            (int) $request->user()->id,
            $this->role($request),
            $document,
            $request
        );

        return response()->json(['status' => 'success']);
    }

    /**
     * Submit or replace a driver's licence. Always lands as `pending`: an
     * upload is a claim, not a verification.
     */
    public function submitVerification(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'license_front' => 'required|image|mimes:jpg,jpeg,png|max:8192',
            'license_back' => 'nullable|image|mimes:jpg,jpeg,png|max:8192',
            'selfie' => 'nullable|image|mimes:jpg,jpeg,png|max:8192',
            'license_number' => 'required|string|max:40',
            'license_state' => 'required|string|max:10',
            'license_expires_on' => 'required|date|after:today',
            'full_name' => 'required|string|max:160',
            'date_of_birth' => 'required|date|before:-18 years',
        ], [
            'license_expires_on.after' => 'That licence has expired. Please upload a current one.',
            'date_of_birth.before' => 'You must be 18 or older to use Urban Goodz Stranded.',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $userId = (int) $request->user()->id;
        $role = $this->role($request);

        $verification = UrbanGoodzStrandedVerification::firstOrNew([
            'user_id' => $userId,
            'role' => $role,
        ]);

        // Private disk, never `public`. A published government ID is a
        // permanent leak.
        $dir = "stranded/verifications/{$userId}/{$role}";

        $verification->license_front_path = $this->store($request->file('license_front'), $dir, 'front');
        if ($request->hasFile('license_back')) {
            $verification->license_back_path = $this->store($request->file('license_back'), $dir, 'back');
        }
        if ($request->hasFile('selfie')) {
            $verification->selfie_path = $this->store($request->file('selfie'), $dir, 'selfie');
        }

        $verification->setLicenseNumber($request->input('license_number'));
        $verification->license_state = strtoupper((string) $request->input('license_state'));
        $verification->license_expires_on = $request->input('license_expires_on');
        $verification->full_name = $request->input('full_name');
        $verification->date_of_birth = $request->input('date_of_birth');
        $verification->status = 'pending';
        $verification->rejection_reason = null;
        $verification->reviewed_by = null;
        $verification->reviewed_at = null;
        $verification->save();

        return response()->json([
            'status' => 'success',
            'verification_status' => 'pending',
            'message' => 'Your licence has been submitted for review.',
        ], 201);
    }

    private function store(\Illuminate\Http\UploadedFile $file, string $dir, string $label): string
    {
        $name = $label . '_' . Str::random(24) . '.' . $file->getClientOriginalExtension();

        // 'local' is the private disk in this project's filesystems config.
        return Storage::disk('local')->putFileAs($dir, $file, $name);
    }

    private function role(Request $request): string
    {
        return $request->input('role') === UrbanGoodzStrandedVerification::ROLE_SAMARITAN
            ? UrbanGoodzStrandedVerification::ROLE_SAMARITAN
            : UrbanGoodzStrandedVerification::ROLE_CUSTOMER;
    }
}
