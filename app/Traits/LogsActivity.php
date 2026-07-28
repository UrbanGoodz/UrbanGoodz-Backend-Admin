<?php

namespace App\Traits;

use App\Models\UrbanGoodzActivityLog;

trait LogsActivity
{
    public function activityLogs()
    {
        return $this->morphMany(UrbanGoodzActivityLog::class, 'loggable');
    }

    public function logActivity(string $event, ?string $description = null, array $oldValues = [], array $newValues = [], array $metadata = []): UrbanGoodzActivityLog
    {
        return $this->activityLogs()->create([
            'event' => $event,
            'description' => $description,
            'causer_type' => $this->resolveCauserType(),
            'causer_id' => $this->resolveCauserId(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'metadata' => $metadata,
        ]);
    }

    public function logStatusTransition(string $from, string $to, ?string $notes = null): UrbanGoodzActivityLog
    {
        return $this->logActivity(
            'status_transition',
            "Status changed from [{$from}] to [{$to}]",
            ['status' => $from],
            ['status' => $to],
            array_merge(['notes' => $notes], $this->resolveAuditContext())
        );
    }

    public function logPaymentEvent(string $event, float $amount, ?string $reference = null, array $extra = []): UrbanGoodzActivityLog
    {
        return $this->logActivity(
            "payment.{$event}",
            ucfirst($event) . " of \${$amount}",
            [],
            ['amount' => $amount, 'reference' => $reference],
            array_merge($extra, $this->resolveAuditContext())
        );
    }

    private function resolveCauserType(): ?string
    {
        if (auth('admin')->check()) {
            return 'App\\Models\\Admin';
        }
        if (auth('web')->check()) {
            return 'App\\Models\\User';
        }
        if (auth('delivery_men')->check()) {
            return 'App\\Models\\DeliveryMan';
        }

        return null;
    }

    private function resolveCauserId(): ?int
    {
        if (auth('admin')->check()) {
            return auth('admin')->id();
        }
        if (auth('web')->check()) {
            return auth('web')->id();
        }
        if (auth('delivery_men')->check()) {
            return auth('delivery_men')->id();
        }

        return null;
    }

    private function resolveAuditContext(): array
    {
        return [
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ];
    }
}
