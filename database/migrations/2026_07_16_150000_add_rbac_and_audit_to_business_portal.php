<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('urban_goodz_business_portal_audit_logs')) {
            return;
        }

        Schema::create('urban_goodz_business_portal_audit_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('admin_id')->nullable()->constrained('admins');
            $table->foreignId('business_client_user_id')->nullable()->constrained('urban_goodz_business_client_users');
            $table->unsignedBigInteger('business_client_id')->index();
            $table->string('action');
            $table->string('mode')->nullable();
            $table->string('target_type')->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->json('details')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });

        if (Schema::hasTable('urban_goodz_business_client_users')) {
            Schema::table('urban_goodz_business_client_users', function (Blueprint $table) {
                if (!Schema::hasColumn('urban_goodz_business_client_users', 'portal_role')) {
                    $table->string('portal_role')->default('viewer');
                }

                if (!Schema::hasColumn('urban_goodz_business_client_users', 'portal_permissions')) {
                    $table->json('portal_permissions')->nullable();
                }
            });

            DB::table('urban_goodz_business_client_users')
                ->where('owner_admin', true)
                ->update(['portal_role' => 'business_owner']);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('urban_goodz_business_portal_audit_logs');

        if (Schema::hasTable('urban_goodz_business_client_users')) {
            Schema::table('urban_goodz_business_client_users', function (Blueprint $table) {
                if (Schema::hasColumn('urban_goodz_business_client_users', 'portal_permissions')) {
                    $table->dropColumn('portal_permissions');
                }

                if (Schema::hasColumn('urban_goodz_business_client_users', 'portal_role')) {
                    $table->dropColumn('portal_role');
                }
            });
        }
    }
};
