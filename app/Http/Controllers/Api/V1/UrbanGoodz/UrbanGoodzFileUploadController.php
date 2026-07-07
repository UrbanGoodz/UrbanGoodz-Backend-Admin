<?php

namespace App\Http\Controllers\Api\V1\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\OrderAnywhereRequest;
use App\Services\UrbanGoodz\UrbanGoodzFileStorageService;
use Illuminate\Http\Request;

class UrbanGoodzFileUploadController extends Controller
{
    private array $allowedCategories = [
        'order_anywhere_receipt',
        'pickup_proof',
        'delivery_proof',
    ];

    public function upload(Request $request, UrbanGoodzFileStorageService $storage, string $category)
    {
        abort_if(!in_array($category, $this->allowedCategories, true), 422, "Invalid file category: {$category}.");

        $mimes = $category === 'order_anywhere_receipt' ? 'mimes:jpg,jpeg,png,pdf' : 'mimes:jpg,jpeg,png,webp';
        $maxSize = $category === 'order_anywhere_receipt' ? 5120 : 10240;

        $data = $request->validate([
            'file' => ['required', 'file', $mimes, "max:{$maxSize}"],
            'order_anywhere_request_id' => ['nullable', 'integer', 'exists:order_anywhere_requests,id'],
        ]);

        $uploadedBy = $request->user()?->id;
        $ownerId = $data['order_anywhere_request_id'] ?? null;
        $ownerType = $ownerId ? OrderAnywhereRequest::class : null;

        $file = $storage->storeGenericFile(
            file: $request->file('file'),
            fileCategory: $category,
            ownerId: $ownerId,
            ownerType: $ownerType,
            uploadedBy: $uploadedBy,
            metadata: [
                'order_anywhere_request_id' => $ownerId,
                'client_ip' => $request->ip(),
            ],
        );

        if ($data['order_anywhere_request_id'] && $category === 'order_anywhere_receipt') {
            OrderAnywhereRequest::where('id', $data['order_anywhere_request_id'])->update([
                'receipt_path' => $file->stored_path,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => "{$category} uploaded successfully.",
            'data' => [
                'id' => $file->id,
                'file_category' => $file->file_category,
                'url' => $storage->temporaryUrl($file),
                'original_name' => $file->original_name,
                'file_size' => $file->file_size,
                'mime_type' => $file->mime_type,
                'uploaded_at' => $file->created_at?->toIso8601String(),
            ],
        ], 201);
    }
}
