<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('urban_goodz_medical_courier_jobs', function (Blueprint $table) {
            if (!Schema::hasColumn('urban_goodz_medical_courier_jobs', 'pickup_facility_name')) {
                $table->string('pickup_facility_name')->nullable()->after('pickup_location');
            }
            if (!Schema::hasColumn('urban_goodz_medical_courier_jobs', 'pickup_contact_name')) {
                $table->string('pickup_contact_name')->nullable()->after('pickup_facility_name');
            }
            if (!Schema::hasColumn('urban_goodz_medical_courier_jobs', 'pickup_contact_phone')) {
                $table->string('pickup_contact_phone')->nullable()->after('pickup_contact_name');
            }
            if (!Schema::hasColumn('urban_goodz_medical_courier_jobs', 'pickup_lat')) {
                $table->decimal('pickup_lat', 10, 7)->nullable()->after('pickup_contact_phone');
            }
            if (!Schema::hasColumn('urban_goodz_medical_courier_jobs', 'pickup_lng')) {
                $table->decimal('pickup_lng', 10, 7)->nullable()->after('pickup_lat');
            }
            if (!Schema::hasColumn('urban_goodz_medical_courier_jobs', 'delivery_facility_name')) {
                $table->string('delivery_facility_name')->nullable()->after('delivery_location');
            }
            if (!Schema::hasColumn('urban_goodz_medical_courier_jobs', 'delivery_contact_name')) {
                $table->string('delivery_contact_name')->nullable()->after('delivery_facility_name');
            }
            if (!Schema::hasColumn('urban_goodz_medical_courier_jobs', 'delivery_contact_phone')) {
                $table->string('delivery_contact_phone')->nullable()->after('delivery_contact_name');
            }
            if (!Schema::hasColumn('urban_goodz_medical_courier_jobs', 'delivery_lat')) {
                $table->decimal('delivery_lat', 10, 7)->nullable()->after('delivery_contact_phone');
            }
            if (!Schema::hasColumn('urban_goodz_medical_courier_jobs', 'delivery_lng')) {
                $table->decimal('delivery_lng', 10, 7)->nullable()->after('delivery_lat');
            }
            if (!Schema::hasColumn('urban_goodz_medical_courier_jobs', 'distance_miles')) {
                $table->float('distance_miles')->nullable()->after('delivery_lng');
            }
            if (!Schema::hasColumn('urban_goodz_medical_courier_jobs', 'payout_amount')) {
                $table->decimal('payout_amount', 10, 2)->nullable()->after('distance_miles');
            }
            if (!Schema::hasColumn('urban_goodz_medical_courier_jobs', 'payout_type')) {
                $table->string('payout_type')->default('flat')->after('payout_amount');
            }
            if (!Schema::hasColumn('urban_goodz_medical_courier_jobs', 'priority')) {
                $table->string('priority')->default('normal')->after('payout_type');
            }
            if (!Schema::hasColumn('urban_goodz_medical_courier_jobs', 'specimen_count')) {
                $table->integer('specimen_count')->default(1)->after('priority');
            }
            if (!Schema::hasColumn('urban_goodz_medical_courier_jobs', 'temperature_min_f')) {
                $table->float('temperature_min_f')->nullable()->after('specimen_count');
            }
            if (!Schema::hasColumn('urban_goodz_medical_courier_jobs', 'temperature_max_f')) {
                $table->float('temperature_max_f')->nullable()->after('temperature_min_f');
            }
            if (!Schema::hasColumn('urban_goodz_medical_courier_jobs', 'pickup_window_start')) {
                $table->timestamp('pickup_window_start')->nullable()->after('temperature_max_f');
            }
            if (!Schema::hasColumn('urban_goodz_medical_courier_jobs', 'pickup_window_end')) {
                $table->timestamp('pickup_window_end')->nullable()->after('pickup_window_start');
            }
            if (!Schema::hasColumn('urban_goodz_medical_courier_jobs', 'delivery_window_start')) {
                $table->timestamp('delivery_window_start')->nullable()->after('pickup_window_end');
            }
            if (!Schema::hasColumn('urban_goodz_medical_courier_jobs', 'delivery_window_end')) {
                $table->timestamp('delivery_window_end')->nullable()->after('delivery_window_start');
            }
            if (!Schema::hasColumn('urban_goodz_medical_courier_jobs', 'assigned_at')) {
                $table->timestamp('assigned_at')->nullable()->after('delivery_window_end');
            }
            if (!Schema::hasColumn('urban_goodz_medical_courier_jobs', 'picked_up_at')) {
                $table->timestamp('picked_up_at')->nullable()->after('assigned_at');
            }
            if (!Schema::hasColumn('urban_goodz_medical_courier_jobs', 'delivered_at')) {
                $table->timestamp('delivered_at')->nullable()->after('picked_up_at');
            }
            if (!Schema::hasColumn('urban_goodz_medical_courier_jobs', 'signature_path')) {
                $table->string('signature_path')->nullable()->after('delivered_at');
            }
            if (!Schema::hasColumn('urban_goodz_medical_courier_jobs', 'metadata')) {
                $table->json('metadata')->nullable()->after('signature_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('urban_goodz_medical_courier_jobs', function (Blueprint $table) {
            $table->dropColumn([
                'pickup_facility_name', 'pickup_contact_name', 'pickup_contact_phone',
                'pickup_lat', 'pickup_lng', 'delivery_facility_name', 'delivery_contact_name',
                'delivery_contact_phone', 'delivery_lat', 'delivery_lng', 'distance_miles',
                'payout_amount', 'payout_type', 'priority', 'specimen_count',
                'temperature_min_f', 'temperature_max_f', 'pickup_window_start',
                'pickup_window_end', 'delivery_window_start', 'delivery_window_end',
                'assigned_at', 'picked_up_at', 'delivered_at', 'signature_path', 'metadata',
            ]);
        });
    }
};
