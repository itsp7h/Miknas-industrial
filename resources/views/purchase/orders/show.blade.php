@extends('layouts.app')

@section('title', 'Purchase Order — ' . ($order->po_number ?? 'PO-' . str_pad($order->id, 5, '0', STR_PAD_LEFT)))

@section('content')
<style>
  .lpo-badge { display:inline-block;padding:2px 9px;border-radius:20px;font-size:9.5px;font-weight:700;letter-spacing:.04em;margin-top:4px; }
  .lpo-badge-draft     { background:#f1f5f9;color:#64748b; }
  .lpo-badge-sent      { background:#dbeafe;color:#1d4ed8; }
  .lpo-badge-received  { background:#d1fae5;color:#065f46; }
  .lpo-badge-cancelled { background:#fee2e2;color:#991b1b; }
  .lpo-info-label { font-size:9.5px;color:#94a3b8;text-transform:uppercase;letter-spacing:.04em; }
  .lpo-info-value { font-size:12.5px;font-weight:600;color:#0f172a;margin-top:2px; }
  .lpo-section-title { font-size:10px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.06em;margin-bottom:12px; }
  .lpo-table { width:100%;border-collapse:collapse;font-size:11px; }
  .lpo-table thead th { background:#1e293b;color:#fff;font-weight:600;font-size:9.5px;letter-spacing:.05em;text-transform:uppercase;padding:8px 10px;text-align:left; }
  .lpo-table thead th.text-right { text-align:right; }
  .lpo-table tbody tr:nth-child(even) { background:#f8fafc; }
  .lpo-table tbody td { padding:7px 10px;color:#334155;border-bottom:1px solid #e2e8f0;vertical-align:middle; }
  .lpo-table tbody td.text-right { text-align:right; }
  .lpo-table tbody td:first-child { color:#94a3b8;width:5%;text-align:center; }
  .lpo-table tbody td:nth-child(2) { font-weight:600;color:#0f172a; }
  .lpo-table tfoot td { padding:10px;font-weight:700; }
  .lpo-table tfoot td.text-right { text-align:right; }
</style>

{{-- Breadcrumb + actions --}}
<div style="margin-bottom:16px;display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;">
  <div>
    <a href="{{ $order->purchaseRequest ? route('purchase.pipeline.show', $order->purchaseRequest) : route('purchase.orders.index') }}"
       style="font-size:13px;color:#2563eb;text-decoration:none;display:inline-flex;align-items:center;gap:5px;margin-bottom:6px;">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
      </svg>
      {{ $order->purchaseRequest ? 'Back to Pipeline' : 'Back to Purchase Orders' }}
    </a>
    <p style="font-size:13px;margin:0;">
      <a href="{{ route('purchase.orders.index') }}" style="color:#2563eb;text-decoration:none;">Purchase Orders</a>
      <span style="color:#94a3b8;"> / {{ $order->po_number ?? 'PO-' . str_pad($order->id, 5, '0', STR_PAD_LEFT) }}</span>
    </p>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap;">
    <a href="{{ route('purchase.orders.print', $order) }}" target="_blank" class="btn-secondary">Print LPO</a>
    <a href="{{ route('purchase.orders.pdf', $order) }}" class="btn-secondary">Download PDF</a>
    <a href="{{ route('purchase.orders.edit', $order) }}" class="btn-secondary">Edit</a>
    <a href="{{ route('purchase.grns.create', ['purchase_order_id' => $order->id]) }}" class="btn-primary">Create GRN</a>
  </div>
</div>

{{-- LPO sheet — mirrors the printable layout --}}
<div style="max-width:820px;margin:0 auto 24px;background:#fff;border-radius:16px;box-shadow:0 4px 24px rgba(0,0,0,.08);overflow:hidden;">

  <div style="display:flex;justify-content:space-between;align-items:flex-start;padding:28px 32px 18px;border-bottom:2px solid #16a34a;">
    <div style="display:flex;align-items:center;gap:10px;">
      <div style="width:38px;height:38px;border-radius:8px;background:#16a34a;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <svg width="18" height="18" fill="none" stroke="#fff" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
        </svg>
      </div>
      <div>
        <div style="font-size:18px;font-weight:700;color:#0f172a;">{{ $company->name ?? 'SteelERP' }}</div>
        <div style="font-size:10px;color:#64748b;margin-top:1px;">{{ $order->purchaseRequest->project_name ?? 'Manufacturing & Trading' }}</div>
      </div>
    </div>
    <div style="text-align:right;">
      <div style="font-size:16px;font-weight:700;color:#1e293b;">Local Purchase Order</div>
      <div style="font-size:10px;color:#64748b;margin-top:3px;">{{ $order->po_number ?? 'PO-' . str_pad($order->id, 5, '0', STR_PAD_LEFT) }}</div>
      <span class="lpo-badge lpo-badge-{{ $order->status ?? 'draft' }}">{{ ucfirst($order->status ?? 'draft') }}</span>
    </div>
  </div>

  <div style="padding:20px 32px;">
    <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:20px;">
      <div>
        <div class="lpo-section-title">Supplier</div>
        <div class="lpo-info-value">{{ $order->supplier->name ?? '—' }}</div>
        @if($order->supplier?->contact_person)
          <div class="lpo-info-value" style="font-weight:400;">{{ $order->supplier->contact_person }}</div>
        @endif
        @if($order->supplier?->address)
          <div class="lpo-info-value" style="font-weight:400;">{{ $order->supplier->address }}</div>
        @endif
        @if($order->supplier?->phone)
          <div class="lpo-info-value" style="font-weight:400;">{{ $order->supplier->phone }}</div>
        @endif
        @if($order->supplier?->email)
          <div class="lpo-info-value" style="font-weight:400;">{{ $order->supplier->email }}</div>
        @endif
      </div>
      <div>
        <div class="lpo-section-title">Order Details</div>
        <div class="lpo-info-label">PO Date</div>
        <div class="lpo-info-value" style="margin-bottom:8px;">{{ $order->po_date ? \Carbon\Carbon::parse($order->po_date)->format('d M Y') : '—' }}</div>
        <div class="lpo-info-label">Expected Delivery</div>
        <div class="lpo-info-value">{{ $order->expected_delivery_date ? \Carbon\Carbon::parse($order->expected_delivery_date)->format('d M Y') : '—' }}</div>
      </div>
    </div>
    @if($order->purchaseRequest)
    <div style="margin-top:14px;">
      <div class="lpo-info-label">Reference MPR</div>
      <div class="lpo-info-value">{{ $order->purchaseRequest->request_number }}</div>
    </div>
    @endif
    @if($order->notes)
    <div style="margin-top:14px;">
      <div class="lpo-info-label">Notes</div>
      <div class="lpo-info-value" style="font-weight:400;">{{ $order->notes }}</div>
    </div>
    @endif
  </div>

  <div style="padding:0 32px 20px;">
    <div class="lpo-section-title">Order Items</div>
    <table class="lpo-table">
      <thead>
        <tr>
          <th style="width:5%;">#</th>
          <th style="width:40%;">Item</th>
          <th style="width:15%;" class="text-right">Quantity</th>
          <th style="width:20%;" class="text-right">Rate</th>
          <th style="width:20%;" class="text-right">Total</th>
        </tr>
      </thead>
      <tbody>
        @forelse($order->items as $i => $item)
        <tr>
          <td>{{ $i + 1 }}</td>
          <td>{{ $item->item->item_name ?? $item->item_name ?? '—' }}</td>
          <td class="text-right">{{ number_format($item->quantity, 2) }} {{ $item->item->unit_of_measure ?? '' }}</td>
          <td class="text-right">BD {{ number_format($item->rate, 2) }}</td>
          <td class="text-right">BD {{ number_format($item->total_amount, 2) }}</td>
        </tr>
        @empty
        <tr>
          <td colspan="5" style="text-align:center;padding:20px;color:#94a3b8;">No items on this order.</td>
        </tr>
        @endforelse
      </tbody>
      <tfoot>
        <tr>
          <td colspan="4" class="text-right">Total Amount</td>
          <td class="text-right">BD {{ number_format($order->total_amount, 2) }}</td>
        </tr>
      </tfoot>
    </table>
  </div>

  <div style="margin-top:8px;padding:14px 32px 24px;border-top:1px solid #e2e8f0;display:flex;justify-content:space-between;font-size:9.5px;color:#94a3b8;">
    <span>SteelERP — Confidential</span>
    <span>Issued by: {{ $order->createdBy->name ?? '—' }}</span>
  </div>

</div>

{{-- GRNs Linked --}}
@if($order->goodsReceiptNotes && $order->goodsReceiptNotes->count())
<div style="max-width:820px;margin:0 auto;background:#fff;border-radius:16px;box-shadow:0 4px 24px rgba(0,0,0,.08);overflow:hidden;">
  <div style="padding:16px 24px;border-bottom:1px solid #e2e8f0;">
    <h2 style="font-size:14px;font-weight:700;color:#0f172a;margin:0;">Goods Receipt Notes</h2>
  </div>
  <table class="table-base">
    <thead>
      <tr>
        <th>GRN #</th>
        <th>Warehouse</th>
        <th>Received Date</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      @foreach($order->goodsReceiptNotes as $grn)
      <tr>
        <td class="font-mono text-gray-700">{{ $grn->grn_number ?? 'GRN-' . str_pad($grn->id, 5, '0', STR_PAD_LEFT) }}</td>
        <td class="text-gray-700">{{ $grn->warehouse->name ?? '' }}</td>
        <td>{{ $grn->received_date ? \Carbon\Carbon::parse($grn->received_date)->format('d M Y') : '' }}</td>
        <td><span class="badge-green">{{ ucfirst($grn->status ?? 'received') }}</span></td>
        <td><a href="{{ route('purchase.grns.show', $grn) }}" class="btn-primary btn-sm">View</a></td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endif
@endsection
