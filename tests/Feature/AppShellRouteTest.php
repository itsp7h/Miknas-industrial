<?php
// tests/Feature/AppShellRouteTest.php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppShellRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_suppliers_react_route_renders_the_app_shell(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/app/purchase/suppliers');

        $response->assertOk();
        $response->assertViewIs('app-shell');
    }

    public function test_purchase_suppliers_route_requires_auth(): void
    {
        $response = $this->get('/app/purchase/suppliers');

        $response->assertRedirect('/login');
    }
}
