<?php

namespace Database\Seeders;

use App\Models\MeasurementRequest;
use Illuminate\Database\Seeder;

class MeasurementRequestSeeder extends Seeder
{
    public function run(): void
    {
        MeasurementRequest::updateOrCreate(
            ['admin_notes' => 'Urban Goodz live tester seed record'],
            [
                'source' => 'photo_assisted',
                'free_tester_mode' => true,
                'payment_status' => 'waived',
                'payment_required' => false,
                'measurement_status' => 'ready_for_tailor_review',
                'review_status' => 'pending',
                'face_blur_enabled' => true,
                'face_blur_status' => 'unavailable',
                'privacy_review_status' => 'pending',
                'platform_measurement_fee' => 0,
                'vendor_review_fee' => 0,
                'total_measurement_fee' => 0,
                'currency' => 'USD',
            ]
        );
    }
}
