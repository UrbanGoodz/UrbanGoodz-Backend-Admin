<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('urban_goodz_business_client_jobs')) {
            Schema::table('urban_goodz_business_client_jobs', function (Blueprint $table) {
                if (!Schema::hasColumn('urban_goodz_business_client_jobs', 'driver_notes')) {
                    $table->text('driver_notes')->nullable()->after('admin_notes');
                }
                if (!Schema::hasColumn('urban_goodz_business_client_jobs', 'exception_reason')) {
                    $table->text('exception_reason')->nullable()->after('driver_notes');
                }
                if (!Schema::hasColumn('urban_goodz_business_client_jobs', 'exception_reported_at')) {
                    $table->timestamp('exception_reported_at')->nullable()->after('exception_reason');
                }
                if (!Schema::hasColumn('urban_goodz_business_client_jobs', 'driver_accepted_at')) {
                    $table->timestamp('driver_accepted_at')->nullable()->after('assigned_at');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('urban_goodz_business_client_jobs')) {
            $columns = ['driver_notes', 'exception_reason', 'exception_reported_at', 'driver_accepted_at'];
            foreach ($columns as $col) {
                if (Schema::hasColumn('urban_goodz_business_client_jobs', $col)) {
                    Schema::table('urban_goodz_business_client_jobs', function (Blueprint $table) use ($col) {
                        $table->dropColumn($col);
                    });
                }
            }
        }
    }
};
