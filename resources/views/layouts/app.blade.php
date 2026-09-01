<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>
    // Buckets the real viewport width into a `viewport` cookie so the next
    // server-rendered request can pick the matching Blade view via
    // resolveView() (CLAUDE.md gotcha #13). Blade has no live client-side
    // swap like the React shell's useViewport(), so a mismatch reloads once —
    // guarded by sessionStorage so a bad detection can never loop.
    (function () {
        function bucket() { return window.innerWidth < 768 ? 'mobile' : 'desktop'; }
        function getCookie(name) {
            var m = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
            return m ? decodeURIComponent(m[2]) : null;
        }
        var current = bucket();
        if (getCookie('viewport') !== current) {
            document.cookie = 'viewport=' + current + ';path=/;max-age=31536000;SameSite=Lax';
            if (!sessionStorage.getItem('viewport_reload_done')) {
                sessionStorage.setItem('viewport_reload_done', '1');
                location.reload();
            }
        } else {
            sessionStorage.removeItem('viewport_reload_done');
        }
    })();
    </script>
    <title>SteelERP @hasSection('title') — @yield('title') @endif</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="alternate icon" href="/favicon.ico">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet"/>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 antialiased" style="font-family:'Inter',sans-serif; overflow-x:hidden;">

<div style="display:flex; min-height:100vh; min-width:0;">

    {{-- ════════════════ SIDEBAR ════════════════ --}}
    <aside id="sidebar" style="
        width: 260px;
        min-width: 260px;
        background: #0f172a;
        display: flex;
        flex-direction: column;
        position: fixed;
        top: 0; left: 0; bottom: 0;
        z-index: 50;
        overflow-y: auto;
        transition: transform .2s ease;
    ">
        {{-- Logo --}}
        <div style="padding:20px 20px 16px; border-bottom:1px solid #1e293b; flex-shrink:0;">
            <div style="display:flex; align-items:center; gap:12px;">
                <div style="width:36px;height:36px;border-radius:10px;background:#2563eb;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <svg width="20" height="20" fill="none" stroke="#fff" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                </div>
                <div>
                    <div style="color:#fff;font-weight:700;font-size:15px;line-height:1.2;">SteelERP</div>
                    <div style="color:#64748b;font-size:11px;margin-top:1px;">Manufacturing & Trading</div>
                </div>
            </div>
        </div>

        {{-- Nav --}}
        <nav style="padding:12px 12px; flex:1;">

            {{-- Dashboard --}}
            <a href="{{ route('dashboard') }}" style="
                display:flex; align-items:center; gap:10px;
                padding:8px 12px; border-radius:8px; margin-bottom:2px;
                font-size:13.5px; font-weight:500; text-decoration:none;
                {{ request()->routeIs('dashboard') ? 'background:#2563eb;color:#fff;' : 'color:#94a3b8;' }}
                transition:background .15s,color .15s;
            " onmouseover="if(!this.style.background.includes('2563eb'))this.style.background='#1e293b'" onmouseout="if(!this.style.background.includes('2563eb'))this.style.background=''">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h4a1 1 0 011 1v5a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h4a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM14 5a1 1 0 011-1h4a1 1 0 011 1v2a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zM14 11a1 1 0 011-1h4a1 1 0 011 1v8a1 1 0 01-1 1h-4a1 1 0 01-1-1v-8z"/>
                </svg>
                Dashboard
            </a>

            {{-- ── PURCHASE ── --}}
            <div style="margin-top:16px; margin-bottom:4px; padding:0 12px;">
                <span style="font-size:10px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:#f59e0b;display:flex;align-items:center;gap:6px;">
                    <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                    Purchase
                </span>
            </div>
            @php
                $navLink = function(string $route, string $label, string $icon = '') use (&$navLink) { return ''; };
            @endphp
            {{-- Pipeline now lives in the React app shell — full navigation, not a named Blade route --}}
            <a href="/app/purchase/pipeline" style="
                display:block;
                padding:7px 12px 7px 24px; border-radius:7px; margin-bottom:4px;
                font-size:13px; text-decoration:none; font-weight:600;
                {{ request()->is('app/purchase/pipeline*') ? 'background:#2563eb;color:#fff;' : 'color:#fbbf24;' }}
            " onmouseover="if(!this.style.background.includes('2563eb'))this.style.background='#1e293b'" onmouseout="if(!this.style.background.includes('2563eb'))this.style.background=''">
                Pipeline
            </a>
            {{-- Suppliers now lives in the React app shell — full navigation, not a named Blade route --}}
            <a href="/app/purchase/suppliers" style="
                display:block; padding:7px 12px 7px 24px; border-radius:7px; margin-bottom:1px;
                font-size:13px; text-decoration:none;
                {{ request()->is('app/purchase/suppliers*') ? 'background:#1e293b;color:#fff;font-weight:500;' : 'color:#94a3b8;' }}
            " onmouseover="if(!this.style.color.includes('fff'))this.style.color='#e2e8f0'" onmouseout="if(!this.style.background.includes('1e293b'))this.style.color='#94a3b8'">
                Suppliers
            </a>
            {{-- Purchase Orders now lives in the React app shell — full navigation, not a named Blade route --}}
            <a href="/app/purchase/orders" style="
                display:block; padding:7px 12px 7px 24px; border-radius:7px; margin-bottom:1px;
                font-size:13px; text-decoration:none;
                {{ request()->is('app/purchase/orders*') ? 'background:#1e293b;color:#fff;font-weight:500;' : 'color:#94a3b8;' }}
            " onmouseover="if(!this.style.color.includes('fff'))this.style.color='#e2e8f0'" onmouseout="if(!this.style.background.includes('1e293b'))this.style.color='#94a3b8'">
                Purchase Orders
            </a>
            {{-- Goods Receipt now lives in the React app shell — full navigation, not a named Blade route --}}
            <a href="/app/purchase/grns" style="
                display:block; padding:7px 12px 7px 24px; border-radius:7px; margin-bottom:1px;
                font-size:13px; text-decoration:none;
                {{ request()->is('app/purchase/grns*') ? 'background:#1e293b;color:#fff;font-weight:500;' : 'color:#94a3b8;' }}
            " onmouseover="if(!this.style.color.includes('fff'))this.style.color='#e2e8f0'" onmouseout="if(!this.style.background.includes('1e293b'))this.style.color='#94a3b8'">
                Goods Receipt (GRN)
            </a>
            @foreach([
                ['purchase.invoices.index',   'Supplier Invoices'],
                ['purchase.payments.index',   'Payments'],
            ] as [$routeName, $label])
            <a href="{{ route($routeName) }}" style="
                display:block; padding:7px 12px 7px 24px; border-radius:7px; margin-bottom:1px;
                font-size:13px; text-decoration:none;
                {{ request()->routeIs(str_replace('.index','',$routeName).'*') ? 'background:#1e293b;color:#fff;font-weight:500;' : 'color:#94a3b8;' }}
            " onmouseover="if(!this.style.color.includes('fff'))this.style.color='#e2e8f0'" onmouseout="if(!this.style.background.includes('1e293b'))this.style.color='#94a3b8'">
                {{ $label }}
            </a>
            @endforeach

            {{-- ── INVENTORY ── --}}
            <div style="margin-top:16px; margin-bottom:4px; padding:0 12px;">
                <span style="font-size:10px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:#10b981;display:flex;align-items:center;gap:6px;">
                    <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
                    Inventory
                </span>
            </div>
            {{-- Items now lives in the React app shell — full navigation, not a named Blade route --}}
            <a href="/app/inventory/items" style="
                display:block; padding:7px 12px 7px 24px; border-radius:7px; margin-bottom:1px;
                font-size:13px; text-decoration:none;
                {{ request()->is('app/inventory/items*') ? 'background:#1e293b;color:#fff;font-weight:500;' : 'color:#94a3b8;' }}
            " onmouseover="if(!this.style.color.includes('fff'))this.style.color='#e2e8f0'" onmouseout="if(!this.style.background.includes('1e293b'))this.style.color='#94a3b8'">
                Items
            </a>
            {{-- Warehouses now lives in the React app shell --}}
            <a href="/app/inventory/warehouses" style="
                display:block; padding:7px 12px 7px 24px; border-radius:7px; margin-bottom:1px;
                font-size:13px; text-decoration:none;
                {{ request()->is('app/inventory/warehouses*') ? 'background:#1e293b;color:#fff;font-weight:500;' : 'color:#94a3b8;' }}
            " onmouseover="if(!this.style.color.includes('fff'))this.style.color='#e2e8f0'" onmouseout="if(!this.style.background.includes('1e293b'))this.style.color='#94a3b8'">
                Warehouses
            </a>
            {{-- Stock Movements now lives in the React app shell --}}
            <a href="/app/inventory/movements" style="
                display:block; padding:7px 12px 7px 24px; border-radius:7px; margin-bottom:1px;
                font-size:13px; text-decoration:none;
                {{ request()->is('app/inventory/movements*') ? 'background:#1e293b;color:#fff;font-weight:500;' : 'color:#94a3b8;' }}
            " onmouseover="if(!this.style.color.includes('fff'))this.style.color='#e2e8f0'" onmouseout="if(!this.style.background.includes('1e293b'))this.style.color='#94a3b8'">
                Stock Movements
            </a>
            {{-- Inventory reports now live in the React app shell --}}
            @foreach([
                ['/app/inventory/reports/summary',   'Stock Summary'],
                ['/app/inventory/reports/movement',  'Movement Report'],
                ['/app/inventory/reports/low-stock', 'Low Stock Alert'],
                ['/app/inventory/reports/valuation', 'Valuation'],
            ] as [$url, $label])
            <a href="{{ $url }}" style="
                display:block; padding:7px 12px 7px 24px; border-radius:7px; margin-bottom:1px;
                font-size:13px; text-decoration:none;
                {{ request()->is(ltrim($url, '/')) ? 'background:#1e293b;color:#fff;font-weight:500;' : 'color:#94a3b8;' }}
            " onmouseover="if(!this.style.color.includes('fff'))this.style.color='#e2e8f0'" onmouseout="if(!this.style.background.includes('1e293b'))this.style.color='#94a3b8'">
                {{ $label }}
            </a>
            @endforeach
            {{-- ── PRODUCTION ── --}}
            <div style="margin-top:16px; margin-bottom:4px; padding:0 12px;">
                <span style="font-size:10px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:#f97316;display:flex;align-items:center;gap:6px;">
                    <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    Production
                </span>
            </div>
            {{-- Production now lives in the React app shell --}}
            @foreach([
                ['/app/production/orders',          'Production Orders'],
                ['/app/production/bom',             'Bill of Materials'],
                ['/app/production/material-issues', 'Material Issues'],
                ['/app/production/outputs',         'Production Output'],
            ] as [$url, $label])
            <a href="{{ $url }}" style="
                display:block; padding:7px 12px 7px 24px; border-radius:7px; margin-bottom:1px;
                font-size:13px; text-decoration:none;
                {{ request()->is(ltrim($url, '/')) ? 'background:#1e293b;color:#fff;font-weight:500;' : 'color:#94a3b8;' }}
            " onmouseover="if(!this.style.color.includes('fff'))this.style.color='#e2e8f0'" onmouseout="if(!this.style.background.includes('1e293b'))this.style.color='#94a3b8'">
                {{ $label }}
            </a>
            @endforeach

            {{-- ── SALES ── --}}
            <div style="margin-top:16px; margin-bottom:4px; padding:0 12px;">
                <span style="font-size:10px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:#a78bfa;display:flex;align-items:center;gap:6px;">
                    <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    Sales
                </span>
            </div>
            {{-- Customers now lives in the React app shell --}}
            <a href="/app/sales/customers" style="
                display:block; padding:7px 12px 7px 24px; border-radius:7px; margin-bottom:1px;
                font-size:13px; text-decoration:none;
                {{ request()->is('app/sales/customers*') ? 'background:#1e293b;color:#fff;font-weight:500;' : 'color:#94a3b8;' }}
            " onmouseover="if(!this.style.color.includes('fff'))this.style.color='#e2e8f0'" onmouseout="if(!this.style.background.includes('1e293b'))this.style.color='#94a3b8'">
                Customers
            </a>
            {{-- Sales Orders now lives in the React app shell --}}
            <a href="/app/sales/orders" style="
                display:block; padding:7px 12px 7px 24px; border-radius:7px; margin-bottom:1px;
                font-size:13px; text-decoration:none;
                {{ request()->is('app/sales/orders*') ? 'background:#1e293b;color:#fff;font-weight:500;' : 'color:#94a3b8;' }}
            " onmouseover="if(!this.style.color.includes('fff'))this.style.color='#e2e8f0'" onmouseout="if(!this.style.background.includes('1e293b'))this.style.color='#94a3b8'">
                Sales Orders
            </a>
            {{-- The rest of Sales now lives in the React app shell --}}
            @foreach([
                ['/app/sales/delivery-notes', 'Delivery Notes'],
                ['/app/sales/invoices',       'Sales Invoices'],
                ['/app/sales/payments',       'Payment Receipts'],
            ] as [$url, $label])
            <a href="{{ $url }}" style="
                display:block; padding:7px 12px 7px 24px; border-radius:7px; margin-bottom:1px;
                font-size:13px; text-decoration:none;
                {{ request()->is(ltrim($url, '/')) ? 'background:#1e293b;color:#fff;font-weight:500;' : 'color:#94a3b8;' }}
            " onmouseover="if(!this.style.color.includes('fff'))this.style.color='#e2e8f0'" onmouseout="if(!this.style.background.includes('1e293b'))this.style.color='#94a3b8'">
                {{ $label }}
            </a>
            @endforeach

            {{-- ── SETTINGS (Admin only) ── --}}
            @role('Admin')
            <div style="margin-top:16px; margin-bottom:4px; padding:0 12px;">
                <span style="font-size:10px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:#64748b;display:flex;align-items:center;gap:6px;">
                    <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    System
                </span>
            </div>
            <a href="{{ route('settings.projects.index') }}" style="
                display:flex; align-items:center; gap:8px;
                padding:7px 12px 7px 24px; border-radius:7px; margin-bottom:1px;
                font-size:13px; text-decoration:none;
                {{ request()->routeIs('settings.projects.index') ? 'background:#1e293b;color:#fff;font-weight:500;' : 'color:#94a3b8;' }}
            " onmouseover="if(!this.style.color.includes('fff'))this.style.color='#e2e8f0'" onmouseout="if(!this.style.background.includes('1e293b'))this.style.color='#94a3b8'">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
                Companies
            </a>
            <a href="{{ route('settings.projects.overview') }}" style="
                display:flex; align-items:center; gap:8px;
                padding:7px 12px 7px 24px; border-radius:7px; margin-bottom:1px;
                font-size:13px; text-decoration:none;
                {{ request()->routeIs('settings.projects.overview') ? 'background:#1e293b;color:#fff;font-weight:500;' : 'color:#94a3b8;' }}
            " onmouseover="if(!this.style.color.includes('fff'))this.style.color='#e2e8f0'" onmouseout="if(!this.style.background.includes('1e293b'))this.style.color='#94a3b8'">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                </svg>
                Projects
            </a>
            <a href="{{ route('settings.users.index') }}" style="
                display:flex; align-items:center; gap:8px;
                padding:7px 12px 7px 24px; border-radius:7px; margin-bottom:1px;
                font-size:13px; text-decoration:none;
                {{ request()->routeIs('settings.users.*') ? 'background:#1e293b;color:#fff;font-weight:500;' : 'color:#94a3b8;' }}
            " onmouseover="if(!this.style.color.includes('fff'))this.style.color='#e2e8f0'" onmouseout="if(!this.style.background.includes('1e293b'))this.style.color='#94a3b8'">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4-4 4 4 0 004 4zm6 0a4 4 0 10-4-4"/>
                </svg>
                Users
            </a>
            <a href="{{ route('settings.integrations') }}" style="
                display:flex; align-items:center; gap:8px;
                padding:7px 12px 7px 24px; border-radius:7px; margin-bottom:1px;
                font-size:13px; text-decoration:none;
                {{ request()->routeIs('settings.integrations*') ? 'background:#1e293b;color:#fff;font-weight:500;' : 'color:#94a3b8;' }}
            " onmouseover="if(!this.style.color.includes('fff'))this.style.color='#e2e8f0'" onmouseout="if(!this.style.background.includes('1e293b'))this.style.color='#94a3b8'">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                Integrations
            </a>
            <a href="{{ route('settings.vat') }}" style="
                display:flex; align-items:center; gap:8px;
                padding:7px 12px 7px 24px; border-radius:7px; margin-bottom:1px;
                font-size:13px; text-decoration:none;
                {{ request()->routeIs('settings.vat*') ? 'background:#1e293b;color:#fff;font-weight:500;' : 'color:#94a3b8;' }}
            " onmouseover="if(!this.style.color.includes('fff'))this.style.color='#e2e8f0'" onmouseout="if(!this.style.background.includes('1e293b'))this.style.color='#94a3b8'">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/>
                </svg>
                VAT Settings
            </a>
            @endrole

            <div style="height:16px;"></div>
        </nav>

        {{-- User footer --}}
        <div style="padding:12px; border-top:1px solid #1e293b; flex-shrink:0;">
            <div style="display:flex; align-items:center; gap:10px; padding:8px 10px; border-radius:8px; background:#1e293b;">
                <div style="width:32px;height:32px;border-radius:50%;background:#2563eb;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:13px;font-weight:700;color:#fff;">
                    {{ strtoupper(substr(Auth::user()->name ?? 'U', 0, 1)) }}
                </div>
                <div style="flex:1;min-width:0;">
                    <div style="color:#e2e8f0;font-size:12.5px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ Auth::user()->name ?? 'User' }}</div>
                    <div style="color:#64748b;font-size:11px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ Auth::user()->email ?? '' }}</div>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" title="Sign out" style="background:none;border:none;cursor:pointer;color:#64748b;padding:4px;" onmouseover="this.style.color='#f87171'" onmouseout="this.style.color='#64748b'">
                        <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    {{-- Mobile overlay --}}
    <div id="sidebar-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:40;"></div>

    {{-- ════════════════ MAIN AREA ════════════════ --}}
    <div id="main-area" style="flex:1; margin-left:260px; display:flex; flex-direction:column; min-height:100vh; min-width:0;">

        {{-- Top bar --}}
        <header style="
            height:60px; background:#fff; border-bottom:1px solid #e2e8f0;
            display:flex; align-items:center; gap:16px; padding:0 28px;
            position:sticky; top:0; z-index:30; flex-shrink:0;
            box-shadow:0 1px 3px rgba(0,0,0,.06);
        ">
            <button id="sidebar-toggle" style="display:none; background:none; border:none; cursor:pointer; color:#64748b; padding:4px;" onclick="toggleSidebar()">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>

            <div style="flex:1;">
                <span style="font-size:14px;font-weight:600;color:#1e293b;">@yield('title', 'Dashboard')</span>
            </div>

            <div style="display:flex;align-items:center;gap:16px;">
                <div id="topbar-date-group" style="display:flex;align-items:center;gap:16px;">
                    <span style="font-size:12px;color:#94a3b8;white-space:nowrap;">{{ now()->format('l, d M Y') }}</span>
                    <div style="width:1px;height:20px;background:#e2e8f0;"></div>
                </div>

                {{-- Bell notification --}}
                <div style="position:relative;" id="bell-wrap">
                  <button id="bell-btn" onclick="toggleBellDropdown()" title="Notifications"
                    style="position:relative;width:34px;height:34px;border-radius:9px;border:1px solid #e2e8f0;
                           background:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#64748b;
                           transition:background .15s,color .15s;"
                    onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='#fff'">
                    <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                    <span id="bell-badge" style="display:none;position:absolute;top:-4px;right:-4px;
                      background:#ef4444;color:#fff;font-size:10px;font-weight:700;
                      min-width:17px;height:17px;border-radius:9px;padding:0 4px;
                      display:none;align-items:center;justify-content:center;border:2px solid #fff;"></span>
                  </button>

                  {{-- Dropdown --}}
                  <div id="bell-dropdown" style="display:none;position:absolute;top:42px;right:0;
                    background:#fff;border:1.5px solid #e2e8f0;border-radius:14px;
                    box-shadow:0 12px 32px rgba(0,0,0,.12);width:320px;z-index:9000;overflow:hidden;">
                    <div style="padding:12px 16px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;">
                      <span style="font-size:13px;font-weight:700;color:#0f172a;">Notifications</span>
                      <button onclick="markAllRead()" style="font-size:11px;color:#2563eb;background:none;border:none;cursor:pointer;font-weight:600;">Mark all read</button>
                    </div>
                    <div id="bell-list" style="max-height:320px;overflow-y:auto;">
                      <div style="padding:24px;text-align:center;color:#94a3b8;font-size:13px;" id="bell-empty">No new notifications</div>
                    </div>
                  </div>
                </div>

                <div id="topbar-user-divider" style="width:1px;height:20px;background:#e2e8f0;"></div>
                <div style="display:flex;align-items:center;gap:8px;">
                    <div style="width:32px;height:32px;flex-shrink:0;border-radius:50%;background:#2563eb;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:#fff;">
                        {{ strtoupper(substr(Auth::user()->name ?? 'U', 0, 1)) }}
                    </div>
                    <span id="topbar-username" style="font-size:13px;color:#475569;font-weight:500;white-space:nowrap;">{{ Auth::user()->name ?? 'User' }}</span>
                </div>
            </div>
        </header>

        {{-- Content --}}
        <main id="main-content" style="flex:1; padding:28px; background:#f1f5f9; min-width:0; overflow-x:hidden;">

            @yield('content')
        </main>
    </div>
</div>

{{-- ── Mobile bottom tab bar — mirrors the sidebar's top-level module links.
     Hidden on desktop (>=1024px) via the media query below; #main-content
     gets extra bottom padding there so this doesn't cover the last row of
     content. ── --}}
<nav id="bottom-tab-bar" style="
    display:none; position:fixed; bottom:0; left:0; right:0; z-index:45;
    background:#fff; border-top:1px solid #e2e8f0; box-shadow:0 -2px 8px rgba(0,0,0,.05);
    padding-bottom:env(safe-area-inset-bottom);
">
    <div style="display:flex;">
        @foreach([
            ['route' => route('dashboard'), 'active' => request()->routeIs('dashboard'), 'label' => 'Dashboard',
             'icon' => 'M4 5a1 1 0 011-1h4a1 1 0 011 1v5a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h4a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM14 5a1 1 0 011-1h4a1 1 0 011 1v2a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zM14 11a1 1 0 011-1h4a1 1 0 011 1v8a1 1 0 01-1 1h-4a1 1 0 01-1-1v-8z'],
            ['route' => '/app/purchase/pipeline', 'active' => request()->is('purchase*') || request()->is('app/purchase*'), 'label' => 'Purchase',
             'icon' => 'M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z'],
            ['route' => '/app/inventory/items', 'active' => request()->is('inventory*') || request()->is('app/inventory*'), 'label' => 'Inventory',
             'icon' => 'M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4'],
            ['route' => '/app/production/orders', 'active' => request()->is('production*') || request()->is('app/production*'), 'label' => 'Production',
             'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z'],
            ['route' => '/app/sales/customers', 'active' => request()->is('sales*') || request()->is('app/sales*'), 'label' => 'Sales',
             'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
        ] as $tab)
        <a href="{{ $tab['route'] }}" style="
            flex:1; display:flex; flex-direction:column; align-items:center; gap:2px;
            padding:8px 4px 6px; text-decoration:none;
            color: {{ $tab['active'] ? '#2563eb' : '#94a3b8' }};
        ">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $tab['icon'] }}"/>
            </svg>
            <span style="font-size:10px; font-weight:{{ $tab['active'] ? '700' : '500' }};">{{ $tab['label'] }}</span>
        </a>
        @endforeach
    </div>
</nav>

{{-- ── Toast notification container ── --}}
<div id="toast-stack" style="position:fixed;bottom:24px;right:24px;z-index:9999;
     display:flex;flex-direction:column;gap:10px;pointer-events:none;"></div>

<style>
/* The sidebar slides off-screen via JS below <1024px, but the content area's
   margin-left:260px (set inline, above) never followed it — leaving a 260px
   dead zone that shifted everything right on phones. This is CSS (not JS)
   so it applies on first paint, before toggleSidebar()'s script even runs. */
@media (max-width: 1023px) {
    #main-area { margin-left: 0 !important; }
    /* The top bar's fixed 28px padding + a full date string + username all in one
       row only fit a desktop-width window — on a phone they overflowed the
       viewport (the "shifted/cut off" look). Trim to what actually fits. */
    #main-area > header { padding: 0 14px !important; gap: 8px !important; }
    #main-area > header > div:first-of-type { min-width: 0; }
    #main-area > header > div:first-of-type span { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    #topbar-date-group, #topbar-user-divider, #topbar-username { display: none !important; }
    #bottom-tab-bar { display: block !important; }
    #main-content { padding-bottom: 84px !important; max-width: 100vw !important; }
}
/* Phone-width only (matches the `mobile` viewport-cookie bucket, gotcha #13) —
   the 28px desktop content padding and 60px admin-panel topbar read as "a
   desktop page shrunk down" rather than a native mobile screen. Tighten both
   so mobile.* pages can go closer to edge-to-edge. */
@media (max-width: 767px) {
    #main-content { padding: 12px 12px 84px !important; background: #f8fafc !important; }
    #main-area > header { height: 52px !important; box-shadow: none !important; border-bottom: 1px solid #f1f5f9 !important; }
}
@keyframes toastIn {
    from { opacity:0; transform:translateX(40px) scale(.95); }
    to   { opacity:1; transform:translateX(0)    scale(1); }
}
@keyframes toastOut {
    from { opacity:1; transform:translateX(0) scale(1);    max-height:80px; margin:0; }
    to   { opacity:0; transform:translateX(40px) scale(.95); max-height:0;  margin:0; }
}
.toast {
    display:flex;align-items:flex-start;gap:10px;
    padding:13px 16px;border-radius:12px;
    font-size:13px;font-weight:500;line-height:1.4;
    box-shadow:0 8px 24px rgba(0,0,0,.12),0 2px 6px rgba(0,0,0,.06);
    pointer-events:all;cursor:pointer;max-width:340px;min-width:240px;
    animation:toastIn .3s cubic-bezier(.34,1.56,.64,1) forwards;
    border:1px solid transparent;
    position:relative;overflow:hidden;
}
.toast::after {
    content:'';position:absolute;bottom:0;left:0;height:3px;
    background:currentColor;opacity:.25;
    animation:toastProgress linear forwards;
}
@keyframes toastProgress {
    from { width:100%; }
    to   { width:0%; }
}
.toast-success { background:#f0fdf4;border-color:#bbf7d0;color:#15803d; }
.toast-error   { background:#fef2f2;border-color:#fecaca;color:#dc2626; }
.toast-info    { background:#eff6ff;border-color:#bfdbfe;color:#1d4ed8; }
.toast-warn    { background:#fffbeb;border-color:#fde68a;color:#92400e; }
</style>

<script>
// ── Toast system ─────────────────────────────────────────
function showToast(message, type, duration) {
    type     = type     || 'success';
    duration = duration || 4000;
    var stack = document.getElementById('toast-stack');

    var icons = {
        success: '<svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20" style="flex-shrink:0;margin-top:1px;"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>',
        error:   '<svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20" style="flex-shrink:0;margin-top:1px;"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>',
        info:    '<svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20" style="flex-shrink:0;margin-top:1px;"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>',
        warn:    '<svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20" style="flex-shrink:0;margin-top:1px;"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>',
    };

    var t = document.createElement('div');
    t.className = 'toast toast-' + type;
    t.style.setProperty('--dur', duration + 'ms');
    t.innerHTML = icons[type] + '<span style="flex:1;">' + message + '</span>'
        + '<button onclick="dismissToast(this.parentNode)" style="background:none;border:none;cursor:pointer;'
        + 'color:currentColor;opacity:.5;padding:0;margin-left:4px;font-size:16px;line-height:1;'
        + 'flex-shrink:0;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=.5">×</button>';
    t.querySelector('button').addEventListener('click', function(e) { e.stopPropagation(); });
    t.style.setProperty('--dur', duration + 'ms');
    t.querySelector('::after');   // trigger
    t.style.cssText += ';--dur:' + duration + 'ms;';
    // Apply progress bar duration via inline style on ::after via a workaround:
    t.addEventListener('animationend', function(e) {
        if (e.animationName === 'toastIn') {
            t.style.setProperty('animation', 'none'); // freeze in place
        }
    });
    // Progress bar via a child div
    var bar = document.createElement('div');
    bar.style.cssText = 'position:absolute;bottom:0;left:0;height:3px;background:currentColor;'
        + 'opacity:.25;width:100%;transition:width ' + duration + 'ms linear;border-radius:0 0 12px 12px;';
    t.appendChild(bar);
    stack.appendChild(t);
    // Trigger progress bar shrink
    requestAnimationFrame(function() { requestAnimationFrame(function() { bar.style.width = '0%'; }); });

    var timer = setTimeout(function() { dismissToast(t); }, duration);
    t.addEventListener('click', function() { clearTimeout(timer); dismissToast(t); });

    return t;
}

function dismissToast(el) {
    if (!el || el._dismissing) return;
    el._dismissing = true;
    el.style.animation = 'toastOut .25s ease forwards';
    setTimeout(function() { if (el.parentNode) el.parentNode.removeChild(el); }, 240);
}

// ── Fire session flash messages as toasts ───────────────
(function() {
    @if(session('success'))
        showToast(@json(session('success')), 'success');
    @endif
    @if(session('error'))
        showToast(@json(session('error')), 'error');
    @endif
    @if(session('info'))
        showToast(@json(session('info')), 'info');
    @endif
    @if(session('warning'))
        showToast(@json(session('warning')), 'warn');
    @endif
})();
</script>

{{-- ── Global delete confirmation modal ── --}}
<div id="global-delete-modal" style="display:none;position:fixed;inset:0;z-index:9000;
     background:rgba(15,23,42,.6);backdrop-filter:blur(5px);
     align-items:center;justify-content:center;padding:20px;">
    <div style="background:#fff;border-radius:18px;width:100%;max-width:380px;overflow:hidden;
                box-shadow:0 24px 60px rgba(0,0,0,.25);animation:toastIn .22s cubic-bezier(.34,1.56,.64,1);">
        <div style="height:3px;background:linear-gradient(90deg,#dc2626,#ef4444,#f97316);"></div>
        <div style="padding:22px 22px 8px;display:flex;align-items:flex-start;gap:14px;">
            <div style="width:40px;height:40px;border-radius:12px;background:#fef2f2;display:flex;
                        align-items:center;justify-content:center;flex-shrink:0;">
                <svg width="20" height="20" fill="none" stroke="#dc2626" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M8 7V4a1 1 0 011-1h6a1 1 0 011 1v3"/>
                </svg>
            </div>
            <div>
                <div style="font-size:15px;font-weight:700;color:#0f172a;margin-bottom:4px;" id="gdm-title">Delete?</div>
                <div style="font-size:13px;color:#64748b;line-height:1.5;" id="gdm-body">This action cannot be undone.</div>
            </div>
        </div>
        <div id="gdm-input-wrap" style="display:none;padding:0 22px 12px;">
            <label id="gdm-input-label" style="display:block;font-size:12px;font-weight:600;color:#64748b;margin-bottom:6px;"></label>
            <input id="gdm-input" type="text" autocomplete="off" spellcheck="false"
                   oninput="gdmCheckInput()"
                   style="width:100%;box-sizing:border-box;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:13px;font-weight:600;color:#0f172a;outline:none;letter-spacing:.02em;"
                   onfocus="this.style.borderColor='#dc2626'" onblur="this.style.borderColor='#e2e8f0'">
        </div>
        <div style="padding:16px 22px 20px;display:flex;gap:8px;justify-content:flex-end;">
            <button onclick="closeGlobalDeleteModal()"
                    style="padding:9px 18px;border-radius:9px;border:1.5px solid #e2e8f0;background:#fff;
                           font-size:13px;font-weight:600;color:#475569;cursor:pointer;">
                Cancel
            </button>
            <button id="gdm-confirm-btn"
                    style="padding:9px 18px;border-radius:9px;border:none;background:#dc2626;
                           font-size:13px;font-weight:600;color:#fff;cursor:pointer;
                           box-shadow:0 3px 10px rgba(220,38,38,.35);">
                Delete
            </button>
        </div>
    </div>
</div>

<script>
// ── Global delete confirmation modal ────────────────────
var _gdmForm = null;

function confirmDelete(form, title, body) {
    _gdmForm = form;
    document.getElementById('gdm-title').textContent = title || 'Delete this record?';
    document.getElementById('gdm-body').textContent  = body  || 'This action cannot be undone.';
    var modal = document.getElementById('global-delete-modal');
    modal.style.display = 'flex';
    document.getElementById('gdm-confirm-btn').onclick = function() {
        var form = _gdmForm;
        closeGlobalDeleteModal();
        form.submit();
    };
}

// Callback-based version for AJAX delete flows
function confirmAction(title, body, onConfirm) {
    document.getElementById('gdm-title').textContent = title || 'Are you sure?';
    document.getElementById('gdm-body').textContent  = body  || 'This action cannot be undone.';
    var modal = document.getElementById('global-delete-modal');
    modal.style.display = 'flex';
    document.getElementById('gdm-confirm-btn').onclick = function() {
        closeGlobalDeleteModal();
        onConfirm();
    };
}

// Type-to-confirm version
var _gdmExpected = null;
function confirmWithInput(title, body, expectedText, onConfirm) {
    document.getElementById('gdm-title').textContent = title || 'Are you sure?';
    document.getElementById('gdm-body').textContent  = body  || 'This action cannot be undone.';
    _gdmExpected = expectedText;
    var wrap  = document.getElementById('gdm-input-wrap');
    var input = document.getElementById('gdm-input');
    var btn   = document.getElementById('gdm-confirm-btn');
    document.getElementById('gdm-input-label').textContent = 'Type "' + expectedText + '" to confirm:';
    input.value = '';
    btn.disabled = true;
    btn.style.opacity = '.4';
    btn.style.cursor  = 'not-allowed';
    btn.style.boxShadow = 'none';
    wrap.style.display = 'block';
    document.getElementById('global-delete-modal').style.display = 'flex';
    btn.onclick = function() { closeGlobalDeleteModal(); onConfirm(); };
    setTimeout(function(){ input.focus(); }, 80);
}
function gdmCheckInput() {
    var val = document.getElementById('gdm-input').value;
    var btn = document.getElementById('gdm-confirm-btn');
    var ok  = _gdmExpected && val === _gdmExpected;
    btn.disabled    = !ok;
    btn.style.opacity    = ok ? '1'           : '.4';
    btn.style.cursor     = ok ? 'pointer'     : 'not-allowed';
    btn.style.boxShadow  = ok ? '0 3px 10px rgba(220,38,38,.35)' : 'none';
}

function closeGlobalDeleteModal() {
    document.getElementById('global-delete-modal').style.display = 'none';
    document.getElementById('gdm-input-wrap').style.display = 'none';
    document.getElementById('gdm-input').value = '';
    _gdmExpected = null;
    _gdmForm = null;
}

document.getElementById('global-delete-modal').addEventListener('click', function(e) {
    if (e.target === this) closeGlobalDeleteModal();
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeGlobalDeleteModal();
});
</script>

<script>
// ── Bell notifications ────────────────────────────────────
(function() {
  var bellOpen = false;

  function fetchNotifications() {
    fetch('{{ route('notifications.unread') }}', { headers: { 'Accept': 'application/json' } })
      .then(function(r){ return r.json(); })
      .then(function(data) {
        var badge = document.getElementById('bell-badge');
        var list  = document.getElementById('bell-list');
        var empty = document.getElementById('bell-empty');
        if (!badge) return;

        if (data.count > 0) {
          badge.textContent = data.count > 9 ? '9+' : data.count;
          badge.style.display = 'flex';
        } else {
          badge.style.display = 'none';
        }

        if (data.items && data.items.length > 0) {
          empty.style.display = 'none';
          var html = '';
          data.items.forEach(function(n) {
            html += '<a href="' + n.go_url + '" style="display:block;padding:12px 16px;border-bottom:1px solid #f8fafc;text-decoration:none;transition:background .1s;" onmouseover="this.style.background=\'#f8fafc\'" onmouseout="this.style.background=\'transparent\'">';
            html += '<div style="font-size:12px;font-weight:600;color:#0f172a;line-height:1.4;">' + n.message + '</div>';
            html += '<div style="font-size:11px;color:#94a3b8;margin-top:2px;">' + n.ago + '</div>';
            html += '</a>';
          });
          list.innerHTML = html + '<div id="bell-empty" style="display:none;"></div>';
        } else {
          list.innerHTML = '<div style="padding:24px;text-align:center;color:#94a3b8;font-size:13px;" id="bell-empty">No new notifications</div>';
        }
      }).catch(function(){});
  }

  window.toggleBellDropdown = function() {
    var dd = document.getElementById('bell-dropdown');
    bellOpen = !bellOpen;
    dd.style.display = bellOpen ? 'block' : 'none';
    if (bellOpen) fetchNotifications();
  };

  window.markAllRead = function() {
    var CSRF = document.querySelector('meta[name="csrf-token"]').content;
    fetch('{{ route('notifications.read-all') }}', {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
    }).then(function() {
      document.getElementById('bell-badge').style.display = 'none';
      document.getElementById('bell-dropdown').style.display = 'none';
      bellOpen = false;
    });
  };

  // Close dropdown on outside click
  document.addEventListener('click', function(e) {
    var wrap = document.getElementById('bell-wrap');
    if (wrap && !wrap.contains(e.target)) {
      document.getElementById('bell-dropdown').style.display = 'none';
      bellOpen = false;
    }
  });

  // Poll every 30 seconds
  fetchNotifications();
  setInterval(fetchNotifications, 30000);
}());
</script>

<script>
function toggleSidebar() {
    var s = document.getElementById('sidebar');
    var o = document.getElementById('sidebar-overlay');
    var open = s.style.transform === '';
    s.style.transform = open ? 'translateX(-260px)' : '';
    o.style.display = open ? 'none' : 'block';
}
document.getElementById('sidebar-overlay').addEventListener('click', function() {
    document.getElementById('sidebar').style.transform = 'translateX(-260px)';
    this.style.display = 'none';
});
// Show mobile toggle on small screens
if (window.innerWidth < 1024) {
    document.getElementById('sidebar').style.transform = 'translateX(-260px)';
    document.getElementById('sidebar-toggle').style.display = 'block';
}
window.addEventListener('resize', function() {
    if (window.innerWidth >= 1024) {
        document.getElementById('sidebar').style.transform = '';
        document.getElementById('sidebar').style.position = 'fixed';
        document.getElementById('sidebar-toggle').style.display = 'none';
        document.getElementById('sidebar-overlay').style.display = 'none';
    } else {
        document.getElementById('sidebar-toggle').style.display = 'block';
    }
});
</script>
</body>
</html>
