<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Inventory Items</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 10.5px; color: #1e293b; background: #fff; }

    .header {
        display: flex; justify-content: space-between; align-items: flex-start;
        padding: 22px 28px 14px; border-bottom: 2px solid #2563eb; margin-bottom: 18px;
    }
    .brand { display: flex; align-items: center; gap: 10px; }
    .brand-box { width:34px;height:34px;border-radius:8px;background:#2563eb;display:flex;align-items:center;justify-content:center; }
    .brand-name { font-size:16px;font-weight:700;color:#0f172a; }
    .brand-sub  { font-size:9px;color:#64748b;margin-top:1px; }
    .report-info { text-align:right; }
    .report-title { font-size:14px;font-weight:700;color:#1e293b; }
    .report-meta  { font-size:9px;color:#64748b;margin-top:2px; }

    .summary { display:flex;gap:10px;margin:0 28px 16px; }
    .sc { flex:1;padding:9px 12px;background:#f1f5f9;border-radius:8px;border-left:3px solid #2563eb; }
    .sc .val { font-size:18px;font-weight:700;color:#2563eb; }
    .sc .lbl { font-size:9px;color:#64748b;margin-top:1px; }
    .sc.blue  { border-color:#3b82f6; } .sc.blue .val  { color:#3b82f6; }
    .sc.green { border-color:#10b981; } .sc.green .val { color:#10b981; }
    .sc.amber { border-color:#f59e0b; } .sc.amber .val { color:#f59e0b; }
    .sc.gray  { border-color:#94a3b8; } .sc.gray .val  { color:#94a3b8; }

    .table-wrap { padding: 0 28px; }
    table { width:100%;border-collapse:collapse;font-size:10px; }
    thead th {
        background:#1e293b;color:#fff;font-weight:600;font-size:8.5px;
        letter-spacing:.06em;text-transform:uppercase;padding:7px 9px;text-align:left;
    }
    tbody tr:nth-child(even) { background:#f8fafc; }
    tbody td { padding:6px 9px;color:#334155;border-bottom:1px solid #e2e8f0;vertical-align:middle; }
    tbody td:nth-child(2) { font-weight:600;color:#0f172a; }

    .badge { display:inline-block;padding:2px 6px;border-radius:20px;font-size:8px;font-weight:700; }
    .fg   { background:#d1fae5;color:#065f46; }
    .rm   { background:#dbeafe;color:#1e40af; }
    .wip  { background:#fef3c7;color:#92400e; }
    .act  { background:#d1fae5;color:#065f46; }
    .ina  { background:#f1f5f9;color:#64748b; }

    .section-row td {
        background:#f8fafc;font-weight:700;font-size:9px;
        color:#64748b;letter-spacing:.06em;text-transform:uppercase;
        padding:5px 9px;border-bottom:1px solid #e2e8f0;
    }

    .footer { margin-top:20px;padding:10px 28px 0;border-top:1px solid #e2e8f0;display:flex;justify-content:space-between;font-size:9px;color:#94a3b8; }
</style>
</head>
<body>

<div class="header">
    <div class="brand">
        <div class="brand-box">
            <svg width="17" height="17" fill="none" stroke="#fff" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
            </svg>
        </div>
        <div>
            <div class="brand-name">SteelERP</div>
            <div class="brand-sub">Manufacturing &amp; Trading</div>
        </div>
    </div>
    <div class="report-info">
        <div class="report-title">Inventory Items List</div>
        <div class="report-meta">Generated: {{ now()->format('d M Y, H:i') }}</div>
        <div class="report-meta">Total Records: {{ $items->count() }}</div>
    </div>
</div>

@php
    $fg      = $items->where('category', 'finished_good')->count();
    $rm      = $items->where('category', 'raw_material')->count();
    $wip     = $items->where('category', 'wip')->count();
    $active  = $items->where('is_active', true)->count();
    $inactive= $items->where('is_active', false)->count();
    $grouped = $items->groupBy('category');
    $catLabels = ['finished_good' => 'Finished Goods', 'raw_material' => 'Raw Materials', 'wip' => 'Work In Progress'];
@endphp

<div class="summary">
    <div class="sc"><div class="val">{{ $items->count() }}</div><div class="lbl">Total Items</div></div>
    <div class="sc green"><div class="val">{{ $fg }}</div><div class="lbl">Finished Goods</div></div>
    <div class="sc blue"><div class="val">{{ $rm }}</div><div class="lbl">Raw Materials</div></div>
    <div class="sc amber"><div class="val">{{ $wip }}</div><div class="lbl">WIP</div></div>
    <div class="sc gray"><div class="val">{{ $inactive }}</div><div class="lbl">Inactive</div></div>
</div>

<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th style="width:9%">Code</th>
                <th style="width:32%">Item Name</th>
                <th style="width:12%">Category</th>
                <th style="width:7%">UOM</th>
                <th style="width:10%;text-align:right">Min Stock</th>
                <th style="width:10%;text-align:right">Cost Price</th>
                <th style="width:8%">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($grouped as $category => $catItems)
            <tr class="section-row">
                <td colspan="7">{{ $catLabels[$category] ?? ucfirst(str_replace('_', ' ', $category)) }}</td>
            </tr>
            @foreach($catItems as $i => $item)
            <tr>
                <td style="font-family:monospace;font-size:9px;color:#94a3b8;">{{ $item->item_code }}</td>
                <td>{{ $item->item_name }}</td>
                <td>
                    @if($item->category === 'finished_good') <span class="badge fg">Fin. Good</span>
                    @elseif($item->category === 'raw_material') <span class="badge rm">Raw Mat.</span>
                    @else <span class="badge wip">WIP</span>
                    @endif
                </td>
                <td>{{ $item->unit_of_measure }}</td>
                <td style="text-align:right">{{ number_format($item->minimum_stock_level, 2) }}</td>
                <td style="text-align:right">{{ number_format($item->cost_price, 4) }}</td>
                <td>
                    @if($item->is_active) <span class="badge act">Active</span>
                    @else <span class="badge ina">Inactive</span>
                    @endif
                </td>
            </tr>
            @endforeach
            @endforeach
            @if($items->isEmpty())
            <tr><td colspan="7" style="text-align:center;padding:20px;color:#94a3b8;">No items found.</td></tr>
            @endif
        </tbody>
    </table>
</div>

<div class="footer">
    <span>SteelERP — Confidential</span>
    <span>Exported by: {{ Auth::user()->name ?? '—' }}</span>
</div>

</body>
</html>
