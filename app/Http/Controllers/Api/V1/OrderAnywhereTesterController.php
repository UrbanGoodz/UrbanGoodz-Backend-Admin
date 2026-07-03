<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class OrderAnywhereTesterController extends Controller
{
    private const STORE_FILE = 'app/urban_goodz_tester/order_anywhere_requests.json';

    public function customerRequests()
    {
        return response()->json([
            'success' => true,
            'data' => array_values($this->records()),
        ]);
    }

    public function adminRequests()
    {
        return $this->customerRequests();
    }

    public function store(Request $request)
    {
        $payload = $request->all();
        $now = now()->toIso8601String();
        $id = (string)($payload['id'] ?? 'oa_' . now()->format('YmdHis') . '_' . random_int(1000, 9999));

        $record = array_merge($payload, [
            'id' => $id,
            'request_status' => $payload['request_status'] ?? 'submitted',
            'admin_notes' => $payload['admin_notes'] ?? null,
            'vendor_notes' => $payload['vendor_notes'] ?? null,
            'driver_notes' => $payload['driver_notes'] ?? null,
            'assigned_driver_id' => $payload['assigned_driver_id'] ?? null,
            'driver_task_status' => $payload['driver_task_status'] ?? null,
            'created_at' => $payload['created_at'] ?? $now,
            'updated_at' => $now,
        ]);

        $records = $this->records();
        $records[$id] = $record;
        $this->save($records);

        return response()->json([
            'success' => true,
            'message' => 'Order Anywhere request submitted for admin review.',
            'data' => $record,
        ], 201);
    }

    public function show($record)
    {
        $records = $this->records();

        if (!isset($records[$record])) {
            return response()->json(['success' => false, 'message' => 'Request not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $records[$record]]);
    }

    public function updateStatus(Request $request, $record)
    {
        return $this->updateRecord($record, [
            'request_status' => $request->input('status', $request->input('request_status', 'admin_reviewing')),
            'admin_notes' => $request->input('admin_notes'),
        ], 'Order Anywhere status updated.');
    }

    public function addNotes(Request $request, $record)
    {
        return $this->updateRecord($record, [
            'admin_notes' => $request->input('admin_notes'),
            'vendor_notes' => $request->input('vendor_notes'),
        ], 'Order Anywhere notes updated.');
    }

    public function assignDriver(Request $request, $record)
    {
        return $this->updateRecord($record, [
            'assigned_driver_id' => $request->input('driver_id', 'tester-driver'),
            'request_status' => 'driver_assigned',
            'driver_task_status' => 'assigned',
            'admin_notes' => $request->input('admin_notes'),
        ], 'Driver assigned to Order Anywhere request.');
    }

    public function driverAvailable()
    {
        $tasks = array_values(array_filter($this->records(), function ($record) {
            return !empty($record['assigned_driver_id']) || in_array($record['request_status'] ?? '', [
                'vendor_runner_assigned',
                'driver_assigned',
                'in_progress',
            ], true);
        }));

        return response()->json(['success' => true, 'data' => $tasks]);
    }

    public function driverAccept($record)
    {
        return $this->driverStatus(new Request(['driver_task_status' => 'accepted']), $record);
    }

    public function driverStatus(Request $request, $record)
    {
        $driverStatus = $request->input('driver_task_status', $request->input('status', 'in_progress'));
        $requestStatus = match ($driverStatus) {
            'picked_up', 'en_route' => 'in_progress',
            'delivered' => 'completed',
            'issue_reported' => 'needs_info',
            default => 'driver_assigned',
        };

        return $this->updateRecord($record, [
            'driver_task_status' => $driverStatus,
            'request_status' => $requestStatus,
            'driver_notes' => $request->input('driver_notes'),
        ], 'Driver task status updated.');
    }

    public function driverIssue(Request $request, $record)
    {
        return $this->updateRecord($record, [
            'driver_task_status' => 'issue_reported',
            'request_status' => 'needs_info',
            'driver_notes' => $request->input('driver_notes', $request->input('issue')),
        ], 'Driver issue reported.');
    }

    private function updateRecord(string $id, array $changes, string $message)
    {
        $records = $this->records();

        if (!isset($records[$id])) {
            return response()->json(['success' => false, 'message' => 'Request not found'], 404);
        }

        $records[$id] = array_merge($records[$id], array_filter($changes, fn ($value) => $value !== null), [
            'updated_at' => now()->toIso8601String(),
        ]);
        $this->save($records);

        return response()->json(['success' => true, 'message' => $message, 'data' => $records[$id]]);
    }

    private function records(): array
    {
        $path = storage_path(self::STORE_FILE);
        if (!File::exists($path)) {
            return [];
        }

        $decoded = json_decode(File::get($path), true);
        return is_array($decoded) ? $decoded : [];
    }

    private function save(array $records): void
    {
        $path = storage_path(self::STORE_FILE);
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($records, JSON_PRETTY_PRINT));
    }
}
