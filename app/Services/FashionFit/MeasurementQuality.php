<?php

namespace App\Services\FashionFit;

/**
 * Per-measurement quality descriptor.
 *
 * A single photo can be excellent for some measurements and poor for others,
 * so confidence is tracked at the measurement level, not just on the analysis.
 * Every measurement also carries explicit provenance so a value derived from a
 * real detected landmark is never presented with the same authority as one
 * derived from a guessed landmark.
 *
 * status values:
 *  - measured                      the value was computed from the pixels
 *  - needs_better_photo            the photo cannot support this measurement
 *  - customer_confirmation_required
 */
class MeasurementQuality
{
    public const PROVENANCE_DETECTED = 'detected';

    public const PROVENANCE_ESTIMATED = 'estimated';

    public const PROVENANCE_FALLBACK = 'fallback';

    public const PROVENANCE_UNKNOWN = 'unknown';

    public const PROVENANCE_DIRECT = 'direct';

    public const STATUS_MEASURED = 'measured';

    public const STATUS_NEEDS_BETTER_PHOTO = 'needs_better_photo';

    public const STATUS_CUSTOMER_CONFIRMATION_REQUIRED = 'customer_confirmation_required';

    /**
     * @param  float  $confidence  0..1 aggregate confidence for this value
     * @param  string  $provenance  worst-case provenance of the landmarks used
     * @param  array<string, string>  $landmarks  landmark name => provenance
     * @param  array<string, mixed>  $calculation  geometry/canvas used to derive it
     * @param  string  $status  one of the STATUS_* constants
     * @param  string  $reason  human-safe reason, only meaningful when not measured
     */
    public function __construct(
        public float $confidence,
        public string $provenance,
        public array $landmarks = [],
        public array $calculation = [],
        public string $status = self::STATUS_MEASURED,
        public string $reason = '',
        public bool $requiresConfirmation = false,
    ) {}

    public function measured(): bool
    {
        return $this->status === self::STATUS_MEASURED;
    }

    /** Whether any landmark this value depends on was guessed rather than seen. */
    public function hasEstimatedOrFallbackLandmark(): bool
    {
        foreach ($this->landmarks as $provenance) {
            if (in_array($provenance, [self::PROVENANCE_ESTIMATED, self::PROVENANCE_FALLBACK, self::PROVENANCE_UNKNOWN], true)) {
                return true;
            }
        }

        return false;
    }

    public function toArray(): array
    {
        return [
            'confidence' => $this->confidence,
            'provenance' => $this->provenance,
            'landmarks' => $this->landmarks,
            'calculation' => $this->calculation,
            'status' => $this->status,
            'reason' => $this->reason,
        ];
    }
}
