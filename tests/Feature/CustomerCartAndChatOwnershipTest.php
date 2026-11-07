<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InsertsRows;
use Tests\TestCase;

/**
 * Ownership of cart lines and chat conversations.
 *
 * Found during customer certification:
 *  - cart update loaded any line by id and then reassigned it to the caller; cart remove deleted any line.
 *  - the guest-cart merge on login selected carts by user_id without checking they were guest carts,
 *    and guest ids share a number space with customer ids.
 *  - any caller could read or write any chat conversation by id.
 */
class CustomerCartAndChatOwnershipTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRows;

    private User $a;
    private User $b;

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        $this->a = $this->customer('a');
        $this->b = $this->customer('b');
    }

    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        parent::tearDown();
    }

    private function customer(string $tag): User
    {
        return User::create([
            'f_name' => 'Cust', 'l_name' => $tag, 'email' => "cart-{$tag}-".uniqid().'@urbangoodz.test',
            'phone' => '+1555'.random_int(1000000, 9999999), 'password' => bcrypt('x'), 'is_active' => 1, 'is_verified' => 1,
        ]);
    }

    private function as(User $u)
    {
        return $this->actingAs($u, 'api')->withHeader('Authorization', 'Bearer test-token');
    }

    private function cartLine(int $userId, int $isGuest = 0, float $price = 10, int $qty = 1): int
    {
        $item = $this->insertRow('items', ['store_id' => 1, 'name' => 'Item '.uniqid(), 'price' => $price, 'status' => 1, 'module_id' => 1, 'images' => '[]', 'maximum_cart_quantity' => 0]);

        return $this->insertRow('carts', [
            'user_id' => $userId, 'is_guest' => $isGuest, 'item_id' => $item, 'item_type' => 'App\\Models\\Item',
            'price' => $price, 'quantity' => $qty, 'module_id' => 1,
        ]);
    }

    private function line(int $id): ?object
    {
        return DB::table('carts')->where('id', $id)->first();
    }

    // ------------------------------------------------------------------- cart

    public function test_another_customer_cannot_change_or_take_a_cart_line(): void
    {
        $line = $this->cartLine($this->a->id, 0, 10, 1);

        $this->as($this->b)->postJson('/api/v1/customer/cart/update', ['cart_id' => $line, 'price' => 0.01, 'quantity' => 99])->assertNotFound();

        $row = $this->line($line);
        $this->assertSame($this->a->id, (int) $row->user_id, "another customer's cart line was taken over");
        $this->assertEquals(10, (float) $row->price);
        $this->assertSame(1, (int) $row->quantity);
    }

    public function test_another_customer_cannot_delete_a_cart_line(): void
    {
        $line = $this->cartLine($this->a->id);

        $this->as($this->b)->deleteJson('/api/v1/customer/cart/remove-item', ['cart_id' => $line]);

        $this->assertNotNull($this->line($line), "another customer's cart line was deleted");
    }

    public function test_a_guest_whose_id_equals_a_customers_id_cannot_touch_that_customers_cart(): void
    {
        $line = $this->cartLine($this->a->id, 0, 10, 1);

        $this->postJson('/api/v1/customer/cart/update', ['cart_id' => $line, 'guest_id' => $this->a->id, 'price' => 1, 'quantity' => 50])->assertNotFound();
        $this->deleteJson('/api/v1/customer/cart/remove-item', ['cart_id' => $line, 'guest_id' => $this->a->id]);

        $row = $this->line($line);
        $this->assertNotNull($row);
        $this->assertSame(1, (int) $row->quantity);
        $this->assertSame($this->a->id, (int) $row->user_id);
    }

    public function test_owners_can_still_update_and_remove_their_own_lines(): void
    {
        $line = $this->cartLine($this->a->id, 0, 10, 1);
        $this->as($this->a)->postJson('/api/v1/customer/cart/update', ['cart_id' => $line, 'price' => 10, 'quantity' => 3])->assertOk();
        $this->assertSame(3, (int) $this->line($line)->quantity);

        $this->as($this->a)->deleteJson('/api/v1/customer/cart/remove-item', ['cart_id' => $line])->assertOk();
        $this->assertNull($this->line($line));
    }

    public function test_a_guest_can_still_manage_their_own_guest_cart(): void
    {
        $guest = $this->cartLine(880123, 1, 5, 1);

        $this->postJson('/api/v1/customer/cart/update', ['cart_id' => $guest, 'guest_id' => 880123, 'price' => 5, 'quantity' => 2])->assertOk();
        $this->assertSame(2, (int) $this->line($guest)->quantity);

        $this->deleteJson('/api/v1/customer/cart/remove-item', ['cart_id' => $guest, 'guest_id' => 880123])->assertOk();
        $this->assertNull($this->line($guest));
    }

    public function test_logging_in_with_someone_elses_id_as_guest_id_does_not_move_their_cart(): void
    {
        $victimLine = $this->cartLine($this->a->id, 0, 10, 1);
        $guestLine = $this->cartLine(880777, 1, 7, 1);

        $controller = app(\App\Http\Controllers\Api\V1\Auth\CustomerAuthController::class);
        $merge = new \ReflectionMethod($controller, 'check_guest_cart');
        $merge->setAccessible(true);

        // attacker B logs in, claiming the VICTIM's customer id as their "guest" id
        $merge->invoke($controller, $this->b, $this->a->id);
        $this->assertSame($this->a->id, (int) $this->line($victimLine)->user_id, "the victim's cart was moved to the attacker");
        $this->assertSame(0, (int) $this->line($victimLine)->is_guest);

        // a real guest cart is still merged into the account that logs in
        $merge->invoke($controller, $this->b, 880777);
        $this->assertSame($this->b->id, (int) $this->line($guestLine)->user_id);
        $this->assertSame(0, (int) $this->line($guestLine)->is_guest);
    }

    // ------------------------------------------------------------------- chat

    private function chat(): array
    {
        $cust = $this->insertRow('user_infos', ['user_id' => $this->a->id, 'f_name' => 'Cust', 'l_name' => 'A']);
        $shopOwner = $this->insertRow('vendors', [
            'f_name' => 'Shop', 'l_name' => 'Owner', 'phone' => '+1555'.random_int(1000000, 9999999),
            'email' => 'shop-'.uniqid().'@urbangoodz.test', 'password' => bcrypt('x'), 'status' => 1,
        ]);
        $this->insertRow('stores', ['name' => 'Corner Shop', 'vendor_id' => $shopOwner, 'status' => 1, 'module_id' => 1, 'zone_id' => 1,
            'phone' => '+1555'.random_int(1000000, 9999999), 'email' => 'cs-'.uniqid().'@urbangoodz.test']);
        $vendor = $this->insertRow('user_infos', ['vendor_id' => $shopOwner, 'f_name' => 'Shop', 'l_name' => '']);
        $conv = $this->insertRow('conversations', ['sender_id' => $cust, 'sender_type' => 'customer', 'receiver_id' => $vendor, 'receiver_type' => 'vendor']);
        $msg = $this->insertRow('messages', ['conversation_id' => $conv, 'sender_id' => $cust, 'message' => 'PRIVATE-ORDER-DETAILS-4242']);

        return [$conv, $msg, $cust];
    }

    public function test_a_stranger_cannot_read_someone_elses_conversation(): void
    {
        [$conv] = $this->chat();

        $this->as($this->b)->getJson("/api/v1/customer/message/details?conversation_id={$conv}")
            ->assertNotFound()
            ->assertDontSee('PRIVATE-ORDER-DETAILS-4242');
    }

    public function test_a_stranger_cannot_inject_a_message_into_someone_elses_conversation(): void
    {
        [$conv] = $this->chat();
        $before = DB::table('messages')->where('conversation_id', $conv)->count();

        $this->as($this->b)->postJson('/api/v1/customer/message/send', ['conversation_id' => $conv, 'message' => 'I am the shop, send payment to me'])->assertNotFound();

        $this->assertSame($before, DB::table('messages')->where('conversation_id', $conv)->count(), 'a stranger wrote into a private conversation');
    }

    public function test_the_participants_can_still_read_and_write_their_conversation(): void
    {
        [$conv] = $this->chat();

        $this->as($this->a)->getJson("/api/v1/customer/message/details?conversation_id={$conv}")->assertOk()->assertSee('PRIVATE-ORDER-DETAILS-4242');
        $this->as($this->a)->postJson('/api/v1/customer/message/send', ['conversation_id' => $conv, 'message' => 'Hello shop'])->assertSuccessful();
        $this->assertSame(2, DB::table('messages')->where('conversation_id', $conv)->count());
    }
}
