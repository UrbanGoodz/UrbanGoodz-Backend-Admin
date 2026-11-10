<?php

namespace Tests\Feature;

use App\Jobs\SendFirebaseNotification;
use App\Models\UrbanGoodzNotification;
use App\Models\User;
use App\Services\FirebaseNotificationTransport;
use App\Models\DeliveryMan;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InsertsRows;
use App\Services\UrbanGoodzNotificationService;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

/**
 * What actually reaches a phone. AI / ops notifications are stored with subject + body; the
 * push job read title + description, so every such push arrived as "Urban Goodz" with no text.
 */
class NotificationPayloadAndOptOutTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRows;

    private function customer(): User
    {
        $user = User::create([
            'f_name' => 'Push', 'l_name' => 'Test', 'email' => 'push-'.uniqid().'@urbangoodz.test',
            'phone' => '+1555'.random_int(1000000, 9999999), 'password' => bcrypt('x'),
        ]);
        // Set directly rather than through mass assignment, which other tests can change.
        DB::table('users')->where('id', $user->id)->update(['cm_firebase_token' => 'fcm-token-1']);

        return $user->fresh();
    }

    public function test_a_push_notification_carries_its_subject_and_body_to_the_device(): void
    {
        $user = $this->customer();
        $n = UrbanGoodzNotification::create([
            'recipient_type' => 'customer', 'recipient_id' => $user->id, 'channel' => 'push', 'event_type' => 'order_delivered',
            'subject' => 'Delivered', 'body' => 'Your order UG-1 has been delivered. Enjoy!', 'data' => ['order_id' => 77], 'priority' => 'normal', 'status' => 'pending',
        ]);

        $seen = null;
        $transport = Mockery::mock(FirebaseNotificationTransport::class);
        $transport->shouldReceive('send')->once()->andReturnUsing(function ($token, $payload) use (&$seen) {
            $seen = $payload;

            return true;
        });

        (new SendFirebaseNotification($n->id, 'customer', $user->id, 'push'))->handle($transport);

        $this->assertSame('Delivered', $seen['title'], 'the device must show the subject, not the generic app name');
        $this->assertSame('Your order UG-1 has been delivered. Enjoy!', $seen['description']);
        $this->assertSame('sent', $n->fresh()->status);
    }

    public function test_a_missing_device_token_fails_once_without_retry_noise(): void
    {
        $user = $this->customer();
        DB::table('users')->where('id', $user->id)->update(['cm_firebase_token' => null]);
        $n = UrbanGoodzNotification::create([
            'recipient_type' => 'customer', 'recipient_id' => $user->id, 'channel' => 'push', 'event_type' => 'order_ready',
            'subject' => 'Ready', 'body' => 'x', 'priority' => 'normal', 'status' => 'pending',
        ]);
        $transport = Mockery::mock(FirebaseNotificationTransport::class);
        $transport->shouldNotReceive('send');

        (new SendFirebaseNotification($n->id, 'customer', $user->id, 'push'))->handle($transport);   // must not throw

        $this->assertSame('failed', $n->fresh()->status);
    }

    // ------------------------------------------------------- inbox isolation (legacy endpoints)

    public function test_a_driver_only_sees_their_own_notifications_with_a_bearer_token(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        $mk = fn (string $t) => DeliveryMan::create([
            'f_name' => 'N', 'l_name' => $t, 'phone' => '+1555'.random_int(1000000, 9999999), 'email' => "n-{$t}-".uniqid().'@urbangoodz.test',
            'password' => bcrypt('x'), 'auth_token' => 'ntok-'.$t.'-'.bin2hex(random_bytes(8)), 'status' => 1, 'application_status' => 'approved', 'active' => 1,
        ]);
        $a = $mk('a');
        $b = $mk('b');
        UserNotification::create(['delivery_man_id' => $a->id, 'title' => 'A-ONLY', 'description' => 'secret-for-a', 'data' => json_encode(['title' => 'A-ONLY'])]);
        UserNotification::create(['delivery_man_id' => $b->id, 'title' => 'B-ONLY', 'description' => 'secret-for-b', 'data' => json_encode(['title' => 'B-ONLY'])]);

        $res = $this->withHeader('Authorization', 'Bearer '.$a->auth_token)->getJson('/api/v1/delivery-man/notifications')->assertOk();
        $body = json_encode($res->json());
        $this->assertStringContainsString('A-ONLY', $body);
        $this->assertStringNotContainsString('B-ONLY', $body);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    private function queued(string $channel, User $user): UrbanGoodzNotification
    {
        $n = UrbanGoodzNotification::create([
            'recipient_type' => 'customer', 'recipient_id' => $user->id, 'channel' => $channel, 'event_type' => 'order_delivered',
            'subject' => 'Delivered', 'body' => 'Your order was delivered.', 'priority' => 'normal', 'status' => 'pending',
        ]);
        app(UrbanGoodzNotificationService::class)->queueForDelivery([$n]);

        return $n->fresh();
    }

    public function test_an_email_channel_notification_is_actually_emailed(): void
    {
        config()->set('mail.status', true);
        config()->set('mail.default', 'array');
        config()->set('mail.from', ['address' => 'noreply@urbangoodz.test', 'name' => 'Urban Goodz']);
        Mail::purge('array');
        $user = $this->customer();

        $n = $this->queued('email', $user);

        $this->assertSame('sent', $n->status);
        $messages = Mail::mailer('array')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $this->assertSame($user->email, $messages[0]->getEnvelope()->getRecipients()[0]->getAddress());
        $this->assertStringContainsString('Delivered', $messages[0]->getOriginalMessage()->getSubject());
    }

    public function test_channels_without_a_transport_are_marked_failed_not_left_pending_forever(): void
    {
        $user = $this->customer();
        // Consent given, so the only thing left to stop it is the missing SMS transport.
        \App\Models\UrbanGoodzNotificationPreference::create(['recipient_type' => 'customer', 'recipient_id' => $user->id, 'sms_consent' => true]);

        $sms = $this->queued('sms', $user);

        $this->assertSame('failed', $sms->status);
        $this->assertStringContainsString('transport', $sms->data['delivery_note']);
        $this->assertSame('delivered', $this->queued('in_app', $user)->status);
    }

    // ------------------------------------------------------------------ preferences / consent

    private function authed(User $u)
    {
        return $this->actingAs($u, 'api');
    }

    public function test_preferences_default_to_service_messages_only_and_record_when_consent_was_given(): void
    {
        $user = $this->customer();
        $url = '/api/v1/urban-goodz/notification-preferences';

        $this->authed($user)->getJson($url)->assertOk()
            ->assertJsonPath('data.push_enabled', true)->assertJsonPath('data.email_enabled', true)
            ->assertJsonPath('data.sms_consent', false)->assertJsonPath('data.promotions_opt_in', false);

        $this->authed($user)->putJson($url, ['sms_consent' => true, 'promotions_opt_in' => true])->assertOk()
            ->assertJsonPath('data.sms_consent', true);
        $row = \App\Models\UrbanGoodzNotificationPreference::where('recipient_id', $user->id)->first();
        $this->assertNotNull($row->sms_consent_at, 'when consent was given is the evidence');

        $this->authed($user)->putJson($url, ['sms_consent' => false])->assertOk();
        $this->assertFalse((bool) $row->fresh()->sms_consent);
    }

    public function test_one_customer_cannot_change_anothers_preferences(): void
    {
        $a = $this->customer();
        $b = $this->customer();
        $this->authed($a)->putJson('/api/v1/urban-goodz/notification-preferences', ['promotions_opt_in' => true, 'user_id' => $b->id, 'recipient_id' => $b->id])->assertOk();

        $this->assertNull(\App\Models\UrbanGoodzNotificationPreference::where('recipient_id', $b->id)->first());
        $this->assertTrue((bool) \App\Models\UrbanGoodzNotificationPreference::where('recipient_id', $a->id)->value('promotions_opt_in'));
    }

    public function test_opt_outs_and_missing_consent_stop_delivery_but_service_messages_still_flow(): void
    {
        config()->set('mail.status', true);
        config()->set('mail.default', 'array');
        config()->set('mail.from', ['address' => 'noreply@urbangoodz.test', 'name' => 'Urban Goodz']);
        $user = $this->customer();
        $make = fn (string $channel, string $event) => tap(UrbanGoodzNotification::create([
            'recipient_type' => 'customer', 'recipient_id' => $user->id, 'channel' => $channel, 'event_type' => $event,
            'subject' => 'S', 'body' => 'B', 'priority' => 'normal', 'status' => 'pending',
        ]), fn ($n) => app(UrbanGoodzNotificationService::class)->queueForDelivery([$n]))->fresh();

        // Defaults: a promotion is blocked on every outside channel; an order update by email is fine; SMS needs consent.
        $this->assertSame('failed', $make('email', 'promotion_available')->status, 'promotions are opt-in');
        $this->assertSame('sent', $make('email', 'order_delivered')->status, 'service email goes out');
        $this->assertSame('failed', $make('sms', 'order_delivered')->status, 'no SMS without consent');
        $this->assertSame('delivered', $make('in_app', 'promotion_available')->status, 'the in-app copy is always kept');

        // Switch email off: service email stops too.
        \App\Models\UrbanGoodzNotificationPreference::create(['recipient_type' => 'customer', 'recipient_id' => $user->id, 'email_enabled' => false]);
        $blocked = $make('email', 'order_delivered');
        $this->assertSame('failed', $blocked->status);
        $this->assertStringContainsString('opted in', $blocked->data['delivery_note']);
    }
}
