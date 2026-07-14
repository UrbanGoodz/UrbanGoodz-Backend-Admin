<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\BusinessSettingsController;
use App\Mail\TestEmailSender;
use App\Models\BusinessSetting;
use App\Services\MailRuntimeConfiguration;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class UrbanGoodzSmtpDispatchTest extends TestCase
{
    use DatabaseTransactions;

    public function test_smtp_test_path_accepts_safe_recipient_without_real_delivery(): void
    {
        $mailRuntime = app(MailRuntimeConfiguration::class);
        BusinessSetting::updateOrCreate(
            ['key' => 'mail_config'],
            ['value' => json_encode([
                'status' => 1,
                'driver' => 'smtp',
                'host' => 'smtp.example.test',
                'port' => 587,
                'username' => 'session9-safe-user',
                'password' => $mailRuntime->encryptPassword('session9-safe-password'),
                'encryption' => 'tls',
                'email_id' => 'no-reply@urbangoodz.test',
                'name' => 'Urban Goodz Test',
                'timeout' => 5,
            ])]
        );
        Mail::fake();
        $request = Request::create('/admin/business-settings/third-party/send-mail', 'POST', [
            'email' => 'session9-safe-recipient@urbangoodz.test',
        ]);

        $response = app(BusinessSettingsController::class)->send_mail($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue((bool) $response->getData(true)['accepted']);
        $this->assertSame('provider_acceptance', $response->getData(true)['stage']);
        Mail::assertSent(TestEmailSender::class, fn (TestEmailSender $mail) =>
            $mail->hasTo('session9-safe-recipient@urbangoodz.test')
        );
    }
}
