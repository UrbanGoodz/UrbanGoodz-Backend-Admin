<?php

namespace App\Exceptions;

use App\Services\UrbanGoodz\DriverCompensationContext;
use RuntimeException;

/**
 * Raised when no driver compensation policy can be resolved.
 *
 * Until policies are configured the platform pays a caller-supplied fallback
 * amount and logs a warning, which is how drivers came to be paid the delivery
 * charge by default with nobody noticing. Callers that opt into
 * `resolveOrFail()` get this instead.
 */
class DriverCompensationConfigurationException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?DriverCompensationContext $context = null,
        public readonly ?string $reason = null,
    ) {
        parent::__construct($message);
    }

    public static function missing(DriverCompensationContext $context): self
    {
        return new self(
            sprintf(
                'No driver compensation policy resolved for policy_type=%s zone=%s business=%s route=%s. '
                . 'Configure a rule in the Master Admin Driver Pricing and Compensation centre.',
                $context->policyType,
                $context->zoneId ?? '-',
                $context->businessClientId ?? '-',
                $context->routeId ?? '-'
            ),
            $context,
            'missing_configuration'
        );
    }
}
