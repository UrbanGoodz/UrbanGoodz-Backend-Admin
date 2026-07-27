<?php

namespace App\Http\Requests\Admin;

use App\Models\UrbanGoodzCompensationRule;
use App\Services\UrbanGoodz\Compensation\ComponentCalculator;
use App\Services\UrbanGoodz\Compensation\Money;
use App\Support\Compensation\ComponentCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CompensationRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Permission is enforced in the controller so an unwired route still fails closed.
        return true;
    }

    public function rules(): array
    {
        return [
            'rule_key' => ['required', 'string', 'max:191', 'regex:/^[a-z0-9._-]+$/i'],
            'name' => ['required', 'string', 'max:191'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'work_type' => ['required', 'string', 'in:' . implode(',', UrbanGoodzCompensationRule::WORK_TYPES)],
            'service_scope' => ['nullable', 'string', 'max:64'],
            'vehicle_scope' => ['nullable', 'array'],
            'vehicle_scope.*' => ['string', 'in:' . implode(',', array_keys(ComponentCatalog::VEHICLES))],
            'market_scope' => ['nullable', 'array'],
            'market_scope.*' => ['string', 'max:64'],
            'zone_id' => ['nullable', 'integer', 'min:1'],
            'priority' => ['required', 'integer', 'between:-1000,1000'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after:effective_from'],
            'rounding_mode' => ['required', 'string', 'in:' . implode(',', Money::modes())],
            'minimum_payout_cents' => ['nullable', 'integer', 'min:0'],
            'maximum_payout_cents' => ['nullable', 'integer', 'min:0'],
            'components' => ['required', 'array', 'min:1'],
            'splits' => ['nullable', 'array'],
            'splits.basis' => ['nullable', 'string', 'in:customer_charge,linehaul,delivery_charge'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $components = $this->input('components', []);

            // Reject unknown component keys rather than letting the engine throw
            // at payout time.
            foreach (array_keys($components) as $key) {
                if (!in_array($key, ComponentCalculator::SUPPORTED, true)) {
                    $validator->errors()->add("components.{$key}", "Unsupported component [{$key}].");
                }
            }

            // Every numeric component field must be non-negative.
            foreach ($components as $key => $config) {
                if (!is_array($config)) {
                    $validator->errors()->add("components.{$key}", 'Component configuration must be an object.');
                    continue;
                }

                foreach ($config as $field => $value) {
                    if (is_numeric($value) && (float) $value < 0) {
                        $validator->errors()->add(
                            "components.{$key}.{$field}",
                            'Negative values are not permitted.'
                        );
                    }
                }
            }

            $min = $this->input('minimum_payout_cents');
            $max = $this->input('maximum_payout_cents');

            if ($min !== null && $max !== null && $min !== '' && $max !== '' && (int) $min > (int) $max) {
                $validator->errors()->add('minimum_payout_cents', 'Minimum payout may not exceed maximum payout.');
            }

            // Split percentages: individually valid and collectively sane.
            $splits = $this->input('splits', []);
            $totalPercent = 0.0;

            foreach (ComponentCatalog::splitParties() as $party => $label) {
                $partyConfig = $splits[$party] ?? null;

                if (!is_array($partyConfig)) {
                    continue;
                }

                if (isset($partyConfig['percent']) && $partyConfig['percent'] !== '') {
                    $percent = (float) $partyConfig['percent'];

                    if ($percent < 0) {
                        $validator->errors()->add("splits.{$party}.percent", 'Percentage may not be negative.');
                    }

                    if ($percent > 100) {
                        $validator->errors()->add("splits.{$party}.percent", 'Percentage may not exceed 100.');
                    }

                    $totalPercent += $percent;
                }

                if (isset($partyConfig['fixed_cents']) && $partyConfig['fixed_cents'] !== '' && (int) $partyConfig['fixed_cents'] < 0) {
                    $validator->errors()->add("splits.{$party}.fixed_cents", 'Fixed amount may not be negative.');
                }
            }

            if ($totalPercent > 100) {
                $validator->errors()->add('splits', 'Split percentages total ' . $totalPercent . '%, which exceeds 100%.');
            }
        });
    }

    /**
     * Normalised attributes ready for RuleAdministrator.
     */
    public function ruleAttributes(): array
    {
        $data = $this->validated();

        foreach (['minimum_payout_cents', 'maximum_payout_cents', 'zone_id'] as $nullable) {
            if (($data[$nullable] ?? null) === '') {
                $data[$nullable] = null;
            }
        }

        $data['components'] = $this->cleanComponents($data['components'] ?? []);
        $data['splits'] = $data['splits'] ?? [];

        return $data;
    }

    private function cleanComponents(array $components): array
    {
        $clean = [];

        foreach ($components as $key => $config) {
            $fields = [];

            foreach ((array) $config as $field => $value) {
                if ($value === '' || $value === null) {
                    continue;
                }

                $fields[$field] = is_numeric($value) ? $value + 0 : $value;
            }

            if ($fields !== []) {
                $clean[$key] = $fields;
            }
        }

        return $clean;
    }
}
