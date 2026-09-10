<?php

namespace Tests\Feature;

use App\Models\PurchaseRequest;
use App\Models\RfqInvitation;
use App\Models\User;
use App\View\Components\GuestLayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Every React cutover deletes named Blade routes, and a Blade page that still
 * calls route() for one throws RouteNotFoundException at render time —
 * invisible to unit tests, and fatal on a page users see.
 *
 * There are no Blade pages left on the shared chrome: the dashboard went
 * first, then the create-request page, then the RFQ and signature pages, and
 * `layouts/app` itself went with them. The SPA host page is the last Blade
 * page that calls route() at all, so it is what gets rendered here. What the
 * sidebar links is a React concern now, covered by Sidebar.test.jsx.
 *
 * The rest of this class is the part that still matters either way: the moved
 * URLs must redirect rather than 404, and the routes that became API endpoints
 * must be gone.
 */
class BladePagesStillRenderTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('purchase-requests.view-all');

        return $user;
    }

    /** The one Blade page left that calls route(): the SPA host page. */
    private function bladePage(): string
    {
        return '/app';
    }

    public function test_the_spa_host_page_renders_and_hands_react_its_context(): void
    {
        $this->actingAs($this->user())
            ->get($this->bladePage())
            ->assertOk()
            // A stale route() in this file — route('logout') is the live one —
            // would throw rather than render.
            ->assertSee('id="react-app"', false)
            ->assertSee('data-user-id', false)
            ->assertSee('data-csrf-token', false);
    }

    /**
     * There is no Blade chrome left at all. `layouts/app` went with the last
     * page on it; `layouts/guest` and the six Breeze partials went with the
     * auth screens. A new Blade page extending either would fail at render,
     * so this fails first and says why.
     */
    public function test_the_blade_chrome_and_its_last_pages_are_gone(): void
    {
        $this->assertFalse(view()->exists('layouts.app'), 'layouts/app was deleted with the last Blade page.');
        $this->assertFalse(view()->exists('layouts.guest'), 'layouts/guest went with the Breeze auth pages.');

        foreach (['purchase.rfq.show', 'purchase.signature.show', 'components.purchase.supplier-invite-list'] as $view) {
            $this->assertFalse(view()->exists($view), "View {$view} should have been deleted.");
        }

        // The Breeze partials had no other consumer than the auth pages.
        foreach ([
            'components.application-logo', 'components.auth-session-status', 'components.input-error',
            'components.input-label', 'components.primary-button', 'components.text-input',
        ] as $view) {
            $this->assertFalse(view()->exists($view), "Breeze partial {$view} should have been deleted.");
        }

        $this->assertFalse(class_exists(GuestLayout::class), 'GuestLayout went with layouts/guest.');

        // What is left: the two React host pages.
        $this->assertTrue(view()->exists('app-shell'));
        $this->assertTrue(view()->exists('auth.shell'));
    }

    /**
     * All five auth screens are React on one shared host page. They stay
     * outside the /app shell because they are exactly the screens someone
     * reaches without auth, without verification, or without a confirmed
     * password — so the shell's middleware would bounce them.
     *
     * Breeze's own POST routes survive alongside the JSON ones, as they did
     * for the login cutover: they are the framework's contract and its test
     * suite's, and nothing in our UI posts to them any more.
     */
    public function test_the_auth_screens_are_react_on_one_host_page(): void
    {
        $guest = [
            '/login' => 'login',
            '/forgot-password' => 'forgot-password',
            '/reset-password/reset-token-123' => 'reset-password',
        ];

        foreach ($guest as $url => $page) {
            $this->get($url)
                ->assertOk()
                ->assertSee('id="auth-app"', false)
                ->assertSee('data-page="'.$page.'"', false);
        }

        // The token and the address come from the emailed link; only the
        // server can hand them to the page.
        $this->get('/reset-password/reset-token-123?email=admin%40erp.com')
            ->assertSee('reset-token-123', false)
            ->assertSee('admin@erp.com', false);

        $unverified = User::factory()->unverified()->create();
        $this->actingAs($unverified)->get('/verify-email')
            ->assertOk()
            ->assertSee('data-page="verify-email"', false)
            ->assertSee($unverified->email, false);

        $this->actingAs($this->user())->get('/confirm-password')
            ->assertOk()
            ->assertSee('data-page="confirm-password"', false);

        foreach (['auth.login', 'auth.forgot-password', 'auth.reset-password',
            'auth.verify-email', 'auth.confirm-password'] as $view) {
            $this->assertFalse(view()->exists($view), "View {$view} should have been deleted.");
        }
    }

    /**
     * The RFQ picker and the signature pad are React dialogs writing to the
     * API. Their Blade pages had no sharable URL, so unlike the request pages
     * these 404 rather than redirect.
     */
    public function test_the_rfq_and_signature_routes_moved_to_the_api(): void
    {
        $user = $this->user();
        $pr = PurchaseRequest::factory()->create(['stage' => 'rfq']);

        foreach (["/purchase/requests/{$pr->id}/rfq", "/purchase/requests/{$pr->id}/sign"] as $url) {
            $this->actingAs($user)->get($url)->assertNotFound();
        }

        foreach ([
            'purchase.requests.rfq', 'purchase.requests.rfq.store', 'purchase.requests.rfq.select',
            'purchase.requests.rfq.send-all', 'purchase.requests.sign', 'purchase.requests.sign.store',
            'purchase.requests.generate-lpo',
            // The notification bell is React and uses the API pair.
            'notifications.unread', 'notifications.go', 'notifications.read-all',
        ] as $name) {
            $this->assertFalse(Route::has($name), "Route {$name} should have moved to the API.");
        }
    }

    /**
     * The public quote portal is React behind its own Vite entry. The Blade
     * route survives — a supplier has no session, so the portal cannot be a
     * route inside the /app shell — but it is a mount point now, and the four
     * pages it used to render are gone. The POST went with them: the quote is
     * submitted to POST /api/v1/rfq/{token}.
     */
    public function test_the_rfq_portal_is_a_react_mount_point(): void
    {
        $invitation = RfqInvitation::factory()->create();

        $this->get("/rfq/{$invitation->token}")
            ->assertOk()
            ->assertSee('id="rfq-app"', false)
            ->assertSee('data-token="'.$invitation->token.'"', false)
            // Nothing about the invitation is in the markup — the React page
            // reads it from the API, so a forwarded link leaks no prices.
            ->assertDontSee($invitation->supplier->name);

        // An unknown token still fails at the door rather than painting a
        // shell that then reports the same thing.
        $this->get('/rfq/nobody-issued-this')->assertNotFound();

        $this->assertFalse(Route::has('rfq.submit'), 'The portal submits to the API now.');
        $this->assertTrue(Route::has('rfq.show'), 'The invitation emails link to this route.');

        foreach (['rfq.show', 'rfq.show-mobile', 'rfq.expired', 'rfq.submitted'] as $view) {
            $this->assertFalse(view()->exists($view), "View {$view} should have been deleted.");
        }

        // The hand-rolled user-agent split went with them: one tree, picked by
        // useViewport (CLAUDE.md #12).
        $this->assertTrue(view()->exists('rfq.portal'));
    }

    /**
     * The Blade dashboard is gone; /dashboard redirects into the shell. The named
     * route has to survive, because Breeze's login and email-verification flows
     * and the root route all send people to it.
     */
    public function test_the_dashboard_moved_into_the_react_shell(): void
    {
        $this->assertTrue(Route::has('dashboard'));

        $this->actingAs($this->user())->get('/dashboard')->assertRedirect('/app');
        $this->get('/')->assertRedirect(route('dashboard'));
    }

    /**
     * What the sidebar links used to be assertable here, because the sidebar
     * was Blade. It is React now — Sidebar.test.jsx owns those assertions, and
     * the redirect tests below still prove the old URLs lead somewhere.
     */
    public function test_the_old_settings_urls_redirect_into_the_react_shell(): void
    {
        $admin = $this->user();
        $admin->assignRole('Admin');

        $this->actingAs($admin)->get('/settings/projects')->assertRedirect('/app/settings/companies');
        $this->actingAs($admin)->get('/settings/projects-overview')->assertRedirect('/app/settings/projects');
        $this->actingAs($admin)->get('/settings/users')->assertRedirect('/app/settings/users');
        $this->actingAs($admin)->get('/settings/integrations')->assertRedirect('/app/settings/integrations');
        $this->actingAs($admin)->get('/settings/vat')->assertRedirect('/app/settings/vat');
    }

    /**
     * The profile page was a Blade page that rendered nothing: its view used
     * `<x-app-layout>` slots against a `@yield('content')` layout, so every
     * visitor got the shell with an empty body. It is a React route now, the old
     * URL redirects, and the sidebar user card is the way in — nothing linked to
     * it before.
     */
    public function test_the_profile_page_moved_into_the_react_shell(): void
    {
        $user = $this->user();

        $this->actingAs($user)->get('/profile')->assertRedirect('/app/profile');

        foreach (['profile.update', 'profile.destroy'] as $name) {
            $this->assertFalse(Route::has($name), "Route {$name} should have moved to the API.");
        }
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
