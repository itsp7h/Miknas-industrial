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
    private function requestWithViewport(?string $viewport): void
    {
        $cookies = $viewport === null ? [] : ['viewport' => $viewport];
        $this->app->instance('request', Request::create('/', 'GET', [], $cookies));
    }

    public function test_it_renders_the_mobile_view_when_the_cookie_says_mobile_and_it_exists(): void
    {
        $this->requestWithViewport('mobile');

        $this->assertSame('mobile.inventory.items.index', resolveView('inventory.items.index')->name());
    }

    public function test_it_falls_back_to_desktop_when_no_mobile_counterpart_exists(): void
    {
        $this->requestWithViewport('mobile');

        // Warehouses has no mobile/ counterpart — this is the incremental
        // rollout case: unmigrated pages must keep rendering as before.
        $this->assertFalse(View::exists('mobile.inventory.warehouses.index'));
        $this->assertSame('inventory.warehouses.index', resolveView('inventory.warehouses.index')->name());
    }

    public function test_it_renders_desktop_when_the_cookie_says_desktop_even_if_a_mobile_view_exists(): void
    {
        $this->requestWithViewport('desktop');

        $this->assertTrue(View::exists('mobile.inventory.items.index'));
        $this->assertSame('inventory.items.index', resolveView('inventory.items.index')->name());
    }

    public function test_it_renders_desktop_when_no_viewport_cookie_is_set(): void
    {
        $this->requestWithViewport(null);

        $this->assertSame('inventory.items.index', resolveView('inventory.items.index')->name());
    }

    public function test_it_passes_data_through_to_the_resolved_view(): void
    {
        $this->requestWithViewport('mobile');

        $view = resolveView('inventory.items.index', ['items' => collect(['a', 'b'])]);

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
