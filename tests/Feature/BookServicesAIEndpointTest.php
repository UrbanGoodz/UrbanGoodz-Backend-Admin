<?php

namespace Tests\Feature;

use App\Models\UrbanGoodzProviderAvailability;
use App\Models\UrbanGoodzServiceProvider;
use App\Models\UrbanGoodzServiceRequest;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\Passport;
use Tests\TestCase;

class BookServicesAIEndpointTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('f_name')->nullable();
            $table->string('l_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->timestamps();
        });
        Schema::create('urban_goodz_service_providers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->string('business_name');
            $table->string('slug')->unique();
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('service_category')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('service_areas')->nullable();
            $table->string('approval_status', 24)->default('pending');
            $table->json('location_modes')->nullable();
            $table->decimal('rating', 4, 2)->default(0);
            $table->unsignedInteger('rating_count')->default(0);
            $table->timestamps();
        });
        Schema::create('urban_goodz_provider_availability', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('provider_id');
            $table->unsignedTinyInteger('day_of_week');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->string('timezone', 64)->default('America/Chicago');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('urban_goodz_service_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('service_type')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('assigned_vendor_id')->nullable();
            $table->text('admin_notes')->nullable();
            $table->json('preferred_dates')->nullable();
            $table->string('location')->nullable();
            $table->unsignedBigInteger('provider_id')->nullable();
            $table->unsignedBigInteger('provider_service_id')->nullable();
            $table->string('location_mode', 24)->nullable();
            $table->text('location_details')->nullable();
            $table->dateTime('requested_start_at')->nullable();
            $table->dateTime('scheduled_at')->nullable();
            $table->unsignedBigInteger('quoted_amount_minor')->nullable();
            $table->unsignedBigInteger('deposit_amount_minor')->nullable();
            $table->string('currency', 3)->default('USD');
            $table->text('provider_notes')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->string('payment_status', 24)->default('not_required');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('urban_goodz_service_provider_earnings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('provider_id');
            $table->unsignedBigInteger('service_request_id');
            $table->unsignedBigInteger('gross_amount_minor')->default(0);
            $table->unsignedBigInteger('platform_fee_minor')->default(0);
            $table->unsignedBigInteger('provider_amount_minor')->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('status', 24)->default('pending');
            $table->timestamps();
        });
        Schema::create('urban_goodz_service_reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('service_request_id');
            $table->unsignedBigInteger('provider_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->timestamps();
        });
        Schema::create('urban_goodz_service_booking_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('service_request_id');
            $table->string('actor_type', 24);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function test_customer_get_providers_lists_active_providers(): void
    {
        UrbanGoodzServiceProvider::create([
            'business_name' => 'Clean Pro',
            'slug' => 'clean-pro',
            'service_category' => 'cleaning',
            'description' => 'Home cleaning services',
            'is_active' => true,
            'is_verified' => true,
            'service_areas' => ['Dallas'],
            'rating' => 4.5,
        ]);
        $user = User::create(['f_name' => 'C', 'l_name' => 'U', 'email' => 'c1@example.com', 'phone' => '5550001000']);

        Passport::actingAs($user);
        $response = $this->getJson('/api/v1/customer/service-bookings/ai/providers?service_name=clean&location=Dallas');

        $response->assertOk();
        $response->assertJsonPath('total_found', 1);
        $response->assertJsonPath('providers.0.name', 'Clean Pro');
    }

    public function test_customer_match_providers_ranks_available_provider_first(): void
    {
        $available = UrbanGoodzServiceProvider::create([
            'business_name' => 'Handy Man',
            'slug' => 'handy-man',
            'service_category' => 'repair',
            'description' => 'General repair',
            'is_active' => true,
            'service_areas' => ['Austin'],
            'rating' => 4.0,
        ]);
        UrbanGoodzProviderAvailability::create([
            'provider_id' => $available->id,
            'day_of_week' => 2,
            'starts_at' => '09:00:00',
            'ends_at' => '17:00:00',
            'is_active' => true,
        ]);
        UrbanGoodzServiceProvider::create([
            'business_name' => 'Bob Repairs',
            'slug' => 'bob-repairs',
            'service_category' => 'repair',
            'description' => 'Repair shop',
            'is_active' => true,
            'service_areas' => ['Austin'],
            'rating' => 3.5,
        ]);
        $user = User::create(['f_name' => 'C', 'l_name' => 'U', 'email' => 'c2@example.com', 'phone' => '5550002000']);

        Passport::actingAs($user);
        $response = $this->postJson('/api/v1/customer/service-bookings/ai/match', [
            'service_name' => 'repair',
            'location' => 'Austin',
            'date' => '2026-08-18',
            'time' => '10:00',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('total_found', 2);
        $response->assertJsonPath('best_match.id', $available->id);
    }

    public function test_vendor_prep_time_route_resolves(): void
    {
        $response = $this->withHeader('vendorType', 'owner')
            ->postJson('/api/v1/vendor/service-bookings/ai/prep-time', [
                'items' => [['name' => 'Burger', 'quantity' => 2]],
                'store_type' => 'restaurant',
            ]);

        $this->assertContains($response->getStatusCode(), [200, 401, 403], 'Route must resolve; auth may reject without token.');
    }

    public function test_completion_records_earning_and_review(): void
    {
        $user = User::create(['f_name' => 'C', 'l_name' => 'U', 'email' => 'c3@example.com', 'phone' => '5550003000']);
        $provider = UrbanGoodzServiceProvider::create([
            'business_name' => 'Mover Co',
            'slug' => 'mover-co',
            'service_category' => 'moving',
            'description' => 'Movers',
            'is_active' => true,
            'service_areas' => ['Dallas'],
        ]);
        $booking = UrbanGoodzServiceRequest::create([
            'user_id' => $user->id,
            'service_type' => 'moving',
            'status' => 'in_progress',
            'location' => 'Dallas',
            'provider_id' => $provider->id,
            'quoted_amount_minor' => 20000,
            'currency' => 'USD',
        ]);

        Passport::actingAs($user);
        $response = $this->postJson('/api/v1/customer/service-bookings/ai/verify', [
            'request_id' => $booking->id,
            'customer_rating' => 5,
            'notes' => 'Great job',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('urban_goodz_service_requests', ['id' => $booking->id, 'status' => 'completed']);
        $this->assertDatabaseHas('urban_goodz_service_provider_earnings', ['service_request_id' => $booking->id]);
        $this->assertDatabaseHas('urban_goodz_service_reviews', ['service_request_id' => $booking->id, 'rating' => 5]);
    }
}
