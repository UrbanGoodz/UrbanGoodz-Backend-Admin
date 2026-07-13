<?php

namespace App\Services;

use App\Models\BusinessSetting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;
use Throwable;

class MailRuntimeConfiguration
{
    public const ENCRYPTED_PREFIX = 'encrypted:';

    public function stored(): ?array
    {
        $setting = BusinessSetting::where('key', 'mail_config')->first();

        if (! $setting?->value) {
            return null;
        }

        $value = json_decode($setting->value, true);

        if (! is_array($value)) {
            throw new RuntimeException('The saved mail configuration is not valid JSON.');
        }

        $value['_updated_at'] = $setting->updated_at?->toIso8601String();

        return $value;
    }

    public function applyStored(bool $requireActive = false): array
    {
        $configuration = $this->stored();

        if ($configuration === null) {
            throw new RuntimeException('Mail configuration is missing.');
        }

        if ($requireActive && ! filter_var($configuration['status'] ?? false, FILTER_VALIDATE_BOOL)) {
            throw new RuntimeException('Mail configuration is disabled.');
        }

        return $this->apply($configuration);
    }

    public function apply(array $configuration): array
    {
        $configuration = $this->normalize($configuration);
        $missing = $this->missingFields($configuration);

        if ($missing !== []) {
            throw new RuntimeException('Missing mail configuration fields: '.implode(', ', $missing));
        }

        $password = $this->decryptPassword((string) $configuration['password']);
        $mailer = $configuration['driver'];

        Config::set('mail.default', $mailer);
        Config::set("mail.mailers.{$mailer}.transport", 'smtp');
        Config::set("mail.mailers.{$mailer}.host", $configuration['host']);
        Config::set("mail.mailers.{$mailer}.port", $configuration['port']);
        Config::set("mail.mailers.{$mailer}.username", $configuration['username']);
        Config::set("mail.mailers.{$mailer}.password", $password);
        Config::set("mail.mailers.{$mailer}.encryption", $configuration['encryption']);
        Config::set("mail.mailers.{$mailer}.timeout", $configuration['timeout']);
        Config::set("mail.mailers.{$mailer}.auth_mode", $configuration['auth_mode']);
        Config::set("mail.mailers.{$mailer}.local_domain", $configuration['local_domain']);
        Config::set('mail.from.address', $configuration['email_id']);
        Config::set('mail.from.name', $configuration['name']);

        return $configuration;
    }

    public function normalize(array $configuration): array
    {
        $encryption = strtolower(trim((string) ($configuration['encryption'] ?? 'tls')));
        if (in_array($encryption, ['', 'none', 'null'], true)) {
            $encryption = null;
        } elseif ($encryption === 'starttls') {
            $encryption = 'tls';
        }

        return array_merge($configuration, [
            'status' => (int) ($configuration['status'] ?? 0),
            'driver' => strtolower(trim((string) ($configuration['driver'] ?? 'smtp'))) ?: 'smtp',
            'host' => trim((string) ($configuration['host'] ?? '')),
            'port' => (int) ($configuration['port'] ?? 0),
            'username' => trim((string) ($configuration['username'] ?? '')),
            'password' => (string) ($configuration['password'] ?? ''),
            'encryption' => $encryption,
            'email_id' => trim((string) ($configuration['email_id'] ?? $configuration['email'] ?? '')),
            'name' => trim((string) ($configuration['name'] ?? '')),
            'timeout' => max(1, (int) ($configuration['timeout'] ?? 30)),
            'auth_mode' => ($configuration['auth_mode'] ?? null) ?: null,
            'local_domain' => ($configuration['local_domain'] ?? null) ?: null,
        ]);
    }

    public function missingFields(array $configuration): array
    {
        $required = ['driver', 'host', 'port', 'username', 'password', 'email_id', 'name'];

        return array_values(array_filter($required, static fn (string $key): bool =>
            ! isset($configuration[$key]) || $configuration[$key] === '' || $configuration[$key] === 0
        ));
    }

    public function encryptPassword(string $password): string
    {
        if ($this->isEncrypted($password)) {
            return $password;
        }

        return self::ENCRYPTED_PREFIX.Crypt::encryptString($password);
    }

    public function decryptPassword(string $password): string
    {
        if (! $this->isEncrypted($password)) {
            return $password;
        }

        try {
            return Crypt::decryptString(substr($password, strlen(self::ENCRYPTED_PREFIX)));
        } catch (DecryptException $exception) {
            throw new RuntimeException('Saved SMTP credential decryption failed.', 0, $exception);
        }
    }

    public function isEncrypted(string $password): bool
    {
        return str_starts_with($password, self::ENCRYPTED_PREFIX);
    }

    public function classify(Throwable $exception): string
    {
        $message = strtolower($exception->getMessage());

        return match (true) {
            str_contains($message, 'decrypt') => 'decryption_failure',
            str_contains($message, 'missing mail configuration'),
            str_contains($message, 'configuration is disabled'),
            str_contains($message, 'configuration fields') => 'missing_configuration',
            str_contains($message, 'getaddrinfo'), str_contains($message, 'could not resolve') => 'dns_failure',
            str_contains($message, 'timed out'), str_contains($message, 'timeout') => 'connection_timeout',
            str_contains($message, 'connection refused') => 'connection_refused',
            str_contains($message, 'certificate') => 'certificate_validation_failure',
            str_contains($message, 'tls'), str_contains($message, 'ssl') => 'tls_negotiation_failure',
            str_contains($message, 'auth'), str_contains($message, '535') => 'authentication_failure',
            str_contains($message, 'rate'), str_contains($message, 'too many') => 'rate_limit',
            str_contains($message, 'recipient'), str_contains($message, '550') => 'recipient_rejection',
            default => 'provider_or_application_error',
        };
    }

    public function diagnostics(): array
    {
        $stored = $this->stored();
        if ($stored === null) {
            return [
                'configuration_loaded' => false,
                'missing_fields' => ['mail_config'],
                'password' => ['present' => false, 'redacted_length' => 0, 'decryption' => 'not_attempted'],
            ];
        }

        $configuration = $this->normalize($stored);
        $password = (string) ($configuration['password'] ?? '');
        $decryption = 'not_encrypted_legacy';
        $length = strlen($password);

        if ($this->isEncrypted($password)) {
            try {
                $length = strlen($this->decryptPassword($password));
                $decryption = 'success';
            } catch (Throwable) {
                $length = 0;
                $decryption = 'failure';
            }
        }

        return [
            'configuration_loaded' => $this->missingFields($configuration) === [],
            'active' => (bool) $configuration['status'],
            'missing_fields' => $this->missingFields($configuration),
            'mailer' => $configuration['driver'],
            'transport' => 'smtp',
            'host' => $configuration['host'],
            'port' => $configuration['port'],
            'encryption' => $configuration['encryption'] ?? 'none',
            'username' => $this->redactIdentity($configuration['username']),
            'from_address' => $this->redactIdentity($configuration['email_id']),
            'from_name' => $configuration['name'],
            'timeout' => $configuration['timeout'],
            'auth_mode' => $configuration['auth_mode'],
            'local_domain' => $configuration['local_domain'],
            'queue_connection' => config('queue.default'),
            'updated_at' => $stored['_updated_at'] ?? null,
            'password' => [
                'present' => $password !== '',
                'redacted_length' => $length,
                'decryption' => $decryption,
            ],
        ];
    }

    private function redactIdentity(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (str_contains($value, '@')) {
            [$local, $domain] = explode('@', $value, 2);
            return substr($local, 0, 1).str_repeat('*', max(3, strlen($local) - 1)).'@'.$domain;
        }

        return substr($value, 0, 1).str_repeat('*', max(3, strlen($value) - 2)).substr($value, -1);
    }
}
