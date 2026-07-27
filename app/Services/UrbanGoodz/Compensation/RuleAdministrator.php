<?php

namespace App\Services\UrbanGoodz\Compensation;

use App\Models\UrbanGoodzCompensationRule;
use App\Models\UrbanGoodzCompensationRuleAudit;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use RuntimeException;

/**
 * Lifecycle management for compensation rules.
 *
 * Published rules are never edited in place. Changing a published rule creates a
 * new draft version, so the exact rule that produced a historical payout stays
 * readable forever. Every transition is written to the audit table.
 */
final class RuleAdministrator
{
    public function createDraft(array $attributes, ?int $actorId = null): UrbanGoodzCompensationRule
    {
        $this->validate($attributes);

        $key = $attributes['rule_key'];
        $nextVersion = (int) UrbanGoodzCompensationRule::where('rule_key', $key)->max('version') + 1;

        $rule = UrbanGoodzCompensationRule::create(array_merge($attributes, [
            'version' => $nextVersion,
            'state' => UrbanGoodzCompensationRule::STATE_DRAFT,
            'is_active' => $attributes['is_active'] ?? true,
            'created_by' => $actorId,
        ]));

        $this->audit($rule, 'created', null, $rule->getAttributes(), $actorId, "Draft v{$nextVersion} created");

        return $rule;
    }

    /**
     * Revise a rule. If the source is published, a new draft version is created
     * rather than mutating the published rule.
     */
    public function revise(UrbanGoodzCompensationRule $rule, array $changes, ?int $actorId = null): UrbanGoodzCompensationRule
    {
        if ($rule->state === UrbanGoodzCompensationRule::STATE_PUBLISHED) {
            $attributes = array_merge(
                collect($rule->getAttributes())
                    ->except(['id', 'version', 'state', 'published_by', 'published_at', 'created_at', 'updated_at'])
                    ->toArray(),
                $changes
            );

            // Casted attributes come back as JSON strings from getAttributes().
            foreach (['components', 'splits', 'vehicle_scope', 'market_scope'] as $jsonField) {
                if (isset($attributes[$jsonField]) && is_string($attributes[$jsonField])) {
                    $attributes[$jsonField] = json_decode($attributes[$jsonField], true);
                }
            }

            return $this->createDraft($attributes, $actorId);
        }

        if ($rule->state === UrbanGoodzCompensationRule::STATE_ARCHIVED) {
            throw new RuntimeException('Archived rules may not be revised.');
        }

        $old = $rule->getAttributes();
        $rule->fill($changes);
        $this->validate(array_merge($rule->getAttributes(), $changes), $rule);
        $rule->save();

        $this->audit($rule, 'updated', $old, $rule->getAttributes(), $actorId, 'Draft revised');

        return $rule;
    }

    public function publish(UrbanGoodzCompensationRule $rule, ?int $actorId = null): UrbanGoodzCompensationRule
    {
        if ($rule->state === UrbanGoodzCompensationRule::STATE_PUBLISHED) {
            throw new RuntimeException('Rule is already published.');
        }

        if ($rule->state === UrbanGoodzCompensationRule::STATE_ARCHIVED) {
            throw new RuntimeException('Archived rules may not be published.');
        }

        $this->validate($rule->getAttributes(), $rule);

        $old = $rule->getAttributes();

        // Supersede the currently published version of the same key.
        UrbanGoodzCompensationRule::where('rule_key', $rule->rule_key)
            ->where('id', '!=', $rule->id)
            ->where('state', UrbanGoodzCompensationRule::STATE_PUBLISHED)
            ->get()
            ->each(function (UrbanGoodzCompensationRule $previous) use ($actorId) {
                $previousOld = $previous->getAttributes();
                $previous->state = UrbanGoodzCompensationRule::STATE_ARCHIVED;
                $previous->save();
                $this->audit($previous, 'archived', $previousOld, $previous->getAttributes(), $actorId, 'Superseded by newer version');
            });

        $rule->state = UrbanGoodzCompensationRule::STATE_PUBLISHED;
        $rule->published_by = $actorId;
        $rule->published_at = Carbon::now();
        $rule->save();

        $this->audit($rule, 'published', $old, $rule->getAttributes(), $actorId, "Published v{$rule->version}");

        return $rule;
    }

    public function setActive(UrbanGoodzCompensationRule $rule, bool $active, ?int $actorId = null): UrbanGoodzCompensationRule
    {
        $old = $rule->getAttributes();
        $rule->is_active = $active;
        $rule->save();

        $this->audit(
            $rule,
            $active ? 'enabled' : 'disabled',
            $old,
            $rule->getAttributes(),
            $actorId,
            $active ? 'Rule enabled' : 'Rule disabled'
        );

        return $rule;
    }

    public function archive(UrbanGoodzCompensationRule $rule, ?int $actorId = null): UrbanGoodzCompensationRule
    {
        $old = $rule->getAttributes();
        $rule->state = UrbanGoodzCompensationRule::STATE_ARCHIVED;
        $rule->save();

        $this->audit($rule, 'archived', $old, $rule->getAttributes(), $actorId, 'Rule archived');

        return $rule;
    }

    public function history(string $ruleKey): array
    {
        return UrbanGoodzCompensationRuleAudit::where('rule_key', $ruleKey)
            ->orderByDesc('id')
            ->get()
            ->all();
    }

    private function validate(array $attributes, ?UrbanGoodzCompensationRule $rule = null): void
    {
        $workType = $attributes['work_type'] ?? $rule?->work_type;

        if (!in_array($workType, UrbanGoodzCompensationRule::WORK_TYPES, true)) {
            throw new InvalidArgumentException("Unsupported work_type [{$workType}].");
        }

        $components = $attributes['components'] ?? $rule?->components ?? [];

        if (is_string($components)) {
            $components = json_decode($components, true) ?: [];
        }

        if (empty($components)) {
            throw new InvalidArgumentException('A compensation rule must define at least one component.');
        }

        ComponentCalculator::assertSupported($components);

        $mode = $attributes['rounding_mode'] ?? $rule?->rounding_mode ?? Money::HALF_UP;

        if (!in_array($mode, Money::modes(), true)) {
            throw new InvalidArgumentException("Unsupported rounding_mode [{$mode}].");
        }

        foreach (['minimum_payout_cents', 'maximum_payout_cents'] as $field) {
            $value = $attributes[$field] ?? $rule?->{$field};
            if ($value !== null && (int) $value < 0) {
                throw new InvalidArgumentException("[{$field}] may not be negative.");
            }
        }

        $min = $attributes['minimum_payout_cents'] ?? $rule?->minimum_payout_cents;
        $max = $attributes['maximum_payout_cents'] ?? $rule?->maximum_payout_cents;

        if ($min !== null && $max !== null && (int) $min > (int) $max) {
            throw new InvalidArgumentException('minimum_payout_cents may not exceed maximum_payout_cents.');
        }
    }

    private function audit(
        UrbanGoodzCompensationRule $rule,
        string $event,
        ?array $old,
        ?array $new,
        ?int $actorId,
        ?string $description
    ): void {
        UrbanGoodzCompensationRuleAudit::create([
            'rule_id' => $rule->id,
            'rule_key' => $rule->rule_key,
            'version' => $rule->version,
            'event' => $event,
            'old_values' => $old,
            'new_values' => $new,
            'actor_id' => $actorId,
            'actor_type' => 'admin',
            'description' => $description,
        ]);
    }
}
