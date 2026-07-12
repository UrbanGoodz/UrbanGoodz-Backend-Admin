<?php

namespace Tests\Feature;

use Tests\TestCase;

class UrbanGoodzLoadBoardTest extends TestCase
{
    /**
     * Test that Dispatcher dashboard redirects unauthenticated visitors to login.
     */
    public function test_dispatcher_dashboard_redirects_unauthenticated()
    {
        $response = $this->get('/business/dispatcher/dashboard');
        $response->assertRedirect('/business/login');
    }

    /**
     * Test that Business Load Board index redirects unauthenticated visitors to login.
     */
    public function test_business_load_board_redirects_unauthenticated()
    {
        $response = $this->get('/business/load-board');
        $response->assertRedirect('/business/login');
    }

    /**
     * Test that Business Load Board create redirects unauthenticated visitors to login.
     */
    public function test_business_load_board_create_redirects_unauthenticated()
    {
        $response = $this->get('/business/load-board/create');
        $response->assertRedirect('/business/login');
    }

    /**
     * Test that Business Load Board show redirects unauthenticated visitors to login.
     */
    public function test_business_load_board_show_redirects_unauthenticated()
    {
        $response = $this->get('/business/load-board/1');
        $response->assertRedirect('/business/login');
    }
}
