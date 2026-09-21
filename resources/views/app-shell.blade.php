<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>SteelERP</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="alternate icon" href="/favicon.ico">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet"/>
    {{-- Must precede @vite: @vitejs/plugin-react needs its refresh preamble in the
         document before any JSX module loads, or every component throws
         "can't detect preamble" under `npm run dev` and the SPA never mounts.
         A production build has no refresh runtime, so this is dev-only — which
         is exactly why a passing CI build does not prove the app boots. --}}
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js-app/main.jsx'])
</head>
<body class="bg-slate-100 antialiased" style="font-family:'Inter',sans-serif;">
    <div
        id="react-app"
        data-user-id="{{ auth()->id() }}"
        data-user-name="{{ auth()->user()->name ?? 'User' }}"
        data-user-email="{{ auth()->user()->email ?? '' }}"
        data-is-admin="{{ auth()->user() && auth()->user()->hasRole('Admin') ? '1' : '0' }}"
        data-can-view-all-purchase-requests="{{ auth()->user() && auth()->user()->can('pipeline.view-all') ? '1' : '0' }}"
        data-can-view-active-pipeline="{{ auth()->user() && auth()->user()->can('pipeline.view-active-pipeline') ? '1' : '0' }}"
        data-can-view-own-purchase-requests="{{ auth()->user() && auth()->user()->can('pipeline.view-own') ? '1' : '0' }}"
        data-logout-url="{{ route('logout') }}"
        data-csrf-token="{{ csrf_token() }}"
        {{-- The configured currency, so money renders right on first paint
             rather than after a fetch. --}}
        {{-- What this person may open. The menu is built from it, so a tab
             they cannot reach is never offered rather than refused at the
             door. The API checks again regardless. --}}
        data-permissions="{{ json_encode(auth()->user()?->getAllPermissions()->pluck('name')->values() ?? []) }}"
        data-currency="{{ \App\Http\Controllers\Api\Settings\FinanceController::currencyCode() }}"
    ></div>
</body>
</html>
