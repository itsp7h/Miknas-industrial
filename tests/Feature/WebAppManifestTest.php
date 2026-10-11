<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A phone opens SteelERP full screen only when it is added to the home screen
 * and the site says it may run standalone. Lose one tag and the app quietly
 * opens inside Safari again, address bar and all, with every test green.
 */
class WebAppManifestTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_app_and_sign_in_pages_declare_themselves_a_web_app(): void
    {
        $pages = [
            $this->actingAs(User::factory()->create())->get('/app'),
        ];
        auth()->logout();
        $pages[] = $this->get('/login');

        foreach ($pages as $response) {
            $response->assertOk();
            $response->assertSee('<link rel="manifest" href="/manifest.json">', false);
            $response->assertSee('<meta name="apple-mobile-web-app-capable" content="yes">', false);
            $response->assertSee('viewport-fit=cover', false);
            $response->assertSee('<link rel="apple-touch-icon" href="/icons/icon-180.png">', false);
        }
    }

    public function test_the_manifest_opens_the_app_standalone_with_icons_that_exist(): void
    {
        $manifest = json_decode(file_get_contents(public_path('manifest.json')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('/app', $manifest['start_url']);
        $this->assertFileExists(public_path('icons/icon-180.png'));
        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(public_path(ltrim($icon['src'], '/')));
        }
    }

    /**
     * Without this, iOS rubber-bands the whole standalone app when it is
     * dragged past its top, pulling the layout down over a blank background.
     */
    public function test_the_standalone_app_does_not_rubber_band(): void
    {
        $css = preg_replace('/\s+/', ' ', file_get_contents(resource_path('css/app.css')));

        $this->assertMatchesRegularExpression(
            '/@media \(display-mode: standalone\) \{ html, body \{ overscroll-behavior-y: none; \} \}/',
            $css,
        );
    }
}
