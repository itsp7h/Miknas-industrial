<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
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
            '/app/purchase/orders',
            '/app/purchase/grns',
            '/app/purchase/invoices',
            '/app/purchase/payments',
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
            '/app/production/orders',
            '/app/production/bom',
            '/app/production/material-issues',
            '/app/production/outputs',
        ] as $url) {
            $response->assertSee($url, false);
        }
    }

    /**
     * Companies is the first Settings page in the shell. The sidebar link is
     * rendered only for an Admin, so this needs one.
     */
    public function test_the_sidebar_links_companies_at_the_react_shell_for_an_admin(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin)->get(route('dashboard'))->assertOk()
            ->assertSee('/app/settings/companies', false);
    }

    /**
     * Both projects settings pages are React now, so their old URLs redirect and
     * the sidebar links the shell. The nav entries render only for an Admin.
     */
    public function test_the_sidebar_links_both_settings_pages_at_the_react_shell(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $response = $this->actingAs($admin)->get(route('dashboard'))->assertOk();

        $response->assertSee('/app/settings/companies', false);
        $response->assertSee('/app/settings/projects', false);
        $response->assertSee('/app/settings/users', false);
        $response->assertSee('/app/settings/integrations', false);
        $response->assertSee('/app/settings/vat', false);
    }

    public function test_the_old_settings_urls_redirect_into_the_react_shell(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin)->get('/settings/projects')->assertRedirect('/app/settings/companies');
        $this->actingAs($admin)->get('/settings/projects-overview')->assertRedirect('/app/settings/projects');
        $this->actingAs($admin)->get('/settings/users')->assertRedirect('/app/settings/users');
        $this->actingAs($admin)->get('/settings/integrations')->assertRedirect('/app/settings/integrations');
        $this->actingAs($admin)->get('/settings/vat')->assertRedirect('/app/settings/vat');
    }

    /**
     * The LPO print/PDF documents are DomPDF-backed and deliberately stay
     * Blade, so the Purchase Orders cutover must not have taken them with it.
     */
    public function test_the_lpo_print_and_pdf_routes_survive_the_cutover(): void
    {
        $this->assertTrue(Route::has('purchase.orders.print'));
        $this->assertTrue(Route::has('purchase.orders.pdf'));
    }

    /**
     * The Purchase pages carry a redirect rather than a 404, following the
     * precedent set for /purchase/pipeline: the URLs were live long enough to be
     * bookmarked, and a dead end is worse than a hop. The DomPDF documents sit
     * under the same prefix, so this also proves the wildcard redirects were
     * declared after them and do not swallow them.
     */
    public function test_moved_purchase_urls_redirect_into_the_react_shell(): void
    {
        $user = $this->user();

        foreach ([
            '/purchase/pipeline' => '/app/purchase/pipeline',
            '/purchase/orders' => '/app/purchase/orders',
            '/purchase/orders/7' => '/app/purchase/orders/7',
            '/purchase/grns' => '/app/purchase/grns',
            '/purchase/grns/7' => '/app/purchase/grns/7',
            '/purchase/grns/create' => '/app/purchase/grns',
            '/purchase/grns/create?purchase_order_id=4' => '/app/purchase/grns?purchase_order_id=4',
            '/purchase/invoices' => '/app/purchase/invoices',
            '/purchase/invoices/create' => '/app/purchase/invoices',
            '/purchase/invoices/7' => '/app/purchase/invoices',
            '/purchase/payments' => '/app/purchase/payments',
            '/purchase/payments/create' => '/app/purchase/payments',
            '/purchase/payments/create?invoice_id=9' => '/app/purchase/payments?invoice_id=9',
            '/purchase/payments/7' => '/app/purchase/payments',
        ] as $from => $to) {
            $this->actingAs($user)->get($from)->assertRedirect($to);
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
            '/production/orders',
            '/production/bom',
            '/production/material-issues',
            '/production/outputs',
        ] as $url) {
            $this->actingAs($this->user())->get($url)->assertNotFound();
        }
    }

    /** The template and import moved to the API with the page. */
    public function test_the_project_settings_writes_moved_to_the_api(): void
    {
        foreach (['settings.projects.store', 'settings.projects.import', 'settings.projects.template',
            'settings.projects.locations.store', 'settings.users.store', 'settings.users.update',
            'settings.integrations.whatsapp', 'settings.mail-accounts.index', 'settings.mail-accounts.store',
            'settings.mail-accounts.test', 'settings.vat.update'] as $name) {
            $this->assertFalse(Route::has($name), "Route {$name} should have moved to the API.");
        }
    }
}
