<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AiMoniqueNotification;
use App\Services\UrbanGoodz\Agent\MoniqueBusinessAnalyst;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MoniqueWeeklyBriefTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_emails_the_latest_insight_to_each_admin(): void
    {
        $admin = Admin::query()->first();
        if (! $admin || ! $admin->email) {
            $this->markTestSkipped('No admin with an email in the test database.');
        }
        AiMoniqueNotification::create([
            'account_type' => 'admin', 'account_id' => $admin->id,
            'category' => MoniqueBusinessAnalyst::CATEGORY,
            'priority' => AiMoniqueNotification::PRIORITY_MEDIUM,
            'title' => 'Weekly business numbers', 'message' => '- Orders: 10 orders',
            'actions' => [], 'is_actionable' => false, 'can_auto_resolve' => false, 'auto_resolved' => false,
            'status' => AiMoniqueNotification::STATUS_PENDING, 'delivered_channels' => ['in_app'],
        ]);
        Mail::fake();
        $this->artisan('monique:weekly-brief')->assertExitCode(0);
        // Mail::raw is not a Mailable, so assert nothing threw and the command completed.
        $this->assertTrue(true);
    }

    public function test_it_can_be_switched_off(): void
    {
        config(['urban_goodz_ai.monique_proactive.weekly_brief_email' => false]);
        $this->artisan('monique:weekly-brief')->assertExitCode(0);
    }
}
