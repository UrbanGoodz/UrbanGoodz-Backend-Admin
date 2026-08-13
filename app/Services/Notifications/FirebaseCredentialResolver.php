<?php

namespace App\Services\Notifications;

use App\Models\BusinessSetting;

/**
 * Decides which Firebase Cloud Messaging credential mode is authoritative.
 *
 * FCM v1 (service account -> OAuth2 bearer token) is the ONLY supported send
 * path. The legacy "server key" API (https://fcm.googleapis.com/fcm/send) was
 * decommissioned by Google on 2024-06-20; a stored legacy key must never be
 * promoted to a send path, and when a v1 service account exists the legacy key
 * is explicitly ignored rather than used as a fallback.
 */
class FirebaseCredentialResolver
{
    public const MODE_V1 = 'v1';

    public const MODE_LEGACY_ONLY = 'legacy_only';

    public const MODE_UNCONFIGURED = 'unconfigured';

    /** The only endpoint template this platform is allowed to post to. */
    public const V1_ENDPOINT_TEMPLATE = 'https://fcm.googleapis.com/v1/projects/%s/messages:send';

    /** Retired 2024-06-20. Present only so tests can assert we never call it. */
    public const LEGACY_ENDPOINT = 'https://fcm.googleapis.com/fcm/send';

    /** Business settings keys. */
    public const V1_SETTING_KEY = 'push_notification_service_file_content';

    public const LEGACY_SETTING_KEY = 'push_notification_key';

    private const REQUIRED_SERVICE_ACCOUNT_FIELDS = ['project_id', 'client_email', 'private_key'];

    /**
     * Pure precedence decision. No database, no network - unit testable.
     *
     * @param  array<string, mixed>|null  $serviceAccount  decoded FCM v1 service account JSON
     * @param  string|null  $legacyServerKey  a stored legacy FCM server key, if any
     * @return array{
     *     mode: string,
     *     can_send: bool,
     *     project_id: string|null,
     *     endpoint: string|null,
     *     legacy_server_key_present: bool,
     *     legacy_server_key_ignored: bool,
     *     reason: string|null
     * }
     */
    public static function decide(?array $serviceAccount, ?string $legacyServerKey = null): array
    {
        $legacyPresent = is_string($legacyServerKey) && trim($legacyServerKey) !== '';
        $projectId = self::validServiceAccountProjectId($serviceAccount);

        if ($projectId !== null) {
            return [
                'mode' => self::MODE_V1,
                'can_send' => true,
                'project_id' => $projectId,
                'endpoint' => sprintf(self::V1_ENDPOINT_TEMPLATE, $projectId),
                'legacy_server_key_present' => $legacyPresent,
                // v1 wins outright; a stored legacy key is dead weight, not a fallback.
                'legacy_server_key_ignored' => $legacyPresent,
                'reason' => null,
            ];
        }

        if ($legacyPresent) {
            return [
                'mode' => self::MODE_LEGACY_ONLY,
                'can_send' => false,
                'project_id' => null,
                'endpoint' => null,
                'legacy_server_key_present' => true,
                'legacy_server_key_ignored' => true,
                'reason' => 'legacy_server_key_is_not_a_send_path',
            ];
        }

        return [
            'mode' => self::MODE_UNCONFIGURED,
            'can_send' => false,
            'project_id' => null,
            'endpoint' => null,
            'legacy_server_key_present' => false,
            'legacy_server_key_ignored' => false,
            'reason' => 'fcm_v1_service_account_missing',
        ];
    }

    /**
     * Same decision, sourced from stored business settings.
     *
     * @return array<string, mixed>
     */
    public function resolve(): array
    {
        return self::decide($this->storedServiceAccount(), $this->storedLegacyServerKey());
    }

    public function mode(): string
    {
        return (string) $this->resolve()['mode'];
    }

    public function canSend(): bool
    {
        return (bool) $this->resolve()['can_send'];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function serviceAccount(): ?array
    {
        $account = $this->storedServiceAccount();

        return self::validServiceAccountProjectId($account) === null ? null : $account;
    }

    /**
     * @param  array<string, mixed>|null  $serviceAccount
     */
    private static function validServiceAccountProjectId(?array $serviceAccount): ?string
    {
        if (! is_array($serviceAccount)) {
            return null;
        }

        foreach (self::REQUIRED_SERVICE_ACCOUNT_FIELDS as $field) {
            $value = $serviceAccount[$field] ?? null;
            if (! is_string($value) || trim($value) === '') {
                return null;
            }
        }

        return trim((string) $serviceAccount['project_id']);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function storedServiceAccount(): ?array
    {
        $decoded = $this->decodedSetting(self::V1_SETTING_KEY);

        return is_array($decoded) ? $decoded : null;
    }

    private function storedLegacyServerKey(): ?string
    {
        $row = BusinessSetting::where('key', self::LEGACY_SETTING_KEY)->first();

        if (! $row || ! is_string($row->value)) {
            return null;
        }

        return trim($row->value) === '' ? null : $row->value;
    }

    private function decodedSetting(string $key): mixed
    {
        $row = BusinessSetting::where('key', $key)->first();

        if (! $row || ! is_string($row->value)) {
            return null;
        }

        return json_decode($row->value, true);
    }
}
