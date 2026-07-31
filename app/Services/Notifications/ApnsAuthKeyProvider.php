<?php

namespace App\Services\Notifications;

use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Token-based (.p8 auth key) authentication for Apple Push Notification service.
 *
 * Replaces the two push certificates that expired on 2021-12-18. A .p8 auth key
 * does not expire, covers every bundle ID in the Apple Developer team, and works
 * against both the sandbox and production APNs hosts.
 *
 * This class only mints and validates the ES256 provider token described in
 * Apple's "Establishing a token-based connection to APNs". It never reads or
 * accepts a .p12/.pem certificate, and it never logs or returns the key itself.
 */
class ApnsAuthKeyProvider
{
    public const MODE_AUTH_KEY = 'auth_key';

    public const MODE_UNCONFIGURED = 'unconfigured';

    private const CACHE_KEY_PREFIX = 'apns:provider_token:';

    /**
     * True only when key id, team id, bundle id and a readable .p8 are all present.
     */
    public function isConfigured(): bool
    {
        return $this->configurationProblems() === [];
    }

    public function mode(): string
    {
        return $this->isConfigured() ? self::MODE_AUTH_KEY : self::MODE_UNCONFIGURED;
    }

    /**
     * Operator-facing readiness report. Contains identifiers and booleans only -
     * never the private key, never the minted token.
     *
     * @return array{
     *     mode: string,
     *     configured: bool,
     *     auth_type: string,
     *     key_id_present: bool,
     *     team_id_present: bool,
     *     bundle_id: string|null,
     *     auth_key_readable: bool,
     *     environment: string,
     *     endpoint: string,
     *     direct_send_enabled: bool,
     *     problems: array<int, string>
     * }
     */
    public function status(): array
    {
        $problems = $this->configurationProblems();

        return [
            'mode' => $problems === [] ? self::MODE_AUTH_KEY : self::MODE_UNCONFIGURED,
            'configured' => $problems === [],
            // Certificate auth is intentionally not supported any more.
            'auth_type' => 'p8_auth_key',
            'key_id_present' => $this->keyId() !== null,
            'team_id_present' => $this->teamId() !== null,
            'bundle_id' => $this->bundleId(),
            'auth_key_readable' => $this->authKeyContent() !== null,
            'environment' => $this->environment(),
            'endpoint' => $this->endpoint(),
            'direct_send_enabled' => (bool) config('apns.direct_send_enabled', false),
            'problems' => $problems,
        ];
    }

    /**
     * @return array<int, string> machine-readable, credential-free problem codes
     */
    public function configurationProblems(): array
    {
        $problems = [];

        if ($this->keyId() === null) {
            $problems[] = 'apns_key_id_missing';
        }
        if ($this->teamId() === null) {
            $problems[] = 'apns_team_id_missing';
        }
        if ($this->bundleId() === null) {
            $problems[] = 'apns_bundle_id_missing';
        }
        if ($this->authKeyContent() === null) {
            $problems[] = 'apns_auth_key_unreadable';
        }

        return $problems;
    }

    /**
     * Mint (or reuse) the ES256 provider token sent as `authorization: bearer <jwt>`.
     *
     * @throws RuntimeException when the configuration is incomplete or the key
     *                          cannot sign - the message never contains the key.
     */
    public function providerToken(): string
    {
        $problems = $this->configurationProblems();
        if ($problems !== []) {
            throw new RuntimeException('APNs auth key is not configured: '.implode(',', $problems));
        }

        $keyId = (string) $this->keyId();
        $ttl = max(60, (int) config('apns.token_ttl_seconds', 3000));

        return Cache::remember(
            self::CACHE_KEY_PREFIX.$keyId,
            $ttl,
            fn (): string => $this->mintProviderToken()
        );
    }

    /**
     * Headers for a direct APNs request. Kept separate from providerToken() so
     * the topic (bundle id) can be checked against the allowed set.
     *
     * @return array<string, string>
     */
    public function requestHeaders(?string $bundleId = null, string $pushType = 'alert', string $priority = '10'): array
    {
        $topic = $bundleId !== null ? trim($bundleId) : (string) $this->bundleId();

        if (! $this->isAllowedTopic($topic)) {
            throw new RuntimeException('APNs topic is not an allowed bundle id.');
        }

        return [
            'authorization' => 'bearer '.$this->providerToken(),
            'apns-topic' => $topic,
            'apns-push-type' => $pushType,
            'apns-priority' => $priority,
        ];
    }

    public function isAllowedTopic(string $bundleId): bool
    {
        $bundleId = trim($bundleId);
        if ($bundleId === '') {
            return false;
        }

        $allowed = array_filter(array_merge(
            [(string) $this->bundleId()],
            (array) config('apns.additional_bundle_ids', [])
        ));

        return in_array($bundleId, array_map('trim', $allowed), true);
    }

    public function endpoint(): string
    {
        $endpoints = (array) config('apns.endpoints', []);

        return (string) ($endpoints[$this->environment()] ?? $endpoints['production'] ?? '');
    }

    public function environment(): string
    {
        $env = strtolower(trim((string) config('apns.environment', 'production')));

        return $env === 'sandbox' ? 'sandbox' : 'production';
    }

    /**
     * Build the ES256 JWT. Header: alg ES256 + kid. Claims: iss (team id) + iat.
     * Apple requires the signature in raw R||S form, not the DER sequence
     * openssl_sign() produces, so it is converted here.
     */
    private function mintProviderToken(): string
    {
        $header = $this->base64UrlEncode((string) json_encode([
            'alg' => 'ES256',
            'kid' => $this->keyId(),
        ]));

        $claims = $this->base64UrlEncode((string) json_encode([
            'iss' => $this->teamId(),
            'iat' => time(),
        ]));

        $signingInput = $header.'.'.$claims;

        $privateKey = openssl_pkey_get_private((string) $this->authKeyContent());
        if ($privateKey === false) {
            throw new RuntimeException('APNs auth key could not be parsed as a private key.');
        }

        $derSignature = '';
        if (! openssl_sign($signingInput, $derSignature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('APNs provider token could not be signed.');
        }

        return $signingInput.'.'.$this->base64UrlEncode($this->derToRawSignature($derSignature));
    }

    /**
     * Convert a DER-encoded ECDSA signature to the fixed-width R||S form
     * (32 bytes each for P-256) that JWS ES256 requires.
     */
    private function derToRawSignature(string $der): string
    {
        $offset = 0;
        if (($der[$offset] ?? '') !== "\x30") {
            throw new RuntimeException('Unexpected ECDSA signature encoding.');
        }
        $offset++;

        // Sequence length (short or long form) - value itself is not needed.
        $lengthByte = ord($der[$offset++]);
        if ($lengthByte > 0x80) {
            $offset += $lengthByte - 0x80;
        }

        $readInteger = function () use ($der, &$offset): string {
            if (($der[$offset] ?? '') !== "\x02") {
                throw new RuntimeException('Unexpected ECDSA signature encoding.');
            }
            $offset++;
            $length = ord($der[$offset++]);
            $value = substr($der, $offset, $length);
            $offset += $length;

            // Strip the leading zero DER adds to keep the integer positive.
            $value = ltrim($value, "\x00");

            return str_pad($value, 32, "\x00", STR_PAD_LEFT);
        };

        return $readInteger().$readInteger();
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function keyId(): ?string
    {
        return $this->nonEmpty(config('apns.key_id'));
    }

    private function teamId(): ?string
    {
        return $this->nonEmpty(config('apns.team_id'));
    }

    private function bundleId(): ?string
    {
        return $this->nonEmpty(config('apns.bundle_id'));
    }

    /**
     * The .p8 body, from APNS_AUTH_KEY_CONTENT or the file at APNS_AUTH_KEY_PATH.
     * A certificate (.p12/.pem cert) is explicitly rejected.
     */
    private function authKeyContent(): ?string
    {
        $inline = $this->nonEmpty(config('apns.auth_key_content'));
        if ($inline !== null) {
            return $this->rejectCertificate($inline);
        }

        $path = $this->nonEmpty(config('apns.auth_key_path'));
        if ($path === null || ! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        return $contents === false ? null : $this->rejectCertificate($contents);
    }

    private function rejectCertificate(string $contents): ?string
    {
        if (str_contains($contents, 'BEGIN CERTIFICATE')) {
            // Certificate auth is retired; do not silently accept one.
            return null;
        }

        return str_contains($contents, 'BEGIN PRIVATE KEY') || str_contains($contents, 'BEGIN EC PRIVATE KEY')
            ? $contents
            : null;
    }

    private function nonEmpty(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
