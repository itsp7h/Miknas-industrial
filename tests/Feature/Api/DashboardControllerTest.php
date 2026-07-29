<?php

namespace Tests\Feature\Api;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_requires_authentication(): void
    {
        $this->getJson('/api/v1/dashboard/summary')->assertStatus(401);
    }

    public function test_summary_returns_supplier_count(): void
    {
        $user = User::factory()->create();
        Supplier::factory()->count(3)->create();

        $response = $this->actingAs($user)->getJson('/api/v1/dashboard/summary');

        $response->assertOk()->assertJsonPath('suppliers_total', 3);
    }
}
