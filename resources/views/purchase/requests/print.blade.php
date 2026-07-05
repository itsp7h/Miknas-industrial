<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>MPR {{ $purchaseRequest->request_number }}</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
        font-family: 'DejaVu Sans', 'Arial', sans-serif;
        font-size: 12px;
        color: #1e293b;
        background: #f1f5f9;
    }

    .sheet {
        max-width: 820px;
        margin: 24px auto;
        background: #fff;
        box-shadow: 0 4px 24px rgba(0,0,0,.08);
    }

    /* ── Header ── */
    .header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        padding: 28px 32px 18px;
        border-bottom: 2px solid #2563eb;
    }
    .brand { display: flex; align-items: center; gap: 10px; }
    .brand-box {
        width: 38px; height: 38px; border-radius: 8px;
        background: #2563eb;
        display: flex; align-items: center; justify-content: center;
    }
    .brand-name { font-size: 18px; font-weight: 700; color: #0f172a; }
    .brand-sub  { font-size: 10px; color: #64748b; margin-top: 1px; }

    .report-info { text-align: right; }
    .report-title { font-size: 16px; font-weight: 700; color: #1e293b; }
    .report-meta  { font-size: 10px; color: #64748b; margin-top: 3px; }

    .badge {
        display: inline-block;
        padding: 2px 9px;
        border-radius: 20px;
        font-size: 9.5px;
        font-weight: 700;
        letter-spacing: .04em;
        margin-top: 4px;
    }
    .badge-pending  { background: #fef3c7; color: #92400e; }
    .badge-approved { background: #d1fae5; color: #065f46; }
    .badge-rejected { background: #fee2e2; color: #991b1b; }
    .badge-ordered  { background: #dbeafe; color: #1d4ed8; }

    /* ── Info grid ── */
    .section { padding: 20px 32px; }
    .section-title {
        font-size: 10px;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: .06em;
        margin-bottom: 12px;
    }
    .info-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 14px 20px;
    }
    .info-grid .full { grid-column: 1 / -1; }
    .info-label { font-size: 9.5px; color: #94a3b8; text-transform: uppercase; letter-spacing: .04em; }
    .info-value { font-size: 12.5px; font-weight: 600; color: #0f172a; margin-top: 2px; }

    /* ── Table ── */
    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 11px;
    }
    thead th {
        background: #1e293b;
        color: #fff;
        font-weight: 600;
        font-size: 9.5px;
        letter-spacing: .05em;
        text-transform: uppercase;
        padding: 8px 10px;
        text-align: left;
    }
    tbody tr:nth-child(even) { background: #f8fafc; }
    tbody td {
        padding: 7px 10px;
        color: #334155;
        border-bottom: 1px solid #e2e8f0;
        vertical-align: middle;
    }
    tbody td:first-child { text-align: center; color: #94a3b8; width: 5%; }
    tbody td:nth-child(2) { font-weight: 600; color: #0f172a; }

    /* ── Signatures ── */
    .signatures {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 24px;
        padding: 24px 32px 8px;
    }
    .sig-box { border-top: 1px solid #cbd5e1; padding-top: 8px; }
    .sig-label { font-size: 9.5px; color: #94a3b8; text-transform: uppercase; letter-spacing: .04em; }
    .sig-name  { font-size: 12.5px; font-weight: 600; color: #0f172a; margin-top: 3px; }
    .sig-meta  { font-size: 10px; color: #64748b; margin-top: 2px; }
    .sig-img   { max-height: 60px; max-width: 220px; margin-bottom: 6px; }

    .footer {
        margin-top: 8px;
        padding: 14px 32px 24px;
        border-top: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        font-size: 9.5px;
        color: #94a3b8;
    }

    .print-bar {
        max-width: 820px;
        margin: 16px auto 0;
        text-align: right;
    }
    .print-btn {
        padding: 10px 20px;
        background: #2563eb;
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
            <div class="report-title">Material Purchase Request</div>
            <div class="report-meta">{{ $purchaseRequest->request_number }}</div>
            @php
                $badgeClass = match($purchaseRequest->status) {
                    'pending'  => 'badge-pending',
                    'approved' => 'badge-approved',
                    'rejected' => 'badge-rejected',
                    'ordered'  => 'badge-ordered',
                    default    => 'badge-pending',
                };
            @endphp
            <span class="badge {{ $badgeClass }}">{{ ucfirst($purchaseRequest->status ?? 'pending') }}</span>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Project / Department Details</div>
        <div class="info-grid">
            <div>
                <div class="info-label">MPR Number</div>
                <div class="info-value">{{ $purchaseRequest->request_number }}</div>
            </div>
            <div>
                <div class="info-label">Date</div>
                <div class="info-value">{{ $purchaseRequest->date?->format('d-m-Y') ?? '—' }}</div>
            </div>
            <div>
                <div class="info-label">Project / Site Name</div>
                <div class="info-value">{{ $purchaseRequest->project_name ?? '—' }}</div>
            </div>
            <div>
                <div class="info-label">Requested By</div>
                <div class="info-value">{{ $purchaseRequest->requested_by_name ?? $purchaseRequest->requestedBy?->name ?? '—' }}</div>
            </div>
            <div>
                <div class="info-label">Required Date</div>
                <div class="info-value">{{ $purchaseRequest->required_date_text ?? '—' }}</div>
            </div>
            <div>
                <div class="info-label">Location / Site</div>
                <div class="info-value">{{ $purchaseRequest->location ?? '—' }}</div>
            </div>
            @if($purchaseRequest->department)
            <div>
                <div class="info-label">Department</div>
                <div class="info-value">{{ $purchaseRequest->department }}</div>
            </div>
            @endif
            @if($purchaseRequest->verified_by_name)
            <div>
                <div class="info-label">Verified By</div>
                <div class="info-value">{{ $purchaseRequest->verified_by_name }}</div>
            </div>
            @endif
            @if($purchaseRequest->remarks)
            <div class="full">
                <div class="info-label">Remarks</div>
                <div class="info-value">{{ $purchaseRequest->remarks }}</div>
            </div>
            @endif
        </div>
    </div>

    <div class="section" style="padding-top:0;">
        <div class="section-title">Material Details</div>
        <table>
            <thead>
                <tr>
                    <th style="width:5%;">S.No</th>
                    <th style="width:35%;">Description of Material</th>
                    <th style="width:12%;">Unit</th>
                    <th style="width:13%;">Qty Required</th>
                    <th style="width:20%;">Purpose / Use</th>
                    <th style="width:15%;">Required Date</th>
                </tr>
            </thead>
            <tbody>
                @forelse($purchaseRequest->items as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $item->description }}</td>
                    <td>{{ $item->unit ?? '—' }}</td>
                    <td>{{ number_format($item->quantity_required, 2) }}</td>
                    <td>{{ $item->purpose_use ?? '—' }}</td>
                    <td>{{ $item->required_date ? $item->required_date->format('d-m-Y') : '—' }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" style="text-align:center;padding:20px;color:#94a3b8;">No items on this request.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="signatures">
        <div class="sig-box">
            <div class="sig-label">Requested By</div>
            <div class="sig-name">{{ $purchaseRequest->requested_by_name ?? $purchaseRequest->requestedBy?->name ?? '—' }}</div>
            <div class="sig-meta">{{ $purchaseRequest->created_at?->format('d M Y') }}</div>
        </div>
        <div class="sig-box">
            <div class="sig-label">GM Signature</div>
            @if($purchaseRequest->signature)
                <img class="sig-img" src="{{ $purchaseRequest->signature->signature_image }}" alt="Signature">
                <div class="sig-name">{{ $purchaseRequest->signature->signedBy?->name ?? '—' }}</div>
                <div class="sig-meta">Signed {{ $purchaseRequest->signature->signed_at?->format('d M Y, H:i') }}</div>
            @else
                <div class="sig-meta" style="margin-top:14px;">Not yet signed</div>
            @endif
        </div>
    </div>

    <div class="footer">
        <span>SteelERP — Confidential</span>
        <span>Printed by: {{ Auth::user()->name ?? '—' }} · {{ now()->format('d M Y, H:i') }}</span>
    </div>

</div>

</body>
</html>
