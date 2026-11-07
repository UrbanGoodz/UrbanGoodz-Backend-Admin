<?php

namespace App\Services;

use App\CentralLogics\Helpers;
use InvalidArgumentException;

/**
 * Prices an Order Anywhere request from what the item actually costs to source.
 *
 *   item_subtotal  what the driver pays the merchant (the purchase-card budget)
 *   service_fee    Urban Goodz markup on the item  = item_subtotal * markup%
 *   delivery_fee   delivery charge
 *   tax            sales tax on the item
 *   quote_amount   the single figure the customer pays
 *
 * Before this, an admin typed the final total by hand, so the markup was a mental
 * sum that nothing recorded or checked. The markup rate is configuration, never a
 * silent default: with none set the platform would quietly sell at cost.
 */
class OrderAnywhereQuotePricing
{
    public static function markupPercent(): ?float
    {
        $setting = Helpers::get_business_settings('order_anywhere_markup_percent');
        $value = is_numeric($setting) ? $setting : config('urban_goodz_payments.order_anywhere.markup_percent');

        return is_numeric($value) ? (float) $value : null;
    }

    /** @return array{item_subtotal: float, service_fee: float, delivery_fee: float, tax: float, quote_amount: float, markup_percent: float} */
    public static function price(float $itemCost, ?float $deliveryFee = null, ?float $markupPercent = null, ?float $tax = null): array
    {
        if ($itemCost <= 0) {
            throw new InvalidArgumentException('The sourced item cost must be greater than zero.');
        }

        $markup = $markupPercent ?? self::markupPercent();
        if ($markup === null || $markup < 0) {
            throw new InvalidArgumentException('No Order Anywhere markup is configured. Set order_anywhere_markup_percent or enter the markup explicitly.');
        }

        $delivery = $deliveryFee ?? (float) (Helpers::get_business_settings('order_anywhere_estimated_delivery_fee') ?: 0);
        $taxRate = Helpers::get_business_settings('order_anywhere_tax_percent');
        $taxAmount = $tax ?? (is_numeric($taxRate) ? round($itemCost * (float) $taxRate / 100, 2) : 0.0);

        $itemCost = round($itemCost, 2);
        $serviceFee = round($itemCost * $markup / 100, 2);

        return [
            'item_subtotal' => $itemCost,
            'service_fee' => $serviceFee,
            'delivery_fee' => round($delivery, 2),
            'tax' => round($taxAmount, 2),
            'quote_amount' => round($itemCost + $serviceFee + $delivery + $taxAmount, 2),
            'markup_percent' => $markup,
        ];
    }
}
