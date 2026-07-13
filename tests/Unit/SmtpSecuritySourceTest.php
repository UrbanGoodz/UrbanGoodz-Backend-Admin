<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class SmtpSecuritySourceTest extends TestCase
{
    public function test_test_email_is_authorized_post_and_rate_limited(): void
    {
        $routes = file_get_contents(__DIR__.'/../../routes/admin.php');

        $this->assertStringContainsString("Route::post('send-mail'", $routes);
        $this->assertStringContainsString("->middleware('throttle:3,1')", $routes);
        $this->assertStringNotContainsString("Route::get('send-mail'", $routes);
    }

    public function test_password_is_not_rendered_or_logged_in_plaintext(): void
    {
        $view = file_get_contents(__DIR__.'/../../resources/views/admin-views/business-settings/mail-index.blade.php');
        $controller = file_get_contents(__DIR__.'/../../app/Http/Controllers/Admin/BusinessSettingsController.php');
        preg_match('/public function send_mail.*?public function mail_diagnostics/s', $controller, $match);
        $sendMail = $match[0] ?? '';

        $this->assertStringContainsString('type="password"', $view);
        $this->assertStringNotContainsString("\$data['password'] ?? ''", $view);
        $this->assertStringNotContainsString('getMessage()', $sendMail);
        $this->assertStringContainsString("'error_code' => \$category", $sendMail);
    }
}
