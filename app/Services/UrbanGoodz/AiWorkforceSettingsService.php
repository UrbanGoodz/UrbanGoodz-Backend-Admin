<?php

namespace App\Services\UrbanGoodz;

use App\Models\AiCopilotSetting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

class AiWorkforceSettingsService
{
    private const FIELDS = [
        'enabled' => ['key' => 'ai_workforce_enabled', 'path' => 'enabled', 'type' => 'bool'],
        'global_kill_switch' => ['key' => 'ai_workforce_global_kill_switch', 'path' => 'global_kill_switch', 'type' => 'bool'],
        'demand_min_requests' => ['key' => 'ai_workforce_demand_min_requests', 'path' => 'demand_thresholds.min_requests', 'type' => 'int'],
        'demand_min_customers' => ['key' => 'ai_workforce_demand_min_customers', 'path' => 'demand_thresholds.min_unique_customers', 'type' => 'int'],
        'demand_window_days' => ['key' => 'ai_workforce_demand_window_days', 'path' => 'demand_thresholds.rolling_window_days', 'type' => 'int'],
        'demand_cooldown_days' => ['key' => 'ai_workforce_demand_cooldown_days', 'path' => 'demand_thresholds.cooldown_days', 'type' => 'int'],
        'sender_name' => ['key' => 'ai_workforce_sender_name', 'path' => 'outreach.sender_name', 'type' => 'string'],
        'sender_email' => ['key' => 'ai_workforce_sender_email', 'path' => 'outreach.sender_email', 'type' => 'string'],
        'max_attempts' => ['key' => 'ai_workforce_max_attempts', 'path' => 'outreach.max_contact_attempts', 'type' => 'int'],
        'hours_start' => ['key' => 'ai_workforce_hours_start', 'path' => 'outreach.sending_hours_start', 'type' => 'string'],
        'hours_end' => ['key' => 'ai_workforce_hours_end', 'path' => 'outreach.sending_hours_end', 'type' => 'string'],
    ];

    public function all(): array
    {
        $settings = (array) Config::get('urban_goodz.ai_workforce', []);

        if (!Schema::hasTable('ai_copilot_settings')) {
            return $settings;
        }

        $stored = AiCopilotSetting::whereIn(
            'key',
            array_column(self::FIELDS, 'key')
        )->pluck('value', 'key');

        foreach (self::FIELDS as $definition) {
            if (!$stored->has($definition['key'])) {
                continue;
            }

            $value = $this->cast($stored->get($definition['key']), $definition['type']);

            // An environment-level emergency stop remains authoritative. A saved
            // Admin setting may enable the stop, but it can never disable env safety.
            if ($definition['path'] === 'global_kill_switch') {
                $value = (bool) data_get($settings, 'global_kill_switch', false) || $value;
            }

            data_set($settings, $definition['path'], $value);
        }

        return $settings;
    }

    public function save(array $values): array
    {
        foreach (self::FIELDS as $field => $definition) {
            if (!array_key_exists($field, $values)) {
                continue;
            }

            AiCopilotSetting::updateOrCreate(
                ['key' => $definition['key']],
                ['value' => $this->serialize($values[$field], $definition['type'])]
            );
        }

        return $this->apply();
    }

    public function apply(): array
    {
        $settings = $this->all();
        Config::set('urban_goodz.ai_workforce', $settings);

        return $settings;
    }

    private function cast(mixed $value, string $type): mixed
    {
        return match ($type) {
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'int' => (int) $value,
            default => (string) $value,
        };
    }

    private function serialize(mixed $value, string $type): string
    {
        return match ($type) {
            'bool' => $value ? '1' : '0',
            'int' => (string) ((int) $value),
            default => (string) ($value ?? ''),
        };
    }
}
