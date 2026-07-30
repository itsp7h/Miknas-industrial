<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

class RegistrationClosedTest extends TestCase
{
    public function test_registration_page_is_not_reachable(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_registration_submission_is_not_reachable(): void
    {
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();
    }
}
