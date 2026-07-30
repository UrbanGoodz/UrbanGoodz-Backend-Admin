<?php

namespace App\Exceptions;

use App\Services\UrbanGoodz\CommissionContext;
use RuntimeException;

/**
 * Raised when no valid commission configuration can be resolved, or when the
 * rule that resolved is not usable.
 *
 * The platform must fail safe here rather than guess. Silently falling back is
 * how a compensation engine with zero configured rules kept paying out for
 * weeks without anyone noticing.
 */
class CommissionConfigurationException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?CommissionContext $context = null,
        public readonly ?string $reason = null,
    ) {
        parent::__construct($message);
    }

    public static function missing(CommissionContext $context): self
    {
        return new self(
            sprintf(
                'No commission configuration resolved for transaction_type=%s partner=%s:%s module=%s. '
                . 'Settlement halted; configure a rule in the Master Admin Financial Control Center.',
                $context->transactionType,
                $context->partnerType ?? '-',
                $context->partnerId ?? '-',
                $context->moduleId ?? '-'
            ),
            $context,
            'missing_configuration'
        );
    }

    public static function invalidRate(CommissionContext $context, string $detail): self
    {
        return new self(
            'Commission rule is not usable: ' . $detail,
            $context,
            'invalid_rate'
        );
    }
}
