<?php

namespace App\Services\UrbanGoodz\Compensation;

/**
 * The itemised result of a compensation calculation.
 *
 * Every cent that reaches a driver is traceable to a named line here, and every
 * line records the inputs that produced it. This is what makes "explain this
 * payout" answerable without re-running the engine.
 */
final class CompensationBreakdown
{
    /** @var array<int,array<string,mixed>> */
    private array $lines = [];

    private array $adjustments = [];

    public function __construct(
        public readonly ?string $ruleKey = null,
        public readonly ?int $ruleVersion = null,
        public readonly string $roundingMode = Money::HALF_UP,
    ) {
    }

    public function add(string $code, string $label, int $amountCents, array $inputs = []): self
    {
        $this->lines[] = [
            'code' => $code,
            'label' => $label,
            'amount_cents' => $amountCents,
            'amount' => Money::toDecimal($amountCents),
            'inputs' => $inputs,
        ];

        return $this;
    }

    public function note(string $code, string $label, array $detail = []): self
    {
        $this->adjustments[] = [
            'code' => $code,
            'label' => $label,
            'detail' => $detail,
        ];

        return $this;
    }

    /** @return array<int,array<string,mixed>> */
    public function lines(): array
    {
        return $this->lines;
    }

    public function adjustments(): array
    {
        return $this->adjustments;
    }

    public function subtotalCents(): int
    {
        return array_sum(array_column($this->lines, 'amount_cents'));
    }

    public function lineTotal(string $code): int
    {
        $total = 0;
        foreach ($this->lines as $line) {
            if ($line['code'] === $code) {
                $total += $line['amount_cents'];
            }
        }

        return $total;
    }

    public function hasLine(string $code): bool
    {
        foreach ($this->lines as $line) {
            if ($line['code'] === $code) {
                return true;
            }
        }

        return false;
    }

    public function toArray(): array
    {
        return [
            'rule_key' => $this->ruleKey,
            'rule_version' => $this->ruleVersion,
            'rounding_mode' => $this->roundingMode,
            'lines' => $this->lines,
            'adjustments' => $this->adjustments,
            'subtotal_cents' => $this->subtotalCents(),
            'subtotal' => Money::toDecimal($this->subtotalCents()),
        ];
    }

    /**
     * Human-readable derivation, one line per component.
     */
    public function explain(): string
    {
        $out = [];
        $out[] = sprintf(
            'Rule %s v%s (rounding: %s)',
            $this->ruleKey ?? 'none',
            $this->ruleVersion ?? '-',
            $this->roundingMode
        );

        foreach ($this->lines as $line) {
            $inputs = '';
            if (!empty($line['inputs'])) {
                $parts = [];
                foreach ($line['inputs'] as $k => $v) {
                    $parts[] = $k . '=' . (is_bool($v) ? ($v ? 'true' : 'false') : (string) $v);
                }
                $inputs = ' [' . implode(', ', $parts) . ']';
            }

            $out[] = sprintf('  %-28s %10s%s', $line['label'], Money::toDecimal($line['amount_cents']), $inputs);
        }

        foreach ($this->adjustments as $adjustment) {
            $detail = empty($adjustment['detail'])
                ? ''
                : ' (' . json_encode($adjustment['detail']) . ')';
            $out[] = sprintf('  %-28s %10s%s', $adjustment['label'], '--', $detail);
        }

        $out[] = sprintf('  %-28s %10s', 'SUBTOTAL', Money::toDecimal($this->subtotalCents()));

        return implode("\n", $out);
    }
}
