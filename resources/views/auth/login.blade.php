<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sign in — SteelERP</title>
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
    {{-- The login screen is React (desktop and mobile trees picked by
         useViewport), but it cannot live in the /app shell: that route is
         behind auth+verified, and a guest has neither. So it mounts here, from
         its own Vite entry, on the Blade route Breeze already owns. --}}
    <div
        id="auth-app"
        data-redirect-to="{{ url()->previous() !== url()->current() && str_starts_with(session()->get('url.intended', ''), url('/')) ? session()->get('url.intended') : url('/app') }}"
        data-show-dev-login="{{ app()->environment('local') ? '1' : '0' }}"
    ></div>

    {{-- Without JavaScript there is no form at all, so say so rather than
         showing a blank page. --}}
    <noscript>
        <div style="max-width:420px;margin:80px auto;padding:20px;border:1px solid #fecaca;background:#fef2f2;color:#b91c1c;border-radius:8px;font-size:14px;">
            Signing in requires JavaScript. Please enable it and reload this page.
        </div>
    </noscript>
</body>
</html>
