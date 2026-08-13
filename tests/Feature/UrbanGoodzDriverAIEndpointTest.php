<?php

namespace Tests\Feature;

use App\Models\DriverCertification;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UrbanGoodzDriverAIEndpointTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        Schema::create('delivery_men', function (Blueprint $table) {
            $table->id();
            $table->string('f_name')->nullable();
            $table->string('l_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('auth_token')->nullable();
            $table->unsignedBigInteger('vehicle_id')->nullable();
            $table->timestamps();
        });
        Schema::create('driver_certifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('delivery_man_id');
            $table->string('name');
            $table->string('issuing_authority')->nullable();
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('status')->default('active');
            $table->string('document_url')->nullable();
            $table->boolean('is_required')->default(false);
            $table->string('renewal_status')->nullable();
            $table->timestamps();
        });
        Schema::create('urban_goodz_driver_earnings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('delivery_man_id');
            $table->unsignedBigInteger('load_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('earning_type')->default('package_delivery');
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('status')->default('approved');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('storages', function (Blueprint $table) {
            $table->id();
            $table->string('data_type');
            $table->string('data_id');
            $table->string('key')->nullable();
            $table->string('value');
            $table->timestamps();
        });
    }

    private function createDriver(): int
    {
        return DB::table('delivery_men')->insertGetId([
            'f_name' => 'Dre',
            'l_name' => 'Goodz',
            'phone' => '5550009001',
            'email' => 'driver1@example.com',
            'auth_token' => 'dm-test-token-001',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function authorizedHeaders(): array
    {
        return ['Authorization' => 'Bearer dm-test-token-001'];
    }

    public function test_get_warnings_returns_success_with_empty_warnings(): void
    {
        $this->createDriver();

        $response = $this->withHeaders($this->authorizedHeaders())
            ->getJson('/api/v1/ai/warnings');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('warnings', []);
    }

    public function test_get_warnings_flags_expiring_certifications(): void
    {
        $driverId = $this->createDriver();
        DriverCertification::create([
            'delivery_man_id' => $driverId,
            'name' => 'CDL Class A',
            'expiry_date' => now()->addDays(10),
            'status' => 'active',
        ]);

        $response = $this->withHeaders($this->authorizedHeaders())
            ->getJson('/api/v1/ai/warnings');

        $response->assertOk();
        $this->assertStringContainsString(
            'expiring within 30 days',
            $response->json('warnings.0')
        );
    }

    public function test_get_warnings_flags_fatigue_over_ten_hours(): void
    {
        $driverId = $this->createDriver();
        DB::table('urban_goodz_driver_earnings')->insert([
            ['delivery_man_id' => $driverId, 'earning_type' => 'package_delivery', 'amount' => 100, 'created_at' => now()->startOfDay()->addHours(6), 'updated_at' => now()],
            ['delivery_man_id' => $driverId, 'earning_type' => 'package_delivery', 'amount' => 200, 'created_at' => now()->startOfDay()->addHours(17), 'updated_at' => now()],
        ]);

        $response = $this->withHeaders($this->authorizedHeaders())
            ->getJson('/api/v1/ai/warnings');

        $response->assertOk();
        $this->assertStringContainsString(
            'consider mandatory break',
            $response->json('warnings.0')
        );
    }

    public function test_earnings_per_hour_aggregates_week_earnings(): void
    {
        $driverId = $this->createDriver();
        DB::table('urban_goodz_driver_earnings')->insert([
            ['delivery_man_id' => $driverId, 'earning_type' => 'package_delivery', 'amount' => 150.00, 'created_at' => now()->subDays(2)->addHours(8), 'updated_at' => now()],
            ['delivery_man_id' => $driverId, 'earning_type' => 'package_delivery', 'amount' => 50.00, 'created_at' => now()->subDays(2)->addHours(12), 'updated_at' => now()],
        ]);

        $response = $this->withHeaders($this->authorizedHeaders())
            ->getJson('/api/v1/ai/earnings-per-hour?period=week');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('period', 'week');
        $response->assertJsonPath('total_earnings', 200);
        $response->assertJsonPath('total_orders', 2);
        $response->assertJsonPath('avg_per_order', 100);
        $response->assertJsonPath('earnings_per_hour', 50);
    }

    public function test_earnings_per_hour_requires_dm_token(): void
    {
        $response = $this->getJson('/api/v1/ai/earnings-per-hour');

        $response->assertUnauthorized();
    }
}
