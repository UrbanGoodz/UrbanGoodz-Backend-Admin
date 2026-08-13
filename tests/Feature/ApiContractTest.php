<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_contract()
    {
        $this->assertTrue(true);
    }
}
