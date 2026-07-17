<?php

namespace App\Services\UrbanGoodz\LoadSource;

use App\Models\ExternalLoad;
use App\Models\LoadImport;
use App\Models\LoadSource;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class LoadManualImportService
{
    public function importSingle(array $data, int $importedBy, string $importedByType): array
    {
        $source = LoadSource::where('source_key', 'manual_import')->first();
        if (!$source) {
            return ['success' => false, 'error' => 'Manual import source not configured'];
        }

        $import = LoadImport::create([
            'source_id' => $source->id,
            'imported_by' => $importedBy,
            'imported_by_type' => $importedByType,
            'import_method' => 'single_form',
            'total_rows' => 1,
            'status' => 'processing',
        ]);

        try {
            $normalizer = new LoadNormalizer();
            $normalized = $normalizer->normalize(array_merge($data, [
                'external_id' => $data['external_id'] ?? 'manual-' . uniqid(),
                'compliance_status' => 'user_imported',
                'status' => 'pending_review',
            ]), $source->id);

            $externalLoad = $normalizer->persistNormalized($normalized);

            $import->update([
                'successful_rows' => 1,
                'status' => 'completed',
            ]);

            return ['success' => true, 'external_load_id' => $externalLoad->id, 'import_id' => $import->id];
        } catch (\Exception $e) {
            $import->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage(), 'import_id' => $import->id];
        }
    }

    public function importCsv(UploadedFile $file, int $importedBy, string $importedByType): array
    {
        $source = LoadSource::where('source_key', 'manual_import')->first();
        if (!$source) {
            return ['success' => false, 'error' => 'Manual import source not configured'];
        }

        $import = LoadImport::create([
            'source_id' => $source->id,
            'imported_by' => $importedBy,
            'imported_by_type' => $importedByType,
            'import_method' => 'csv',
            'original_filename' => $file->getClientOriginalName(),
            'status' => 'processing',
        ]);

        try {
            $rows = $this->parseCsv($file);
            $total = count($rows);
            $successful = 0;
            $failed = 0;
            $duplicates = 0;
            $errors = [];

            $normalizer = new LoadNormalizer();

            foreach ($rows as $index => $row) {
                try {
                    $normalized = $normalizer->normalize(array_merge($row, [
                        'external_id' => $row['external_id'] ?? 'csv-' . $import->id . '-' . ($index + 1),
                        'compliance_status' => 'user_imported',
                        'status' => 'pending_review',
                    ]), $source->id);

                    $externalLoad = $normalizer->persistNormalized($normalized);

                    if ($externalLoad->wasRecentlyCreated) {
                        $successful++;
                    } else {
                        $duplicates++;
                    }
                } catch (\Exception $e) {
                    $failed++;
                    $errors[] = ['row' => $index + 1, 'error' => $e->getMessage()];
                }
            }

            $import->update([
                'total_rows' => $total,
                'successful_rows' => $successful,
                'failed_rows' => $failed,
                'duplicate_rows' => $duplicates,
                'status' => $failed === $total ? 'failed' : ($failed > 0 ? 'partially_completed' : 'completed'),
                'error_message' => !empty($errors) ? json_encode(array_slice($errors, 0, 10)) : null,
                'metadata' => ['successful' => $successful, 'failed' => $failed, 'duplicates' => $duplicates],
            ]);

            return [
                'success' => true,
                'import_id' => $import->id,
                'total' => $total,
                'successful' => $successful,
                'failed' => $failed,
                'duplicates' => $duplicates,
            ];
        } catch (\Exception $e) {
            $import->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage(), 'import_id' => $import->id];
        }
    }

    public function shareToUrbanGoodz(array $data, int $sharedBy, string $sharedByType): array
    {
        $source = LoadSource::where('source_key', 'manual_import')->first();
        if (!$source) {
            return ['success' => false, 'error' => 'Manual import source not configured'];
        }

        $import = LoadImport::create([
            'source_id' => $source->id,
            'imported_by' => $sharedBy,
            'imported_by_type' => $sharedByType,
            'import_method' => 'share_to_urban_goodz',
            'import_reference' => $data['source_url'] ?? null,
            'total_rows' => 1,
            'status' => 'processing',
        ]);

        try {
            $normalizer = new LoadNormalizer();
            $normalized = $normalizer->normalize(array_merge($data, [
                'external_id' => $data['external_id'] ?? 'share-' . uniqid(),
                'compliance_status' => 'external_link',
                'status' => 'pending_review',
            ]), $source->id);

            $externalLoad = $normalizer->persistNormalized($normalized);

            $import->update(['successful_rows' => 1, 'status' => 'completed']);

            return ['success' => true, 'external_load_id' => $externalLoad->id, 'import_id' => $import->id];
        } catch (\Exception $e) {
            $import->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function parseCsv(UploadedFile $file): array
    {
        $handle = fopen($file->getPathname(), 'r');
        if (!$handle) return [];

        $headers = fgetcsv($handle);
        if (!$headers) return [];

        $headers = array_map('strtolower', array_map('trim', $headers));
        $headerMap = array_map(function($h) {
            return str_replace(' ', '_', strtolower(trim($h)));
        }, $headers);

        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) === count($headerMap)) {
                $rows[] = array_combine($headerMap, $row);
            }
        }

        fclose($handle);
        return $rows;
    }
}
