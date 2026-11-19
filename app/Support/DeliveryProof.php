<?php

namespace App\Support;

/**
 * Delivery proof must be real. The driver app's proof dialog used to submit placeholder text
 * ("/images/mock_photo.png", "MOCK_SIGNATURE") when the fields were left blank; those are not proof.
 */
class DeliveryProof
{
    public static function clean(?string $value): ?string
    {
        $v = trim((string) $value);
        if ($v === '') {
            return null;
        }
        $lower = strtolower($v);
        foreach (['mock_signature', 'mock_photo', 'simulated', 'placeholder'] as $needle) {
            if (str_contains($lower, $needle)) {
                return null;
            }
        }

        return $v;
    }
}
