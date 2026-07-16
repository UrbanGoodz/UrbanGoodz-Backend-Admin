<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('urban_goodz_logistics_jobs')->insert([
            'job_number' => 'LG-' . date('Ymd') . '-001',
            'pickup_location' => '1200 McKinney St, Houston, TX 77010',
            'delivery_location' => '4500 San Jacinto St, Houston, TX 77004',
            'pickup_by' => $now->copy()->addDays(2),
            'deliver_by' => $now->copy()->addDays(3),
            'description' => 'Same-day local delivery: 5 boxes of restaurant supplies from central warehouse to downtown location. Handle with care — contains glass bottles.',
            'weight_kg' => 45.00,
            'status' => 'available',
            'offer_amount' => 240.00,
            'admin_notes' => 'Test seed record — verify admin DB Records shows 1',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('urban_goodz_medical_courier_jobs')->insert([
            'job_number' => 'MC-' . date('Ymd') . '-001',
            'pickup_location' => '7000 Fannin St, Houston, TX 77030',
            'pickup_facility_name' => 'Houston Medical Center Lab',
            'pickup_contact_name' => 'Dr. Sarah Chen',
            'pickup_contact_phone' => '(713) 555-0101',
            'pickup_lat' => 29.7067,
            'pickup_lng' => -95.3984,
            'delivery_location' => '6560 Fannin St, Houston, TX 77030',
            'delivery_facility_name' => 'Methodist Hospital Pathology',
            'delivery_contact_name' => 'Lab Tech Mike',
            'delivery_contact_phone' => '(713) 555-0202',
            'delivery_lat' => 29.7068,
            'delivery_lng' => -95.3985,
            'distance_miles' => 2.5,
            'payout_amount' => 35.00,
            'payout_type' => 'flat',
            'specimen_type' => 'Blood samples',
            'specimen_count' => 4,
            'requires_refrigeration' => true,
            'is_biological_hazard' => false,
            'temperature_min_f' => 36.0,
            'temperature_max_f' => 46.0,
            'priority' => 'urgent',
            'status' => 'pending',
            'admin_notes' => 'Test seed record — verify admin DB Records shows 1',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('urban_goodz_creator_applications')->insert([
            'customer_id' => null,
            'creator_name' => 'Test Creator Seed',
            'niche' => 'Fashion & Lifestyle',
            'social_media' => '@testcreator_seed',
            'city' => 'Houston',
            'bio' => 'Test seed record for creator commerce verification. This record confirms backend persistence and admin visibility.',
            'sell_or_promote' => 'promote',
            'status' => 'submitted',
            'admin_notes' => 'Test seed record — verify admin visibility',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('urban_goodz_logistics_jobs')
            ->where('admin_notes', 'like', '%Test seed record%')
            ->delete();
        DB::table('urban_goodz_medical_courier_jobs')
            ->where('admin_notes', 'like', '%Test seed record%')
            ->delete();
        DB::table('urban_goodz_creator_applications')
            ->where('admin_notes', 'like', '%Test seed record%')
            ->delete();
    }
};
