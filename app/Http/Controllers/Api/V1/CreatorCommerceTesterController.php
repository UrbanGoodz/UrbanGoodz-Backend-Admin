<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class CreatorCommerceTesterController extends Controller
{
    private const APPLICATION_FILE = 'app/urban_goodz_tester/creator_applications.json';
    private const PROMOTION_FILE = 'app/urban_goodz_tester/creator_promotions.json';

    public function customerApplications()
    {
        return response()->json(['success' => true, 'data' => array_values($this->records(self::APPLICATION_FILE))]);
    }

    public function adminApplications()
    {
        return $this->customerApplications();
    }

    public function storeApplication(Request $request)
    {
        $record = $this->makeRecord($request->all(), [
            'status' => 'submitted',
            'admin_notes' => null,
        ]);
        $records = $this->records(self::APPLICATION_FILE);
        $records[$record['id']] = $record;
        $this->save(self::APPLICATION_FILE, $records);

        return response()->json([
            'success' => true,
            'message' => 'Creator application submitted for admin review.',
            'data' => $record,
        ], 201);
    }

    public function storePromotion(Request $request)
    {
        $record = $this->makeRecord($request->all(), [
            'status' => 'submitted',
            'admin_notes' => null,
        ]);
        $records = $this->records(self::PROMOTION_FILE);
        $records[$record['id']] = $record;
        $this->save(self::PROMOTION_FILE, $records);

        return response()->json([
            'success' => true,
            'message' => 'Creator promotion submitted for admin review.',
            'data' => $record,
        ], 201);
    }

    public function promotions()
    {
        return response()->json(['success' => true, 'data' => array_values($this->records(self::PROMOTION_FILE))]);
    }

    public function updateApplicationStatus(Request $request, $record)
    {
        return $this->update(self::APPLICATION_FILE, $record, [
            'status' => $request->input('status', 'under_review'),
            'admin_notes' => $request->input('admin_notes'),
        ]);
    }

    public function updatePromotionStatus(Request $request, $record)
    {
        return $this->update(self::PROMOTION_FILE, $record, [
            'status' => $request->input('status', 'under_review'),
            'admin_notes' => $request->input('admin_notes'),
        ]);
    }

    private function makeRecord(array $payload, array $defaults): array
    {
        $now = now()->toIso8601String();
        return array_merge($defaults, $payload, [
            'id' => (string)($payload['id'] ?? 'creator_' . now()->format('YmdHis') . '_' . random_int(1000, 9999)),
            'created_at' => $payload['created_at'] ?? $now,
            'updated_at' => $now,
        ]);
    }

    private function update(string $file, string $id, array $changes)
    {
        $records = $this->records($file);
        if (!isset($records[$id])) {
            return response()->json(['success' => false, 'message' => 'Record not found'], 404);
        }

        $records[$id] = array_merge($records[$id], array_filter($changes, fn ($value) => $value !== null), [
            'updated_at' => now()->toIso8601String(),
        ]);
        $this->save($file, $records);

        return response()->json(['success' => true, 'data' => $records[$id]]);
    }

    private function records(string $file): array
    {
        $path = storage_path($file);
        if (!File::exists($path)) {
            return [];
        }

        $decoded = json_decode(File::get($path), true);
        return is_array($decoded) ? $decoded : [];
    }

    private function save(string $file, array $records): void
    {
        $path = storage_path($file);
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($records, JSON_PRETTY_PRINT));
    }
}
