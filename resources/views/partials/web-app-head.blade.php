{{-- What lets a phone run SteelERP as an app. A browser tab cannot hide its
     own address bar (iOS gives a page no way to), but a site added to the home
     screen opens full screen when it says it may — that is these tags and
     /manifest.json. viewport-fit=cover is what makes env(safe-area-inset-*)
     non-zero, which the mobile chrome already pads by, so the coloured headers
     run up under the translucent status bar. --}}
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#1D4ED8">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="SteelERP">
<link rel="apple-touch-icon" href="/icons/icon-180.png">
