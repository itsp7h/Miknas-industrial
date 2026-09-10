<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Quote Request — SteelERP</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="alternate icon" href="/favicon.ico">
    {{-- Must precede @vite, same as the SPA shell and the login page: without
         the refresh preamble every JSX module throws under `npm run dev`. --}}
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js-app/rfq.jsx'])
</head>
<body style="margin:0; font-family:system-ui,-apple-system,'Segoe UI',Roboto,sans-serif; background:#f1f5f9;">
    {{-- The supplier portal is React (desktop and mobile trees picked by
         useViewport), but it cannot live in the /app shell: that route is
         behind auth+verified and a supplier has no account at all. So it
         mounts here, from its own Vite entry, on the public token route.

         Only the token is handed over. Everything the page shows — the state
         of the invitation, the items, the confirmation code — comes from
         GET /api/v1/rfq/{token}, so the markup itself leaks nothing to a
         crawler or a forwarded link. --}}
    <div id="rfq-app" data-token="{{ $token }}"></div>

    <noscript>
        <div style="max-width:520px;margin:80px auto;padding:20px;border:1px solid #fecaca;background:#fef2f2;color:#b91c1c;border-radius:8px;font-size:14px;line-height:1.6;">
            Submitting a quote requires JavaScript. Please enable it and reload
            this page, or reply to the invitation email and we will take your
            prices directly.
        </div>
    </noscript>
</body>
</html>
