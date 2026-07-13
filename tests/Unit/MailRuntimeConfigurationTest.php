<?php

namespace Tests\Unit;

use App\Services\MailRuntimeConfiguration;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class MailRuntimeConfigurationTest extends TestCase
{
    public function test_it_encrypts_and_decrypts_smtp_passwords(): void
    {
        $service = app(MailRuntimeConfiguration::class);
        $encrypted = $service->encryptPassword('test-secret-value');

        $this->assertStringStartsWith(MailRuntimeConfiguration::ENCRYPTED_PREFIX, $encrypted);
        $this->assertSame('test-secret-value', $service->decryptPassword($encrypted));
        $this->assertStringNotContainsString('test-secret-value', $encrypted);
    }

    public function test_it_maps_saved_admin_values_into_runtime_configuration(): void
    {
        $service = app(MailRuntimeConfiguration::class);
        $configuration = $service->apply([
            'status' => 1,
            'driver' => 'smtp',
            'host' => 'smtp.example.test',
            'port' => 465,
            'encryption' => 'ssl',
            'username' => 'mailer@example.test',
            'password' => $service->encryptPassword('secret'),
            'email_id' => 'from@example.test',
            'name' => 'Urban Goodz',
            'timeout' => 20,
            'auth_mode' => 'login',
            'local_domain' => 'example.test',
        ]);

        $this->assertSame('smtp', Config::get('mail.default'));
        $this->assertSame('smtp.example.test', Config::get('mail.mailers.smtp.host'));
        $this->assertSame(465, Config::get('mail.mailers.smtp.port'));
        $this->assertSame('secret', Config::get('mail.mailers.smtp.password'));
        $this->assertSame('from@example.test', Config::get('mail.from.address'));
        $this->assertSame(20, $configuration['timeout']);
    }

    public function test_it_classifies_authentication_errors_without_returning_the_secret(): void
    {
        $service = app(MailRuntimeConfiguration::class);
        $exception = new \RuntimeException('535 Authentication credentials invalid for secret-value');

        $this->assertSame('authentication_failure', $service->classify($exception));
        $this->assertStringNotContainsString('secret-value', $service->classify($exception));
    }

    public function test_redacted_diagnostics_never_return_the_password(): void
    {
        $source = file_get_contents(__DIR__.'/../../app/Services/MailRuntimeConfiguration.php');

        $this->assertStringContainsString("'redacted_length'", $source);
        $this->assertStringContainsString("'decryption'", $source);
        $this->assertStringNotContainsString("'password' => \$password", $source);
    }
}
