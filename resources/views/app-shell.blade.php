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
    @vite(['resources/css/app.css', 'resources/js-app/main.jsx'])
</head>
<body class="bg-slate-100 antialiased" style="font-family:'Inter',sans-serif;">
    <div
        id="react-app"
        data-user-id="{{ auth()->id() }}"
        data-user-name="{{ auth()->user()->name ?? 'User' }}"
        data-user-email="{{ auth()->user()->email ?? '' }}"
        data-is-admin="{{ auth()->user() && auth()->user()->hasRole('Admin') ? '1' : '0' }}"
        data-can-view-all-purchase-requests="{{ auth()->user() && auth()->user()->can('purchase-requests.view-all') ? '1' : '0' }}"
        data-can-view-active-pipeline="{{ auth()->user() && auth()->user()->can('purchase-requests.view-active-pipeline') ? '1' : '0' }}"
        data-can-view-own-purchase-requests="{{ auth()->user() && auth()->user()->can('purchase-requests.view-own') ? '1' : '0' }}"
        data-logout-url="{{ route('logout') }}"
        data-csrf-token="{{ csrf_token() }}"
    ></div>

    {{-- Provides window.mprModalOpen()/the MPR Alpine.js modal state to every React page,
         regardless of which one is showing. Its own "+ New Request" trigger button is
         hidden here since React pages render their own button that calls
         window.mprModalOpen() directly. --}}
    <x-purchase.request-modal :hide-trigger="true" />
</body>
</html>
