<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $order->po_number ?? 'PO-' . str_pad($order->id, 5, '0', STR_PAD_LEFT) }}</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
        font-family: 'DejaVu Sans', 'Arial', sans-serif;
        font-size: 11px;
        color: #1e293b;
        padding: 2cm 1.6cm;
    }

    .header { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
    .header td { vertical-align: top; padding: 0; }
    .brand-box {
        width: 40px; height: 40px; border-radius: 6px;
        background: #16a34a; text-align: center; padding: 10px 0 0;
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

    .parties { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
    .parties td { vertical-align: top; padding: 0; }
    .party { width: 48%; }
    .party-gap { width: 4%; }
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

    .bottom { width: 100%; border-collapse: collapse; }
    .bottom td { vertical-align: top; padding: 0; }
    .notes-box { width: 55%; }
    .bottom-gap { width: 5%; }
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

    .signatures { width: 100%; border-collapse: collapse; margin-top: 40px; }
    .signatures td { vertical-align: top; padding: 0; }
    .sig-block { width: 45%; }
    .sig-gap { width: 10%; }
    .sig-line { border-top: 1px solid #94a3b8; padding-top: 4px; font-size: 10px; color: #64748b; text-align: center; }
    .sig-name { font-size: 11px; font-weight: 600; color: #0f172a; margin-bottom: 26px; text-align: center; }

    .disclaimer { text-align: center; font-size: 11px; font-weight: 700; margin-top: 24px; text-decoration: underline; }
    .disclaimer-sub { text-align: center; font-size: 9.5px; color: #64748b; margin-top: 2px; }

    .footer { margin-top: 18px; padding-top: 10px; border-top: 1px solid #e2e8f0; text-align: center; font-size: 9px; color: #94a3b8; }
</style>
</head>
<body>

<table class="header">
    <tr>
        <td>
            <table style="border-collapse:collapse;">
                <tr>
                    <td style="width:40px;padding:0;">
                        <div class="brand-box">
                            <svg width="20" height="20" fill="none" stroke="#fff" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                        </div>
                    </td>
                    <td style="padding:0 0 0 10px;vertical-align:middle;">
                        <div class="brand-name">{{ $company->name ?? 'SteelERP' }}</div>
                        <div class="brand-sub">{{ $order->purchaseRequest->project_name ?? 'Manufacturing & Trading' }}</div>
                    </td>
                </tr>
            </table>
        </td>
        <td style="text-align:right;">
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
        </td>
    </tr>
</table>

<table class="parties">
    <tr>
        <td class="party">
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
        </td>
        <td class="party-gap"></td>
        <td class="party">
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
        </td>
    </tr>
</table>

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

<table class="bottom">
    <tr>
        <td class="notes-box">
            <div class="notes-title">Notes and Instructions</div>
            <div class="notes-body">{{ $order->notes ?? '—' }}</div>
        </td>
        <td class="bottom-gap"></td>
        <td class="summary">
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
        </td>
    </tr>
</table>

<table class="signatures">
    <tr>
        <td class="sig-block">
            <div class="sig-name">{{ $order->createdBy->name ?? '—' }}</div>
            <div class="sig-line">Prepared By</div>
        </td>
        <td class="sig-gap"></td>
        <td class="sig-block">
            <div class="sig-name">&nbsp;</div>
            <div class="sig-line">Approved By</div>
        </td>
    </tr>
</table>

<div class="disclaimer">This is not a Tax Invoice!</div>
<div class="disclaimer-sub">FOR {{ strtoupper($company->name ?? 'SteelERP') }}</div>

<div class="footer">
    Should you have any enquiries concerning this purchase order, please contact {{ $order->createdBy->name ?? 'us' }}.
</div>

</body>
</html>
