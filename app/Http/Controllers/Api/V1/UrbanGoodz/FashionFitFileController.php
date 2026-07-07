<?php

namespace App\Http\Controllers\Api\V1\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Services\UrbanGoodz\UrbanGoodzFileStorageService;
use Illuminate\Http\Request;

class FashionFitFileController extends Controller
{
    public function uploadPhoto(Request $request, UrbanGoodzFileStorageService $storage)
    {
        $data = $request->validate([
            'photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'category' => ['required', 'string', 'in:front,side,back'],
            'measurement_profile_id' => ['nullable', 'integer', 'exists:urban_goodz_measurement_requests,id'],
            'height_ref' => ['nullable', 'numeric'],
        ]);

        $uploadedBy = $request->user()?->id;
        $ownerId = $data['measurement_profile_id'] ?? null;
        $ownerType = $ownerId ? 'App\Models\MeasurementRequest' : '';

        $file = $storage->storeFashionFitPhoto(
            file: $request->file('photo'),
            category: $data['category'],
            ownerId: $ownerId,
            ownerType: $ownerType,
            uploadedBy: $uploadedBy,
            metadata: [
                'measurement_profile_id' => $ownerId,
                'height_ref' => $data['height_ref'] ?? null,
                'client_ip' => $request->ip(),
            ],
        );

        return response()->json([
            'success' => true,
            'message' => 'Fashion Fit photo uploaded.',
            'data' => [
                'id' => $file->id,
                'user_id' => $uploadedBy,
                'photo_url' => $storage->temporaryUrl($file),
                'orientation' => $data['category'],
                'height_ref' => $data['height_ref'] ?? null,
                'uploaded_at' => $file->created_at?->toIso8601String(),
                'status' => 'uploaded',
                'file_id' => $file->id,
                'category' => $data['category'],
                'stored_path' => $file->stored_path,
                'file_size' => $file->file_size,
                'mime_type' => $file->mime_type,
                'url' => $storage->temporaryUrl($file),
            ],
        ], 201);
    }
}
