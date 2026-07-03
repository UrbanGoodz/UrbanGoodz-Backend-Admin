<?php

namespace App\Console\Commands;

use App\Models\OrderAnywhereRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ImportOrderAnywhereTesterData extends Command
{
    protected $signature = 'urban-goodz:import-order-anywhere-json {--path= : Optional absolute JSON file path}';

    protected $description = 'Import legacy storage/app/urban_goodz_tester/order_anywhere_requests.json records into order_anywhere_requests.';

    public function handle(): int
    {
        $path = $this->option('path') ?: storage_path('app/urban_goodz_tester/order_anywhere_requests.json');

        if (! File::exists($path)) {
            $this->info('No legacy Order Anywhere JSON file found.');
            return self::SUCCESS;
        }

        $records = json_decode(File::get($path), true);

        if (! is_array($records)) {
            $this->error('Legacy Order Anywhere JSON file is not valid JSON.');
            return self::FAILURE;
        }

        $imported = 0;

        foreach ($records as $legacyId => $record) {
            if (! is_array($record)) {
                continue;
            }

            $requestNumber = $record['request_number'] ?? $record['id'] ?? (string) $legacyId;

            OrderAnywhereRequest::updateOrCreate(
                ['request_number' => $requestNumber],
                [
                    'customer_id' => $record['customer_id'] ?? $record['user_id'] ?? null,
                    'customer_name' => $record['customer_name'] ?? $record['name'] ?? null,
                    'customer_phone' => $record['customer_phone'] ?? $record['phone'] ?? null,
                    'customer_email' => $record['customer_email'] ?? $record['email'] ?? null,
                    'store_vendor_name' => $record['store_vendor_name'] ?? $record['vendor_name'] ?? $record['store_name'] ?? null,
                    'store_vendor_address_or_website' => $record['store_vendor_address_or_website'] ?? $record['store_address'] ?? $record['website'] ?? null,
                    'request_details' => $record['request_details'] ?? $record['details'] ?? null,
                    'item_details' => $record['item_details'] ?? $record['items'] ?? null,
                    'quantity' => $record['quantity'] ?? null,
                    'budget_estimate' => $record['budget_estimate'] ?? $record['budget'] ?? null,
                    'status' => $record['status'] ?? $record['request_status'] ?? 'pending_review',
                    'admin_notes' => $record['admin_notes'] ?? null,
                    'vendor_id' => $record['vendor_id'] ?? $record['store_vendor_id'] ?? null,
                    'vendor_status' => $record['vendor_status'] ?? null,
                    'vendor_notes' => $record['vendor_notes'] ?? null,
                    'vendor_quote_amount' => $record['vendor_quote_amount'] ?? $record['quote_amount'] ?? null,
                    'assigned_delivery_man_id' => $record['assigned_delivery_man_id'] ?? $record['assigned_driver_id'] ?? null,
                    'driver_task_status' => $record['driver_task_status'] ?? null,
                    'driver_notes' => $record['driver_notes'] ?? null,
                    'metadata' => $record,
                ]
            );

            $imported++;
        }

        $this->info("Imported {$imported} legacy Order Anywhere request(s).");

        return self::SUCCESS;
    }
}
