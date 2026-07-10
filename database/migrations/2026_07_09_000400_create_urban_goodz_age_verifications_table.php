<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('urban_goodz_age_verifications')) {
            Schema::create('urban_goodz_age_verifications', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('package_id')->nullable();
                $table->unsignedBigInteger('route_id')->nullable();
                $table->unsignedBigInteger('order_id')->nullable();
                $table->unsignedBigInteger('driver_id')->nullable();
                $table->string('verification_status', 50)->default('pending');
                $table->string('refusal_reason', 100)->nullable();
                $table->text('driver_notes')->nullable();
                $table->string('id_type_checked', 100)->nullable();
                $table->string('recipient_name_verified', 255)->nullable();
                $table->date('recipient_dob_verified')->nullable();
                $table->boolean('recipient_age_confirmed')->default(false);
                $table->timestamp('verification_attempted_at')->nullable();
                $table->boolean('signature_captured')->default(false);
                $table->boolean('proof_photo_captured')->default(false);
                $table->boolean('admin_review_required')->default(false);
                $table->string('admin_review_status', 50)->nullable();
                $table->unsignedBigInteger('admin_reviewed_by')->nullable();
                $table->timestamp('admin_reviewed_at')->nullable();
                $table->text('admin_notes')->nullable();
                $table->timestamps();

                if (Schema::hasTable('urban_goodz_route_packages')) {
                    $table->foreign('package_id', 'ug_age_ver_pkg_fk')
                          ->references('id')->on('urban_goodz_route_packages')
                          ->nullOnDelete();
                }
                if (Schema::hasTable('urban_goodz_dedicated_routes')) {
                    $table->foreign('route_id', 'ug_age_ver_route_fk')
                          ->references('id')->on('urban_goodz_dedicated_routes')
                          ->nullOnDelete();
                }
                if (Schema::hasTable('orders')) {
                    $table->foreign('order_id', 'ug_age_ver_order_fk')
                          ->references('id')->on('orders')
                          ->nullOnDelete();
                }
                if (Schema::hasTable('delivery_men')) {
                    $table->foreign('driver_id', 'ug_age_ver_driver_fk')
                          ->references('id')->on('delivery_men')
                          ->nullOnDelete();
                }
                if (Schema::hasTable('admins')) {
                    $table->foreign('admin_reviewed_by', 'ug_age_ver_admin_fk')
                          ->references('id')->on('admins')
                          ->nullOnDelete();
                }

                $table->index(['package_id', 'verification_status'], 'ug_age_ver_pkg_status_idx');
                $table->index(['order_id', 'verification_status'], 'ug_age_ver_order_status_idx');
                $table->index('admin_review_required', 'ug_age_ver_review_req_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('urban_goodz_age_verifications');
    }
};
