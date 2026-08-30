<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every React cutover deletes named Blade routes, and the shared layout and
 * dashboard call route() for the sidebar. A stale reference throws
 * RouteNotFoundException at render time — invisible to unit tests, and fatal
 * on a page every user sees. These render the surviving Blade pages for real.
 */
class BladePagesStillRenderTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create();
    }

    public function test_the_dashboard_renders_with_the_full_sidebar(): void
    {
        $this->actingAs($this->user())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Stock Summary', false);
    }

    /**
     * The sidebar links for migrated Inventory pages must point at the React
     * shell, not at deleted Blade routes.
     */
    public function test_the_sidebar_links_migrated_pages_at_the_react_shell(): void
    {
        $response = $this->actingAs($this->user())->get(route('dashboard'))->assertOk();

        foreach ([
            '/app/inventory/items',
            '/app/inventory/warehouses',
            '/app/inventory/movements',
            '/app/inventory/reports/summary',
            '/app/inventory/reports/movement',
            '/app/inventory/reports/low-stock',
            '/app/inventory/reports/valuation',
            '/app/sales/customers',
            '/app/sales/orders',
            '/app/sales/delivery-notes',
            '/app/sales/invoices',
            '/app/sales/payments',
        ] as $url) {
            $response->assertSee($url, false);
        }
    }

    public function test_no_blade_route_remains_for_migrated_pages(): void
    {
        foreach ([
            '/inventory/items',
            '/inventory/warehouses',
            '/inventory/movements',
            '/inventory/reports/summary',
            '/sales/customers',
            '/sales/orders',
            '/sales/delivery-notes',
            '/sales/invoices',
            '/sales/payments',
        ] as $url) {
            $this->actingAs($this->user())->get($url)->assertNotFound();
        }
    }
}
