{{--
    The LPO document itself — the one layout both the on-screen copy (print)
    and the downloaded/emailed PDF render, so the two cannot drift apart.
    It is written for DomPDF, which has no flexbox, no inline <svg> and only
    regular and bold DejaVu Sans: tables for layout, the icon as an image,
    and weights of 400 or 700 only. A browser draws all of that the same way.
--}}
<style>
    .header { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
    .header td { vertical-align: top; padding: 0; }
    .brand-box {
        width: 40px; height: 40px; border-radius: 6px;
        background: #16a34a; text-align: center; padding: 10px 0 0;
    }
    .brand-logo { display: block; }
    .brand-name { font-size: 19px; font-weight: 700; color: #0f172a; }
    .brand-sub  { font-size: 10px; color: #64748b; margin-top: 1px; }

    .doc-title { font-size: 22px; font-weight: 700; color: #64748b; text-align: right; }

    .meta-table { margin: 8px 0 0 auto; border-collapse: collapse; }
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
        background: #1e293b; color: #fff; font-size: 9.5px; font-weight: 700;
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
        /* content-box: DomPDF adds padding to a min-height whatever
           box-sizing says, so say it for the browser too. */
        box-sizing: content-box;
        border: 1px solid #e2e8f0; border-radius: 4px; padding: 10px; min-height: 48px;
        font-size: 10.5px; color: #475569;
    }

    .summary { width: 40%; }
    .summary-table { width: 100%; border-collapse: collapse; }
    .summary-table td { padding: 5px 0; font-size: 11px; }
    .summary-table td.label { color: #64748b; }
    .summary-table td.value { text-align: right; font-weight: 700; color: #0f172a; }
    .summary-table tr.total td { border-top: 2px solid #1e293b; padding-top: 8px; font-size: 14px; font-weight: 700; color: #0f172a; }

    .unit-note { font-size: 8.5px; color: #b45309; margin-top: 2px; line-height: 1.3; }
    .signatures { width: 100%; border-collapse: collapse; margin-top: 40px; }
    .signatures td { vertical-align: top; padding: 0; }
    /* Both blocks draw the same fixed-height area above their line, content
       at its foot — the signature and name in one, the company's stamp in
       the other — so the two lines stay level, signed or not, stamped or
       not. A table cell, because DomPDF honours a cell's height and
       vertical-align where it would not a div's. */
    .sig-area { width: 100%; border-collapse: collapse; }
    .sig-area td { height: 100px; padding: 0 0 6px; vertical-align: bottom; text-align: center; }
    .sig-img { max-height: 60px; max-width: 200px; }
    .sig-stamp { display: inline-block; }
    .sig-area td.sig-stamp-cell { width: 150px; }
    .sig-with-stamp .sig-img { max-width: 160px; }
    .sig-block { width: 45%; }
    .sig-gap { width: 10%; }
    .sig-line { border-top: 1px solid #94a3b8; padding-top: 4px; font-size: 10px; color: #64748b; text-align: center; }
    .sig-name { font-size: 11px; font-weight: 700; color: #0f172a; margin-top: 4px; text-align: center; }

    .disclaimer { text-align: center; font-size: 11px; font-weight: 700; margin-top: 24px; text-decoration: underline; }
    .disclaimer-sub { text-align: center; font-size: 9.5px; color: #64748b; margin-top: 2px; }

    .footer { margin-top: 18px; padding-top: 10px; border-top: 1px solid #e2e8f0; text-align: center; font-size: 9px; color: #94a3b8; }
</style>

<table class="header">
    <tr>
        <td>
            <table style="border-collapse:collapse;">
                <tr>
                    @if(! empty($logo))
                    {{-- The company's logo (Settings → Companies), fitted to 160×56. --}}
                    <td style="padding:0;vertical-align:middle;">
                        <img class="brand-logo" src="{{ $logo['src'] }}" width="{{ $logo['width'] }}" height="{{ $logo['height'] }}" alt="{{ $company->name }}">
                    </td>
                    @else
                    <td style="width:40px;padding:0;">
                        <div class="brand-box">
                            <img src="data:image/svg+xml;base64,{{ base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="#fff" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>') }}" width="20" height="20" alt="">
                        </div>
                    </td>
                    @endif
                    <td style="padding:0 0 0 10px;vertical-align:middle;">
                        <div class="brand-name">{{ $company->name ?? 'SteelERP' }}</div>
                        <div class="brand-sub">{{ $order->purchaseRequest->company_name ?? 'Manufacturing & Trading' }}</div>
                    </td>
                </tr>
            </table>
        </td>
        <td style="text-align:right;">
            <div class="doc-title">Purchase Order</div>
            <table class="meta-table">
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
            {{-- The supplier's quotation number, taken from their quote when
                 the LPO was generated. --}}
            @if($order->quote_reference)
                <div class="party-line"><strong>Ref:</strong> {{ $order->quote_reference }}</div>
            @endif
            {{-- Name, email and phone first: who the LPO is to and how to reach
                 them. Every number the supplier has, in the order the
                 Suppliers list shows them, each once. --}}
            @php
                $vendorPhones = collect([$order->supplier?->phone, $order->supplier?->phone2, $order->supplier?->whatsapp])
                    ->map(fn ($p) => trim((string) $p))->filter()->unique()->values();
            @endphp
            @if($order->supplier?->email)
                <div class="party-line">{{ $order->supplier->email }}</div>
            @endif
            @if($vendorPhones->isNotEmpty())
                <div class="party-line">P: {{ $vendorPhones->implode(' / ') }}</div>
            @endif
            @if($order->supplier?->contact_person)
                <div class="party-line">{{ $order->supplier->contact_person }}</div>
            @endif
            @if($order->supplier?->address)
                <div class="party-line">{{ $order->supplier->address }}</div>
            @endif
        </td>
        <td class="party-gap"></td>
        <td class="party">
            <div class="party-title">Ship To</div>
            @if($order->purchaseRequest?->requested_by_name)
                <div class="party-name">{{ $order->purchaseRequest->requested_by_name }}</div>
            @endif
            @if(! empty($shipToPhones))
                <div class="party-line">P: {{ implode(' / ', $shipToPhones) }}</div>
            @endif
            @if($order->purchaseRequest?->department)
                <div class="party-line">{{ $order->purchaseRequest->department }}</div>
            @endif
            @if($order->purchaseRequest?->location)
                <div class="party-line">{{ $order->purchaseRequest->location }}</div>
            @endif
            {{-- The address saved for that location under Settings → Projects. --}}
            @if(! empty($shipToAddress))
                <div class="party-line">{{ $shipToAddress }}</div>
            @endif
            {{-- The MPR's project, not its company: the company already heads the
                 letterhead, and the site is where the goods are for. --}}
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
        {{-- Quoted in the supplier's own unit: the order is in theirs (what
             they deliver and invoice), with ours beside it (what the GRN
             receives and the stock counts). --}}
        @if ($item->inSupplierUnit())
        <tr>
            <td>{{ $item->item->item_code ?? '—' }}</td>
            <td>
                {{ $item->item->item_name ?? '—' }}
                <div class="unit-note">
                    Supplier's unit: {{ \App\Support\SupplierUnit::describe($item->supplier_unit, $item->unit_factor, $item->system_unit) }}
                    &middot; {{ \App\Support\SupplierUnit::number($item->supplier_quantity) }} {{ $item->supplier_unit }}
                    = {{ \App\Support\SupplierUnit::number((float) $item->quantity) }} {{ $item->system_unit }} in our system
                </div>
            </td>
            <td class="text-right">{{ number_format($item->supplier_quantity, 2) }}</td>
            <td class="text-right">
                {{ $item->supplier_unit }}
                <div class="unit-note">ours: {{ $item->system_unit }}</div>
            </td>
            <td class="text-right">{{ number_format($item->supplier_rate, 3) }}</td>
            <td class="text-right">{{ number_format($item->total_amount, 3) }}</td>
        </tr>
        @else
        <tr>
            <td>{{ $item->item->item_code ?? '—' }}</td>
            <td>{{ $item->item->item_name ?? '—' }}</td>
            <td class="text-right">{{ number_format($item->quantity, 2) }}</td>
            <td class="text-right">{{ $item->item->unit_of_measure ?? '' }}</td>
            <td class="text-right">{{ number_format($item->rate, 3) }}</td>
            <td class="text-right">{{ number_format($item->total_amount, 3) }}</td>
        </tr>
        @endif
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
            <table class="sig-area{{ empty($stamp) ? '' : ' sig-with-stamp' }}"><tr>
                <td>
                    {{-- The issuer's signature as it was when the LPO was issued. --}}
                    @if ($order->prepared_signature)
                        <img class="sig-img" src="{{ $order->prepared_signature }}" alt="Signature">
                    @endif
                    <div class="sig-name">{{ $order->createdBy->name ?? '—' }}</div>
                </td>
                {{-- The company's stamp (Settings → Companies), fitted to 150×90,
                     beside the issuer's signature. --}}
                @if (! empty($stamp))
                    <td class="sig-stamp-cell">
                        <img class="sig-stamp" src="{{ $stamp['src'] }}" width="{{ $stamp['width'] }}" height="{{ $stamp['height'] }}" alt="{{ $company->name }} stamp">
                    </td>
                @endif
            </tr></table>
            <div class="sig-line">Prepared By</div>
        </td>
        <td class="sig-gap"></td>
        <td class="sig-block">
            <table class="sig-area"><tr>
                <td>
                    {{-- The approver's signature as it was when they approved it.
                         Empty until then — and an unapproved LPO is never sent. --}}
                    @if ($order->approved_signature)
                        <img class="sig-img" src="{{ $order->approved_signature }}" alt="Signature">
                    @endif
                    @if ($order->approved_at)
                        <div class="sig-name">{{ $order->approvedBy->name ?? '—' }}</div>
                    @endif
                </td>
            </tr></table>
            <div class="sig-line">Approved By</div>
        </td>
    </tr>
</table>

<div class="disclaimer">This is not a Tax Invoice!</div>
<div class="disclaimer-sub">FOR {{ strtoupper($company->name ?? 'SteelERP') }}</div>

{{-- The person on System → Requested By who asked for the goods, and their
     numbers — the same lookup as Ship To. An MPR with no requester falls
     back to whoever issued the LPO. --}}
<div class="footer">
    @if($order->purchaseRequest?->requested_by_name)
        Should you have any enquiries concerning this purchase order, please contact {{ $order->purchaseRequest->requested_by_name }}@if(! empty($shipToPhones)) on {{ implode(' / ', $shipToPhones) }}@endif.
    @else
        Should you have any enquiries concerning this purchase order, please contact {{ $order->createdBy->name ?? 'us' }}.
    @endif
</div>
