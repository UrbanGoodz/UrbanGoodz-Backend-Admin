<?php

namespace Tests\Feature;

use App\Models\ExternalConfiguration;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * POST customer/wallet/transfer-mart-from-drivemond is a server-to-server credit with no user
 * session (auth:api is removed on purpose), guarded only by shared secrets. It must therefore be
 * strict about secrets, amounts and replays.
 */
class PartnerWalletTransferTest extends TestCase
{
    use DatabaseTransactions;

    private const URL = '/api/v1/customer/wallet/transfer-mart-from-drivemond';
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config()->set('currency_conf', ['key' => 'currency', 'value' => 'USD']);
        foreach (['activation_mode' => '1', 'drivemond_base_url' => 'https://drive.test', 'drivemond_token' => 'dm-secret', 'system_self_token' => 'mart-secret'] as $k => $v) {
            ExternalConfiguration::updateOrCreate(['key' => $k], ['value' => $v]);
        }
        $this->user = User::create(['f_name' => 'Wal', 'l_name' => 'Let', 'email' => 'wal-'.uniqid().'@urbangoodz.test', 'phone' => '+1555'.random_int(1000000, 9999999), 'password' => bcrypt('x')]);
        // Set directly: another test in the full run changes mass-assignment behaviour, which
        // silently dropped wallet_balance here.
        \Illuminate\Support\Facades\DB::table('users')->where('id', $this->user->id)->update(['wallet_balance' => 10]);
        $this->user = $this->user->fresh();
        Http::fake(['drive.test/*' => Http::response(['status' => true, 'data' => ['phone' => $this->user->phone]], 200)]);
    }

    private function body(array $over = []): array
    {
        return array_merge([
            'currency' => 'USD', 'amount' => 25, 'bearer_token' => 'bt-'.uniqid(),
            'token' => 'mart-secret', 'external_base_url' => 'https://drive.test', 'external_token' => 'dm-secret',
        ], $over);
    }

    public function test_a_valid_transfer_credits_the_customer_once_and_a_replay_does_not(): void
    {
        $body = $this->body();
        $this->postJson(self::URL, $body)->assertOk()->assertJsonPath('status', true);
        $this->assertSame(35.0, (float) $this->user->fresh()->wallet_balance);

        $this->postJson(self::URL, $body)->assertOk()->assertJsonPath('status', false);
        $this->assertSame(35.0, (float) $this->user->fresh()->wallet_balance, 'a replayed call must not credit again');
    }

    public function test_negative_zero_and_non_numeric_amounts_never_touch_the_balance(): void
    {
        foreach ([-50, 0, 'abc', 99999999] as $bad) {
            $this->postJson(self::URL, $this->body(['amount' => $bad]))->assertOk()->assertJsonPath('status', false);
        }
        $this->assertSame(10.0, (float) $this->user->fresh()->wallet_balance);
    }

    public function test_wrong_secrets_credit_nothing_including_numeric_lookalikes(): void
    {
        ExternalConfiguration::where('key', 'system_self_token')->update(['value' => '0e111']);

        foreach ([['token' => 'nope'], ['token' => '0e222'], ['external_token' => 'nope'], ['external_base_url' => 'https://evil.test']] as $bad) {
            $this->postJson(self::URL, $this->body($bad + ['token' => '0e222']))->assertOk()->assertJsonPath('status', false);
        }
        $this->assertSame(10.0, (float) $this->user->fresh()->wallet_balance, '"0e111" == "0e222" under loose comparison; it must not be accepted');
    }

    public function test_a_failed_partner_lookup_does_not_block_a_legitimate_retry(): void
    {
        $body = $this->body();
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake(['drive.test/*' => Http::sequence()
            ->push([], 500)
            ->push(['status' => true, 'data' => ['phone' => $this->user->phone]], 200)]);

        $this->postJson(self::URL, $body)->assertOk()->assertJsonPath('status', false);
        $this->assertSame(10.0, (float) $this->user->fresh()->wallet_balance);

        $this->postJson(self::URL, $body)->assertOk()->assertJsonPath('status', true);
        $this->assertSame(35.0, (float) $this->user->fresh()->wallet_balance);
    }
}
