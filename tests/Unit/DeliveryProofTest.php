<?php

namespace Tests\Unit;

use App\Support\DeliveryProof;
use PHPUnit\Framework\TestCase;

class DeliveryProofTest extends TestCase
{
    public function test_placeholders_and_blanks_are_not_proof(): void
    {
        foreach ([null, '', '   ', '/images/mock_photo.png', 'MOCK_SIGNATURE', 'Photo (Simulated)', 'placeholder.jpg'] as $v) {
            $this->assertNull(DeliveryProof::clean($v), var_export($v, true));
        }
    }

    public function test_real_values_pass_through_trimmed(): void
    {
        $this->assertSame('uploads/dropoff/abc123.jpg', DeliveryProof::clean(' uploads/dropoff/abc123.jpg '));
        $this->assertSame('data:image/png;base64,AAAA', DeliveryProof::clean('data:image/png;base64,AAAA'));
    }
}
