<?php

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

    /**
     * The SPA mounts JSX, so the shell must emit @viteReactRefresh before @vite.
     * Without it every component throws "@vitejs/plugin-react can't detect
     * preamble" under `npm run dev` and the page renders blank. A production
     * build has no refresh runtime, so neither the Vite build nor any other
     * test notices — this assertion is the only thing standing between a
     * developer and an unbootable local app.
     */
    public function test_the_shell_emits_the_react_refresh_preamble_before_the_bundle(): void
    {
        $rendered = file_get_contents(resource_path('views/app-shell.blade.php'));

        $this->assertStringContainsString('@viteReactRefresh', $rendered);
        $this->assertLessThan(
            strpos($rendered, '@vite('),
            strpos($rendered, '@viteReactRefresh'),
            '@viteReactRefresh must come before @vite() or the preamble lands too late.'
        );
    }

    public function test_app_shell_view_does_not_extend_the_old_blade_layout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/app');

        $response->assertOk();
        // The old layouts/app.blade.php sidebar/topbar/bell must not be present —
        // the React shell (DesktopShell/MobileShell) owns navigation entirely on
        // this page. Its distinctive bell-polling markup is the id="bell-wrap"
        // element, only ever rendered by the Blade layout.
        $response->assertDontSee('id="bell-wrap"', false);
        $response->assertDontSee('id="sidebar"', false);
        $response->assertSee('id="react-app"', false);
    }
}
