<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('urban_goodz_driver_earnings', function (Blueprint $table) {
            if (!Schema::hasColumn('urban_goodz_driver_earnings', 'business_client_job_id')) {
                $table->unsignedBigInteger('business_client_job_id')->nullable()->after('delivery_man_id');

                $table->foreign('business_client_job_id', 'ug_earn_bcj_fk')
                      ->references('id')->on('urban_goodz_business_client_jobs')
                      ->nullOnDelete();

                $table->index('business_client_job_id', 'ug_earn_bcj_idx');

                $table->index(
                    ['delivery_man_id', 'business_client_job_id', 'earning_type'],
                    'ug_earn_bcj_idem_idx'
                );
            }
        });
    }

    public function down(): void
    {
        Schema::table('urban_goodz_driver_earnings', function (Blueprint $table) {
            $table->dropForeign('ug_earn_bcj_fk');
            $table->dropIndex('ug_earn_bcj_idem_idx');
            $table->dropIndex('ug_earn_bcj_idx');
            $table->dropColumn('business_client_job_id');
        });
    }
};
