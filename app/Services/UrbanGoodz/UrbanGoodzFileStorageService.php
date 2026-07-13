<?php

namespace App\Services\UrbanGoodz;

use App\Models\UrbanGoodzFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UrbanGoodzFileStorageService
{
    public function storeFashionFitPhoto(
        UploadedFile $file,
        string $category,
        ?int $ownerId = null,
        string $ownerType = '',
        ?int $uploadedBy = null,
        array $metadata = [],
    ): UrbanGoodzFile {
        $validMimes = ['jpg', 'jpeg', 'png', 'webp'];
        $maxSize = 10240;

        abort_if(!in_array(strtolower($file->getClientOriginalExtension()), $validMimes), 422, 'Invalid image type. Allowed: jpg, jpeg, png, webp.');
        abort_if($file->getSize() > $maxSize * 1024, 422, "Image must be under {$maxSize}KB.");

        $userId = $uploadedBy ?? $ownerId ?? 'guest';
        $timestamp = now()->format('Ymd_His');
        $random = Str::random(8);
        $ext = $file->getClientOriginalExtension();
        $filename = "{$timestamp}_{$random}.{$ext}";

        $directory = "urban_goodz/fashion_fit/measurement_profiles/{$userId}/{$category}";

        $storedPath = $file->storeAs($directory, $filename, 'local');

        abort_if($storedPath === false, 500, 'Failed to store file.');

        return UrbanGoodzFile::create([
            'owner_id' => $ownerId,
            'owner_type' => $ownerType,
            'file_category' => "fashion_fit_photo_{$category}",
            'original_name' => $file->getClientOriginalName(),
            'stored_path' => $storedPath,
            'disk' => 'local',
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'metadata' => array_merge($metadata, [
                'category' => $category,
                'original_extension' => $ext,
            ]),
            'visibility' => 'customer_private',
            'uploaded_by' => $uploadedBy,
        ]);
    }

    public function storeGenericFile(
        UploadedFile $file,
        string $fileCategory,
        ?int $ownerId = null,
        ?string $ownerType = null,
        ?int $uploadedBy = null,
        array $metadata = [],
        string $visibility = 'customer_private',
    ): UrbanGoodzFile {
        $categorySlug = Str::slug($fileCategory);
        $userId = $uploadedBy ?? $ownerId ?? 'guest';
        $timestamp = now()->format('Ymd_His');
        $random = Str::random(8);
        $ext = $file->getClientOriginalExtension();
        $filename = "{$timestamp}_{$random}.{$ext}";

        $directory = "urban_goodz/{$categorySlug}/{$userId}";
        $storedPath = $file->storeAs($directory, $filename, 'public');

        abort_if($storedPath === false, 500, 'Failed to store file.');

        return UrbanGoodzFile::create([
            'owner_id' => $ownerId,
            'owner_type' => $ownerType,
            'file_category' => $categorySlug,
            'original_name' => $file->getClientOriginalName(),
            'stored_path' => $storedPath,
            'disk' => 'public',
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'metadata' => array_merge($metadata, [
                'original_extension' => $ext,
            ]),
            'visibility' => $visibility,
            'uploaded_by' => $uploadedBy,
        ]);
    }

    public function temporaryUrl(UrbanGoodzFile $file): string
    {
        return Storage::disk($file->disk)->url($file->stored_path);
    }
}
