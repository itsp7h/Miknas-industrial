<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>LPO {{ $order->po_number ?? 'PO-' . str_pad($order->id, 5, '0', STR_PAD_LEFT) }}</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
        font-family: 'DejaVu Sans', 'Arial', sans-serif;
        font-size: 11px;
        color: #1e293b;
        background: #f1f5f9;
    }

    .sheet {
        max-width: 820px;
        margin: 24px auto;
        background: #fff;
        box-shadow: 0 4px 24px rgba(0,0,0,.08);
        padding: 32px;
    }

    .header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 18px;
    }
    .brand { display: flex; align-items: center; gap: 10px; }
    .brand-box {
        width: 40px; height: 40px; border-radius: 6px;
        background: #16a34a;
        display: flex; align-items: center; justify-content: center;
    }
    .brand-name { font-size: 19px; font-weight: 700; color: #0f172a; }
    .brand-sub  { font-size: 10px; color: #64748b; margin-top: 1px; }

    .doc-title { font-size: 22px; font-weight: 700; color: #64748b; text-align: right; }

    .meta-table { margin-top: 8px; border-collapse: collapse; }
    .meta-table td { padding: 3px 0; font-size: 10.5px; text-align: right; }
    .meta-table td.label { color: #475569; padding-right: 10px; white-space: nowrap; }
    .meta-table td.value {
        background: #fef3c7; border: 1px solid #fde68a; border-radius: 3px;
        padding: 3px 10px; font-weight: 700; color: #0f172a; min-width: 110px;
    }

    .parties { display: flex; justify-content: space-between; gap: 20px; margin-bottom: 16px; }
    .party { width: 48%; }
    .party-title {
        font-size: 11px; font-weight: 700; color: #0f172a; text-transform: uppercase;
        letter-spacing: .04em; border-bottom: 1.5px solid #1e293b; padding-bottom: 4px; margin-bottom: 8px;
    }
    .party-name { font-size: 12.5px; font-weight: 700; color: #0f172a; }
    .party-line { font-size: 10.5px; color: #475569; margin-top: 2px; }
    .site-box {
        margin-top: 10px; padding: 8px 10px; background: #f1f5f9; border-radius: 4px; text-align: center;
    }
    .site-label { font-size: 9px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .05em; }
    .site-value { font-size: 13px; font-weight: 700; color: #0f172a; margin-top: 2px; }

    table.items { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
    table.items th {
        background: #1e293b; color: #fff; font-size: 9.5px; font-weight: 600;
        text-transform: uppercase; letter-spacing: .04em; padding: 7px 8px; text-align: left;
    }
    table.items th.text-right { text-align: right; }
    table.items td { padding: 6px 8px; border-bottom: 1px solid #e2e8f0; font-size: 10.5px; color: #334155; }
    table.items td.text-right { text-align: right; }
    table.items tbody tr:nth-child(even) { background: #f8fafc; }

    .bottom { display: flex; justify-content: space-between; gap: 20px; }
    .notes-box { width: 55%; }
    .notes-title { font-size: 10.5px; font-weight: 700; color: #0f172a; margin-bottom: 6px; }
    .notes-body {
        border: 1px solid #e2e8f0; border-radius: 4px; padding: 10px; min-height: 70px;
        font-size: 10.5px; color: #475569;
    }

    .summary { width: 40%; }
    .summary-table { width: 100%; border-collapse: collapse; }
    .summary-table td { padding: 5px 0; font-size: 11px; }
    .summary-table td.label { color: #64748b; }
    .summary-table td.value { text-align: right; font-weight: 600; color: #0f172a; }
    .summary-table tr.total td { border-top: 2px solid #1e293b; padding-top: 8px; font-size: 14px; font-weight: 700; color: #0f172a; }

    .signatures { display: flex; justify-content: space-between; margin-top: 40px; }
    .sig-block { width: 45%; }
    .sig-line { border-top: 1px solid #94a3b8; padding-top: 4px; font-size: 10px; color: #64748b; text-align: center; }
    .sig-name { font-size: 11px; font-weight: 600; color: #0f172a; margin-bottom: 26px; text-align: center; }

    .disclaimer { text-align: center; font-size: 11px; font-weight: 700; margin-top: 24px; text-decoration: underline; }
    .disclaimer-sub { text-align: center; font-size: 9.5px; color: #64748b; margin-top: 2px; }

    .footer { margin-top: 18px; padding-top: 10px; border-top: 1px solid #e2e8f0; text-align: center; font-size: 9px; color: #94a3b8; }

    .print-bar {
        max-width: 820px;
        margin: 16px auto 0;
        text-align: right;
    }
    .print-btn {
        padding: 10px 20px;
        background: #16a34a;
        color: #fff;
        border: none;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
    }

    @media print {
        body { background: #fff; }
        .sheet { box-shadow: none; margin: 0; max-width: 100%; }
        .print-bar { display: none; }
    }
</style>
</head>
<body>

<div class="print-bar">
    <button class="print-btn" onclick="window.print()">🖨 Print</button>
</div>

<div class="sheet">

    <div class="header">
        <div class="brand">
            <div class="brand-box">
                <svg width="20" height="20" fill="none" stroke="#fff" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
            </div>
            <div>
                <div class="brand-name">{{ $company->name ?? 'SteelERP' }}</div>
                <div class="brand-sub">{{ $order->purchaseRequest->project_name ?? 'Manufacturing & Trading' }}</div>
            </div>
        </div>
        <div>
            <div class="doc-title">Purchase Order</div>
            <table class="meta-table" align="right">
                <tr>
                    <td class="label">Date</td>
                    <td class="value">{{ $order->po_date ? \Carbon\Carbon::parse($order->po_date)->format('d/M/y') : '—' }}</td>
                </tr>
                <tr>
                    <td class="label">P.O. Number</td>
                    <td class="value">{{ $order->po_number ?? 'PO-' . str_pad($order->id, 5, '0', STR_PAD_LEFT) }}</td>
                </tr>
                <tr>
                    <td class="label">Req. No</td>
                    <td class="value">{{ $order->purchaseRequest->request_number ?? '—' }}</td>
                </tr>
            </table>
        </div>
    </div>

    <div class="parties">
        <div class="party">
            <div class="party-title">Vendor</div>
            <div class="party-name">{{ $order->supplier->name ?? '—' }}</div>
            @if($order->supplier?->contact_person)
                <div class="party-line">{{ $order->supplier->contact_person }}</div>
            @endif
            @if($order->supplier?->address)
                <div class="party-line">{{ $order->supplier->address }}</div>
            @endif
            @if($order->supplier?->phone)
                <div class="party-line">P: {{ $order->supplier->phone }}</div>
            @endif
            @if($order->supplier?->email)
                <div class="party-line">{{ $order->supplier->email }}</div>
            @endif
        </div>
        <div class="party">
            <div class="party-title">Ship To</div>
            @if($order->purchaseRequest?->requested_by_name)
                <div class="party-name">{{ $order->purchaseRequest->requested_by_name }}</div>
            @endif
            @if($order->purchaseRequest?->department)
                <div class="party-line">{{ $order->purchaseRequest->department }}</div>
            @endif
            @if($order->purchaseRequest?->location)
                <div class="party-line">{{ $order->purchaseRequest->location }}</div>
            @endif
            @if($order->purchaseRequest?->project_name)
            <div class="site-box">
                <div class="site-label">Site / Project</div>
                <div class="site-value">{{ $order->purchaseRequest->project_name }}</div>
            </div>
            @endif
        </div>
    </div>

    <table class="items">
        <thead>
            <tr>
                <th style="width:14%;">Code</th>
                <th style="width:38%;">Product Description</th>
                <th style="width:10%;" class="text-right">Qty</th>
                <th style="width:10%;" class="text-right">UOM</th>
                <th style="width:14%;" class="text-right">U.Price</th>
                <th style="width:14%;" class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($order->items as $item)
            <tr>
                <td>{{ $item->item->item_code ?? '—' }}</td>
                <td>{{ $item->item->item_name ?? '—' }}</td>
                <td class="text-right">{{ number_format($item->quantity, 2) }}</td>
                <td class="text-right">{{ $item->item->unit_of_measure ?? '' }}</td>
                <td class="text-right">{{ number_format($item->rate, 3) }}</td>
                <td class="text-right">{{ number_format($item->total_amount, 3) }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="6" style="text-align:center;padding:20px;color:#94a3b8;">No items on this order.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="bottom">
        <div class="notes-box">
            <div class="notes-title">Notes and Instructions</div>
            <div class="notes-body">{{ $order->notes ?? '—' }}</div>
        </div>
        <div class="summary">
            <table class="summary-table">
                <tr>
                    <td class="label">Subtotal</td>
                    <td class="value">BHD {{ number_format($subtotal, 3) }}</td>
                </tr>
                <tr>
                    <td class="label">VAT Rate</td>
                    <td class="value">{{ rtrim(rtrim(number_format($vatRate, 2), '0'), '.') }}%</td>
                </tr>
                <tr>
                    <td class="label">VAT</td>
                    <td class="value">BHD {{ number_format($vatAmount, 3) }}</td>
                </tr>
                <tr>
                    <td class="label">Discount</td>
                    <td class="value">BHD {{ number_format($discount, 3) }}</td>
                </tr>
                <tr class="total">
                    <td class="label">Total</td>
                    <td class="value">BHD {{ number_format($total, 3) }}</td>
                </tr>
            </table>
        </div>
    </div>

    <div class="signatures">
        <div class="sig-block">
            <div class="sig-name">{{ $order->createdBy->name ?? '—' }}</div>
            <div class="sig-line">Prepared By</div>
        </div>
        <div class="sig-block">
            <div class="sig-name">&nbsp;</div>
            <div class="sig-line">Approved By</div>
        </div>
    </div>

    <div class="disclaimer">This is not a Tax Invoice!</div>
    <div class="disclaimer-sub">FOR {{ strtoupper($company->name ?? 'SteelERP') }}</div>

    <div class="footer">
        Should you have any enquiries concerning this purchase order, please contact {{ $order->createdBy->name ?? 'us' }}.
    </div>

</div>

</body>
</html>
