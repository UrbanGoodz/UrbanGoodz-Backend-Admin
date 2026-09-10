<?php

namespace Tests\Feature;

use App\Services\UrbanGoodz\AllowedActionRegistry;
use App\Services\UrbanGoodz\PersonReferenceResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * What a customer can actually get Skylar to do.
 *
 * Monique's equivalent covers the operator side. This covers the requirement
 * that Skylar be able to do the things a customer asks rather than describe
 * them: the customer-facing half of the same execution engine.
 */
class SkylarCustomerUsageTest extends TestCase
{
    use DatabaseTransactions;

    private function registry(): AllowedActionRegistry
    {
        return app(AllowedActionRegistry::class);
    }

    public function test_customer_role_has_a_real_action_surface(): void
    {
        $actions = $this->registry()->getAllowedActionsForRole('customer');

        // Skylar being "capable" has to mean a concrete list, not a claim.
        $this->assertGreaterThanOrEqual(
            25,
            count($actions),
            'Customer action surface has shrunk; Skylar can do less than before'
        );
    }

    /**
     * The things a customer most often asks for must all exist as actions.
     *
     * @return array<string, array{0: string}>
     */
    public static function everydayCustomerActions(): array
    {
        return [
            'cancel an order'      => ['cancel_order'],
            'track a delivery'     => ['track_delivery'],
            'delivery status'      => ['get_delivery_status'],
            'book a rental'        => ['book_rental_asset'],
            'search the market'    => ['search_marketplace'],
            'find a stylist'       => ['search_stylists'],
            'order from anywhere'  => ['create_order_anywhere_request'],
            'book anything'        => ['submit_book_anything_request'],
            'find events'          => ['search_events'],
            'earn money'           => ['search_earn_money_opportunities'],
        ];
    }

    #[DataProvider('everydayCustomerActions')]
    public function test_everyday_request_has_an_action(string $action): void
    {
        $definition = $this->registry()->getActionDefinition($action);

        $this->assertNotNull($definition, "No action defined for {$action}");
        $this->assertContains(
            'customer',
            $definition['roles'] ?? [],
            "{$action} exists but a customer is not allowed to run it"
        );
    }

    public function test_cancelling_an_order_asks_before_doing_it(): void
    {
        $definition = $this->registry()->getActionDefinition('cancel_order');

        // Cancelling is not reversible from the customer's side, so Skylar
        // must confirm rather than act on a single ambiguous sentence.
        $this->assertTrue(
            (bool) ($definition['requires_confirmation'] ?? false),
            'cancel_order must require confirmation before executing'
        );
    }

    public function test_paying_is_gated(): void
    {
        $definition = $this->registry()->getActionDefinition('authorize_payment');

        $this->assertNotNull($definition);
        $this->assertTrue(
            (bool) ($definition['requires_confirmation'] ?? false),
            'Authorizing a payment must never happen without confirmation'
        );
    }

    public function test_read_only_lookups_do_not_nag_for_confirmation(): void
    {
        // Asking "where is my order" should just answer.
        foreach (['track_delivery', 'get_delivery_status', 'search_marketplace'] as $action) {
            $definition = $this->registry()->getActionDefinition($action);
            $this->assertNotNull($definition, "{$action} missing");
            $this->assertFalse(
                (bool) ($definition['requires_confirmation'] ?? false),
                "{$action} is a lookup and should not require confirmation"
            );
        }
    }

    public function test_a_customer_cannot_run_operator_actions(): void
    {
        $customerActions = $this->registry()->getAllowedActionsForRole('customer');

        // Operator powers must not leak into the customer surface.
        foreach (['assign_order', 'retry_queue_job'] as $operatorAction) {
            $this->assertNotContains(
                $operatorAction,
                $customerActions,
                "{$operatorAction} must not be available to a customer"
            );
        }
    }

    public function test_skylar_resolves_who_the_customer_is_talking_about(): void
    {
        // "My driver is Marcus. Where is he?" - the same resolver Monique uses.
        $resolver = new PersonReferenceResolver();
        $resolver->observeTurn('Marcus is my driver. He has my order.');
        $out = $resolver->observeTurn('Where is he right now?');

        $this->assertEquals('Marcus', $out['he'] ?? null);
    }

    public function test_skylar_does_not_assume_a_customers_pronouns(): void
    {
        $resolver = new PersonReferenceResolver();
        $resolver->registerFromRecord('c-1', 'Jordan', 'customer');

        $this->assertFalse($resolver->pronounsAreKnown('Jordan'));
        $this->assertEquals('they', $resolver->pronounsFor('Jordan')['subject']);
    }

    public function test_every_customer_action_declares_its_safety_posture(): void
    {
        // An action missing these fields would inherit defaults silently, which
        // is how a destructive call ends up running without a confirmation.
        foreach ($this->registry()->getAllowedActionsForRole('customer') as $action) {
            $definition = $this->registry()->getActionDefinition($action);

            $this->assertIsArray($definition, "{$action} has no definition");
            $this->assertArrayHasKey('requires_confirmation', $definition, "{$action} missing requires_confirmation");
            $this->assertArrayHasKey('idempotent', $definition, "{$action} missing idempotent");
            $this->assertArrayHasKey('roles', $definition, "{$action} missing roles");
        }
    }
}
