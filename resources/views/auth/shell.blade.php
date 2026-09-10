@php
    // One host page for every auth screen, rather than five near-identical
    // ones. The page name selects the React tree; the title is here because
    // it is the only thing the server still decides about these screens.
    $titles = [
        'login' => 'Sign in',
        'forgot-password' => 'Reset your password',
        'reset-password' => 'Choose a new password',
        'verify-email' => 'Verify your email',
        'confirm-password' => 'Confirm your password',
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $titles[$page] ?? 'SteelERP' }} — SteelERP</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="alternate icon" href="/favicon.ico">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet"/>
    {{-- Must precede @vite, same as the SPA shell: without the refresh preamble
         every JSX module throws under `npm run dev` and nothing mounts. --}}
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js-app/auth.jsx'])
</head>
<body style="margin:0; font-family:'Inter',sans-serif;">
    {{-- The auth screens are React (desktop and mobile trees picked by
         useViewport), but they cannot live in the /app shell: that route is
         behind auth+verified, and these are precisely the screens someone
         reaches without both. So they mount here, from their own Vite entry,
         on the Blade routes Breeze already owns. --}}
    <div
        id="auth-app"
        data-page="{{ $page }}"
        data-props="{{ json_encode($props ?? []) }}"
    ></div>

    {{-- Without JavaScript there is no form at all, so say so rather than
         showing a blank page. --}}
    <noscript>
        <div style="max-width:420px;margin:80px auto;padding:20px;border:1px solid #fecaca;background:#fef2f2;color:#b91c1c;border-radius:8px;font-size:14px;">
            This page requires JavaScript. Please enable it and reload.
        </div>
    </noscript>
</body>
</html>
