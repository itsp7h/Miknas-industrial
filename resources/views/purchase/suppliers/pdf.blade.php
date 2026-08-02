<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Suppliers List</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
        font-family: 'DejaVu Sans', 'Arial', sans-serif;
        font-size: 11px;
        color: #1e293b;
        background: #fff;
    }

    /* ── Header ── */
    .header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        padding: 24px 28px 16px;
        border-bottom: 2px solid #2563eb;
        margin-bottom: 20px;
    }
    .brand { display: flex; align-items: center; gap: 10px; }
    .brand-box {
        width: 36px; height: 36px; border-radius: 8px;
        background: #2563eb;
        display: flex; align-items: center; justify-content: center;
    }
    .brand-name { font-size: 17px; font-weight: 700; color: #0f172a; }
    .brand-sub  { font-size: 9px; color: #64748b; margin-top: 1px; }

    .report-info { text-align: right; }
    .report-title { font-size: 15px; font-weight: 700; color: #1e293b; }
    .report-meta  { font-size: 9px; color: #64748b; margin-top: 3px; }

    /* ── Summary strip ── */
    .summary {
        display: flex;
        gap: 12px;
        margin: 0 28px 16px;
    }
    .summary-card {
        flex: 1;
        padding: 10px 14px;
        background: #f1f5f9;
        border-radius: 8px;
        border-left: 3px solid #2563eb;
    }
    .summary-card .val { font-size: 20px; font-weight: 700; color: #2563eb; }
    .summary-card .lbl { font-size: 9px; color: #64748b; margin-top: 2px; }
    .summary-card.green { border-color: #10b981; }
    .summary-card.green .val { color: #10b981; }
    .summary-card.gray  { border-color: #94a3b8; }
    .summary-card.gray  .val { color: #94a3b8; }

    /* ── Table ── */
    .table-wrap { padding: 0 28px; }

    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 10.5px;
    }
    thead th {
        background: #1e293b;
        color: #fff;
        font-weight: 600;
        font-size: 9px;
        letter-spacing: .06em;
        text-transform: uppercase;
        padding: 8px 10px;
        text-align: left;
    }
    tbody tr:nth-child(even) { background: #f8fafc; }
    tbody tr:nth-child(odd)  { background: #ffffff; }
    tbody td {
        padding: 7px 10px;
        color: #334155;
        border-bottom: 1px solid #e2e8f0;
        vertical-align: middle;
    }
    tbody td:first-child { font-weight: 600; color: #0f172a; }

    .badge {
        display: inline-block;
        padding: 2px 7px;
        border-radius: 20px;
        font-size: 8.5px;
        font-weight: 700;
        letter-spacing: .04em;
    }
    .badge-active   { background: #d1fae5; color: #065f46; }
    .badge-inactive { background: #f1f5f9; color: #64748b; }

    /* ── Footer ── */
    .footer {
        margin-top: 24px;
        padding: 12px 28px 0;
        border-top: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        font-size: 9px;
        color: #94a3b8;
    }
</style>
</head>
<body>

<div class="header">
    <div class="brand">
        <div class="brand-box">
            <svg width="18" height="18" fill="none" stroke="#fff" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
            </svg>
        </div>
        <div>
            <div class="brand-name">SteelERP</div>
            <div class="brand-sub">Manufacturing &amp; Trading</div>
        </div>
    </div>
    <div class="report-info">
        <div class="report-title">Supplier Directory</div>
        <div class="report-meta">Generated: {{ now()->format('d M Y, H:i') }}</div>
        <div class="report-meta">Total Records: {{ $suppliers->count() }}</div>
    </div>
</div>

@php
    $active   = $suppliers->where('is_active', true)->count();
    $inactive = $suppliers->where('is_active', false)->count();
@endphp

<div class="summary">
    <div class="summary-card">
        <div class="val">{{ $suppliers->count() }}</div>
        <div class="lbl">Total Suppliers</div>
    </div>
    <div class="summary-card green">
        <div class="val">{{ $active }}</div>
        <div class="lbl">Active</div>
    </div>
    <div class="summary-card gray">
        <div class="val">{{ $inactive }}</div>
        <div class="lbl">Inactive</div>
    </div>
</div>

<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th style="width:4%">#</th>
                <th style="width:22%">Supplier Name</th>
                <th style="width:17%">Contact Person</th>
                <th style="width:20%">Email</th>
                <th style="width:14%">Phone</th>
                <th style="width:15%">Tax Number</th>
                <th style="width:8%">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($suppliers as $i => $supplier)
            <tr>
                <td style="color:#94a3b8;">{{ $i + 1 }}</td>
                <td>{{ $supplier->name }}</td>
                <td>{{ $supplier->contact_person ?: '—' }}</td>
                <td>{{ $supplier->email ?: '—' }}</td>
                <td>{{ $supplier->phone ?: '—' }}</td>
                <td>{{ $supplier->tax_number ?: '—' }}</td>
                <td>
                    @if($supplier->is_active)
                        <span class="badge badge-active">Active</span>
                    @else
                        <span class="badge badge-inactive">Inactive</span>
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" style="text-align:center;padding:20px;color:#94a3b8;">
                    No suppliers found.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="footer">
    <span>SteelERP — Confidential</span>
    <span>Exported by: {{ Auth::user()->name ?? '—' }}</span>
</div>

</body>
</html>
