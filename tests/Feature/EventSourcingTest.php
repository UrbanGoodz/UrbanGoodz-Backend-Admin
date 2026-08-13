<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventSourcingTest extends TestCase
{
    use RefreshDatabase;

    public function test_sourcing()
    {
        $this->assertTrue(true);
    }
}
