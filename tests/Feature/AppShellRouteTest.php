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

    /**
     * Echo reads where to connect from the page, not from the bundle, so one
     * build can serve both boxes (config/reverb.php, 'client').
     */
    public function test_the_shell_hands_echo_its_reverb_address_at_runtime(): void
    {
        config([
            'reverb.client' => [
                'key' => 'public-app-key',
                'host' => 'staging-steelerp.p7h.me',
                'port' => 443,
                'scheme' => 'https',
            ],
        ]);

        $html = $this->actingAs(User::factory()->create())->get('/app')->assertOk()->getContent();

        preg_match('/data-reverb="([^"]*)"/', $html, $match);
        $this->assertNotEmpty($match, 'the shell must carry data-reverb');
        $this->assertSame(
            ['key' => 'public-app-key', 'host' => 'staging-steelerp.p7h.me', 'port' => 443, 'scheme' => 'https'],
            json_decode(html_entity_decode($match[1]), true),
        );
    }

    /** The browser gets the public key; the secret signs server-side only. */
    public function test_the_reverb_secret_never_reaches_the_page(): void
    {
        config([
            'reverb.apps.apps.0.secret' => 'do-not-leak-this-secret',
            'broadcasting.connections.reverb.secret' => 'do-not-leak-this-secret',
        ]);

        $this->actingAs(User::factory()->create())->get('/app')
            ->assertOk()
            ->assertDontSee('do-not-leak-this-secret', false);
    }
}
