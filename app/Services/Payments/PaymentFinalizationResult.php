<?php

namespace App\Services\Payments;

use App\Models\OrderAnywhereRequest;

class PaymentFinalizationResult
{
    public function __construct(
        public readonly OrderAnywhereRequest $request,
        public readonly bool $alreadyProcessed,
        public readonly string $finalizationKey,
    ) {}
}
