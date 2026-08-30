<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * Covers the switch behind the Blade mobile/desktop split (CLAUDE.md gotcha
 * #13). The rollout is only safe because a page with no mobile counterpart
 * falls back to desktop untouched — that fallback is what these assert.
 */
class ResolveViewTest extends TestCase
{
    /**
     * Fixture views rather than real app views: pages get migrated to React and
     * deleted (Items already has been), and this test should not break each
     * time that happens.
     */
    protected function setUp(): void
    {
        parent::setUp();
        View::addLocation(__DIR__.'/../fixtures/views');
    }

    private function requestWithViewport(?string $viewport): void
    {
        $cookies = $viewport === null ? [] : ['viewport' => $viewport];
        $this->app->instance('request', Request::create('/', 'GET', [], $cookies));
    }

    public function test_it_renders_the_mobile_view_when_the_cookie_says_mobile_and_it_exists(): void
    {
        $this->requestWithViewport('mobile');

        $this->assertSame('mobile.probe.page', resolveView('probe.page')->name());
    }

    public function test_it_falls_back_to_desktop_when_no_mobile_counterpart_exists(): void
    {
        $this->requestWithViewport('mobile');

        // No mobile counterpart — the incremental-rollout case: an unmigrated
        // page must keep rendering exactly as before.
        $this->assertFalse(View::exists('mobile.probe.desktop-only'));
        $this->assertSame('probe.desktop-only', resolveView('probe.desktop-only')->name());
    }

    public function test_it_renders_desktop_when_the_cookie_says_desktop_even_if_a_mobile_view_exists(): void
    {
        $this->requestWithViewport('desktop');

        $this->assertTrue(View::exists('mobile.probe.page'));
        $this->assertSame('probe.page', resolveView('probe.page')->name());
    }

    public function test_it_renders_desktop_when_no_viewport_cookie_is_set(): void
    {
        $this->requestWithViewport(null);

        $this->assertSame('probe.page', resolveView('probe.page')->name());
    }

    public function test_it_passes_data_through_to_the_resolved_view(): void
    {
        $this->requestWithViewport('mobile');

        $view = resolveView('probe.page', ['items' => collect(['a', 'b'])]);

        $this->assertSame(['a', 'b'], $view->getData()['items']->all());
    }

    public function test_is_mobile_viewport_reflects_the_cookie(): void
    {
        $this->requestWithViewport('mobile');
        $this->assertTrue(isMobileViewport());

        $this->requestWithViewport('desktop');
        $this->assertFalse(isMobileViewport());

        $this->requestWithViewport(null);
        $this->assertFalse(isMobileViewport());
    }
}
