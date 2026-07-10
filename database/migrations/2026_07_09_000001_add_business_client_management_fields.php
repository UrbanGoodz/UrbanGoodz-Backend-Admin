<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('urban_goodz_business_clients')) {
            Schema::table('urban_goodz_business_clients', function (Blueprint $table) {
                if (!Schema::hasColumn('urban_goodz_business_clients', 'contact_name')) {
                    $table->string('contact_name')->nullable()->after('legal_name');
                }
                if (!Schema::hasColumn('urban_goodz_business_clients', 'contact_email')) {
                    $table->string('contact_email')->nullable()->after('email');
                }
                if (!Schema::hasColumn('urban_goodz_business_clients', 'contact_phone')) {
                    $table->string('contact_phone')->nullable()->after('phone');
                }
                if (!Schema::hasColumn('urban_goodz_business_clients', 'billing_email')) {
                    $table->string('billing_email')->nullable()->after('contact_email');
                }
                if (!Schema::hasColumn('urban_goodz_business_clients', 'billing_phone')) {
                    $table->string('billing_phone')->nullable()->after('billing_email');
                }
                if (!Schema::hasColumn('urban_goodz_business_clients', 'billing_terms')) {
                    $table->string('billing_terms')->default('due_on_receipt')->after('notes');
                }
                if (!Schema::hasColumn('urban_goodz_business_clients', 'credit_limit')) {
                    $table->decimal('credit_limit', 12, 2)->nullable()->after('billing_terms');
                }
                if (!Schema::hasColumn('urban_goodz_business_clients', 'payment_method_status')) {
                    $table->string('payment_method_status')->default('not_added')->after('credit_limit');
                }
            });
        }

        if (Schema::hasTable('urban_goodz_business_client_users')) {
            Schema::table('urban_goodz_business_client_users', function (Blueprint $table) {
                if (!Schema::hasColumn('urban_goodz_business_client_users', 'last_login_at')) {
                    $table->timestamp('last_login_at')->nullable()->after('remember_token');
                }
                if (!Schema::hasColumn('urban_goodz_business_client_users', 'status')) {
                    $table->string('status')->default('active')->after('is_active');
                }
            });
        }

        if (Schema::hasTable('urban_goodz_business_client_locations')) {
            Schema::table('urban_goodz_business_client_locations', function (Blueprint $table) {
                if (!Schema::hasColumn('urban_goodz_business_client_locations', 'operating_hours')) {
                    $table->text('operating_hours')->nullable()->after('contact_email');
                }
                if (!Schema::hasColumn('urban_goodz_business_client_locations', 'pickup_instructions')) {
                    $table->text('pickup_instructions')->nullable()->after('operating_hours');
                }
                if (!Schema::hasColumn('urban_goodz_business_client_locations', 'delivery_instructions')) {
                    $table->text('delivery_instructions')->nullable()->after('pickup_instructions');
                }
            });
        }
    }

    public function down(): void
    {
    }
};
