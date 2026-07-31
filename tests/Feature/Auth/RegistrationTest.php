<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Registration route has been intentionally removed.
     * This test guards against accidental re-introduction of self-registration.
     */
    public function test_registration_route_is_disabled(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_registration_route_can_not_be_reached_via_post(): void
    {
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();
    }
}
