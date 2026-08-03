<?php

use Illuminate\Contracts\View\View;

if (! function_exists('resolveView')) {
    /**
     * Renders the mobile counterpart of $view (at "mobile.$view") when the
     * request's `viewport` cookie says mobile AND that mobile view actually
     * exists — otherwise falls back to $view unchanged. This is what makes
     * the Blade mobile/desktop rollout (CLAUDE.md gotcha #13) incremental:
     * a page with no mobile counterpart yet keeps rendering exactly as before.
     */
    function resolveView(string $view, array $data = []): View
    {
        $mobileView = 'mobile.' . $view;

        if (request()->cookie('viewport') === 'mobile' && view()->exists($mobileView)) {
            return view($mobileView, $data);
        }

        return view($view, $data);
    }
}

if (! function_exists('isMobileViewport')) {
    /**
     * For pages whose mobile view needs different data than desktop (e.g. a
     * full unpaginated list so client-side search covers everything, per
     * CLAUDE.md gotcha #6, instead of the desktop page's server pagination) —
     * use this to branch the query itself rather than trying to force one
     * dataset to fit both views.
     */
    function isMobileViewport(): bool
    {
        return request()->cookie('viewport') === 'mobile';
    }
}
