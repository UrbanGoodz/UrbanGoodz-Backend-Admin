<?php

namespace Tests\Feature;

use App\Models\DispatcherSavedSearch;
use App\Models\LoadSource;
use App\Models\UrbanGoodzBusinessClient;
use App\Models\UrbanGoodzBusinessClientUser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DispatcherSourcingIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_dispatch_owner_can_open_sourcing_without_credential_exposure(): void
    {
        $this->withoutMiddleware();
        [, $owner] = $this->makeDispatcher('dispatch_owner');

        $source = LoadSource::create([
            'source_key' => 'dispatcher-safe-' . bin2hex(random_bytes(5)),
            'name' => 'Dispatcher Safe Source',
            'type' => 'api',
            'enabled' => false,
            'api_status' => 'awaiting_credentials',
            'partnership_status' => 'pending',
        ]);
        $source->setCredential('api_key', 'never-render-this-secret');

        $response = $this->actingAs($owner, 'business')
            ->get(route('business.dispatcher.sourcing'));

        $response->assertOk();
        $response->assertViewIs('business.dispatcher.sourcing.index');
        $response->assertViewHas('sourceHealth', function ($sources): bool {
            $attributes = $sources->firstWhere('name', 'Dispatcher Safe Source')?->getAttributes() ?? [];

            return !array_key_exists('encrypted_value', $attributes)
                && !array_key_exists('last_error_message', $attributes);
        });
        $response->assertDontSee('never-render-this-secret');
        $response->assertSee('awaiting_credentials');
    }

    public function test_dispatcher_sourcing_requires_explicit_permission(): void
    {
        $this->withoutMiddleware();
        [, $restricted] = $this->makeDispatcher('dispatcher', []);

        $this->actingAs($restricted, 'business')
            ->get(route('business.dispatcher.sourcing'))
            ->assertForbidden();

        [, $allowed] = $this->makeDispatcher('dispatcher', ['dispatch_sourcing_view']);

        $this->actingAs($allowed, 'business')
            ->get(route('business.dispatcher.sourcing'))
            ->assertOk();
    }

    public function test_saved_search_mutation_is_tenant_scoped(): void
    {
        $this->withoutMiddleware();
        [, $owner] = $this->makeDispatcher('dispatch_owner');
        [$otherClient, $otherOwner] = $this->makeDispatcher('dispatch_owner');

        $otherSearch = DispatcherSavedSearch::create([
            'business_client_user_id' => $otherOwner->id,
            'dispatch_company_id' => $otherClient->id,
            'name' => 'Other company search',
            'criteria' => ['origin_state' => 'TX'],
        ]);

        $this->actingAs($owner, 'business')
            ->delete(route('business.dispatcher.sourcing.saved-searches.delete', $otherSearch->id))
            ->assertNotFound();

        $this->assertDatabaseHas('dispatcher_saved_searches', ['id' => $otherSearch->id]);
    }

    /**
     * @return array{UrbanGoodzBusinessClient, UrbanGoodzBusinessClientUser}
     */
    private function makeDispatcher(string $role, array $permissions = []): array
    {
        $suffix = bin2hex(random_bytes(5));
        $client = UrbanGoodzBusinessClient::create([
            'company_name' => 'Dispatcher Client ' . $suffix,
            'email' => "dispatcher-client-{$suffix}@example.test",
            'status' => 'approved',
            'account_type' => 'dispatch_company',
        ]);
        $user = UrbanGoodzBusinessClientUser::create([
            'business_client_id' => $client->id,
            'first_name' => 'Dispatch',
            'last_name' => 'User',
            'email' => "dispatcher-user-{$suffix}@example.test",
            'password' => bcrypt('password'),
            'role' => $role,
            'permissions' => $permissions,
            'is_active' => true,
            'status' => 'active',
        ]);

        return [$client, $user];
    }
}
