@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')

{{-- Page header --}}
<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-800">Dashboard</h1>
    <p class="text-slate-500 text-sm mt-1">Welcome back, {{ Auth::user()->name }}. Here's what's happening today.</p>
</div>

{{-- KPI Cards --}}
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-4 mb-8">

    <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200 flex items-start gap-4">
        <div class="w-11 h-11 rounded-xl bg-blue-100 flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
        <div class="min-w-0">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Sales</p>
            <p class="text-2xl font-bold text-slate-800 mt-1 truncate">{{ number_format($totalSales ?? 0, 0) }}</p>
            <p class="text-xs text-slate-400 mt-0.5">All time invoiced</p>
        </div>
    </div>

    <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200 flex items-start gap-4">
        <div class="w-11 h-11 rounded-xl bg-emerald-100 flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
            </svg>
        </div>
        <div class="min-w-0">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Inventory Value</p>
            <p class="text-2xl font-bold text-slate-800 mt-1 truncate">{{ number_format($inventoryValue ?? 0, 0) }}</p>
            <p class="text-xs text-slate-400 mt-0.5">All warehouses</p>
        </div>
    </div>

    <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200 flex items-start gap-4">
        <div class="w-11 h-11 rounded-xl bg-orange-100 flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
        </div>
        <div>
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Production Active</p>
            <p class="text-2xl font-bold text-slate-800 mt-1">{{ $productionInProgress ?? 0 }}</p>
            <p class="text-xs text-slate-400 mt-0.5">Orders in progress</p>
        </div>
    </div>

    <a href="/app/purchase/pipeline"
       class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200 flex items-start gap-4 hover:border-amber-300 hover:shadow-md transition-all duration-200 group">
        <div class="w-11 h-11 rounded-xl bg-amber-100 group-hover:bg-amber-200 flex items-center justify-center flex-shrink-0 transition-colors">
            <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
        <div>
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Purchase Pipeline</p>
            <p class="text-2xl font-bold text-slate-800 mt-1">{{ $purchasePending ?? 0 }}</p>
            <p class="text-xs text-slate-400 mt-0.5">Active pipelines</p>
        </div>
    </a>

    <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200 flex items-start gap-4">
        <div class="w-11 h-11 rounded-xl bg-red-100 flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
        </div>
        <div class="min-w-0">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Receivables</p>
            <p class="text-2xl font-bold text-red-600 mt-1 truncate">{{ number_format($outstandingReceivables ?? 0, 0) }}</p>
            <p class="text-xs text-slate-400 mt-0.5">Outstanding</p>
        </div>
    </div>

</div>

{{-- Quick Actions --}}
<div class="mb-6">
    <h2 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-3">Quick Actions</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">

        <a href="{{ route('purchase.requests.create') }}"
           class="group bg-white rounded-xl p-4 border border-slate-200 hover:border-amber-300 hover:shadow-md transition-all duration-200 flex items-center gap-4">
            <div class="w-10 h-10 rounded-lg bg-amber-50 group-hover:bg-amber-100 flex items-center justify-center flex-shrink-0 transition-colors">
                <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
            </div>
            <div>
                <p class="text-sm font-semibold text-slate-700 group-hover:text-amber-700">New Purchase Request</p>
                <p class="text-xs text-slate-400 mt-0.5">Raise a PR for approval</p>
            </div>
        </a>

        <a href="/app/sales/orders"
           class="group bg-white rounded-xl p-4 border border-slate-200 hover:border-violet-300 hover:shadow-md transition-all duration-200 flex items-center gap-4">
            <div class="w-10 h-10 rounded-lg bg-violet-50 group-hover:bg-violet-100 flex items-center justify-center flex-shrink-0 transition-colors">
                <svg class="w-5 h-5 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
            </div>
            <div>
                <p class="text-sm font-semibold text-slate-700 group-hover:text-violet-700">New Sales Order</p>
                <p class="text-xs text-slate-400 mt-0.5">Create order for a customer</p>
            </div>
        </a>

        <a href="/app/production/orders"
           class="group bg-white rounded-xl p-4 border border-slate-200 hover:border-orange-300 hover:shadow-md transition-all duration-200 flex items-center gap-4">
            <div class="w-10 h-10 rounded-lg bg-orange-50 group-hover:bg-orange-100 flex items-center justify-center flex-shrink-0 transition-colors">
                <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
            </div>
            <div>
                <p class="text-sm font-semibold text-slate-700 group-hover:text-orange-700">New Production Order</p>
                <p class="text-xs text-slate-400 mt-0.5">Schedule a production run</p>
            </div>
        </a>

        <a href="/app/inventory/reports/low-stock"
           class="group bg-white rounded-xl p-4 border border-slate-200 hover:border-red-300 hover:shadow-md transition-all duration-200 flex items-center gap-4">
            <div class="w-10 h-10 rounded-lg bg-red-50 group-hover:bg-red-100 flex items-center justify-center flex-shrink-0 transition-colors">
                <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>
            <div>
                <p class="text-sm font-semibold text-slate-700 group-hover:text-red-600">Low Stock Alert</p>
                <p class="text-xs text-slate-400 mt-0.5">Items below minimum level</p>
            </div>
        </a>

    </div>
</div>

{{-- Module Shortcuts --}}
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="bg-gradient-to-r from-amber-500 to-amber-400 px-5 py-4">
            <h3 class="text-white font-semibold text-sm">Purchase</h3>
            <p class="text-amber-100 text-xs mt-0.5">Procurement workflow</p>
        </div>
        <div class="p-4 space-y-1">
            <a href="{{ route('purchase.requests.index') }}" class="flex items-center justify-between py-1.5 text-sm text-slate-600 hover:text-amber-600 transition-colors">
                <span>Purchase Requests</span>
                <svg class="w-3.5 h-3.5 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
            <a href="/app/purchase/orders" class="flex items-center justify-between py-1.5 text-sm text-slate-600 hover:text-amber-600 transition-colors">
                <span>Purchase Orders</span>
                <svg class="w-3.5 h-3.5 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
            <a href="{{ route('purchase.grns.index') }}" class="flex items-center justify-between py-1.5 text-sm text-slate-600 hover:text-amber-600 transition-colors">
                <span>Goods Receipt (GRN)</span>
                <svg class="w-3.5 h-3.5 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="bg-gradient-to-r from-emerald-500 to-emerald-400 px-5 py-4">
            <h3 class="text-white font-semibold text-sm">Inventory</h3>
            <p class="text-emerald-100 text-xs mt-0.5">Stock management</p>
        </div>
        <div class="p-4 space-y-1">
            <a href="/app/inventory/items" class="flex items-center justify-between py-1.5 text-sm text-slate-600 hover:text-emerald-600 transition-colors">
                <span>Item Master</span>
                <svg class="w-3.5 h-3.5 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
            <a href="/app/inventory/reports/summary" class="flex items-center justify-between py-1.5 text-sm text-slate-600 hover:text-emerald-600 transition-colors">
                <span>Stock Summary</span>
                <svg class="w-3.5 h-3.5 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
            <a href="/app/inventory/reports/valuation" class="flex items-center justify-between py-1.5 text-sm text-slate-600 hover:text-emerald-600 transition-colors">
                <span>Inventory Valuation</span>
                <svg class="w-3.5 h-3.5 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="bg-gradient-to-r from-orange-500 to-orange-400 px-5 py-4">
            <h3 class="text-white font-semibold text-sm">Production</h3>
            <p class="text-orange-100 text-xs mt-0.5">Manufacturing operations</p>
        </div>
        <div class="p-4 space-y-1">
            <a href="/app/production/orders" class="flex items-center justify-between py-1.5 text-sm text-slate-600 hover:text-orange-600 transition-colors">
                <span>Production Orders</span>
                <svg class="w-3.5 h-3.5 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
            <a href="/app/production/bom" class="flex items-center justify-between py-1.5 text-sm text-slate-600 hover:text-orange-600 transition-colors">
                <span>Bill of Materials</span>
                <svg class="w-3.5 h-3.5 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
            <a href="/app/production/material-issues" class="flex items-center justify-between py-1.5 text-sm text-slate-600 hover:text-orange-600 transition-colors">
                <span>Material Issues</span>
                <svg class="w-3.5 h-3.5 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="bg-gradient-to-r from-violet-500 to-violet-400 px-5 py-4">
            <h3 class="text-white font-semibold text-sm">Sales</h3>
            <p class="text-violet-100 text-xs mt-0.5">Customer & revenue</p>
        </div>
        <div class="p-4 space-y-1">
            <a href="/app/sales/orders" class="flex items-center justify-between py-1.5 text-sm text-slate-600 hover:text-violet-600 transition-colors">
                <span>Sales Orders</span>
                <svg class="w-3.5 h-3.5 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
            <a href="/app/sales/invoices" class="flex items-center justify-between py-1.5 text-sm text-slate-600 hover:text-violet-600 transition-colors">
                <span>Sales Invoices</span>
                <svg class="w-3.5 h-3.5 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
            <a href="/app/sales/customers" class="flex items-center justify-between py-1.5 text-sm text-slate-600 hover:text-violet-600 transition-colors">
                <span>Customers</span>
                <svg class="w-3.5 h-3.5 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>
    </div>

</div>

@endsection
