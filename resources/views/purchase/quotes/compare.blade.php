@extends('layouts.app')

@section('title', 'Compare Quotes — ' . $request->request_number)

@section('content')
<div style="max-width:1100px;margin:0 auto;">
  <div style="background:#fff;border-radius:16px;box-shadow:0 4px 24px rgba(0,0,0,.08);overflow:hidden;">

    <div style="background:linear-gradient(135deg,#f59e0b,#d97706);padding:24px 28px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
      <div>
        <div style="font-size:11px;font-weight:600;color:rgba(255,255,255,.7);text-transform:uppercase;letter-spacing:.06em;">Quote Comparison</div>
        <div style="font-size:20px;font-weight:700;color:#fff;margin-top:4px;">{{ $request->request_number }}</div>
        @if($request->project_name)
        <div style="font-size:13px;color:rgba(255,255,255,.8);margin-top:2px;">{{ $request->project_name }}</div>
        @endif
      </div>
      <a href="{{ route('purchase.requests.quotes', $request) }}"
         style="padding:9px 18px;background:rgba(255,255,255,.15);color:#fff;border:1.5px solid rgba(255,255,255,.35);border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;">
        ← Back to Quotes
      </a>
    </div>

    <div style="padding:24px;overflow-x:auto;">
      @if($quotes->isEmpty())
        <p style="text-align:center;color:#94a3b8;padding:60px 0;">No quotes submitted yet.</p>
      @else

      @php
        $awardedQuote = $request->awardedQuote;
      @endphp

      <table style="width:100%;border-collapse:collapse;font-size:13px;min-width:500px;">
        <thead>
          <tr>
            <th style="padding:10px 14px;text-align:left;background:#f8fafc;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;min-width:200px;border-radius:8px 0 0 0;">Item</th>
            @foreach($quotes as $quote)
            <th style="padding:10px 14px;text-align:center;background:#f8fafc;font-size:12px;font-weight:700;color:{{ $quote->is_awarded ? '#15803d' : '#0f172a' }};min-width:150px;
              {{ $quote->is_awarded ? 'background:#f0fdf4;' : '' }}">
              {{ $quote->supplier->name }}
              @if($quote->is_awarded)
              <div style="font-size:10px;font-weight:700;color:#15803d;margin-top:2px;">✓ AWARDED</div>
              @endif
            </th>
            @endforeach
          </tr>
        </thead>
        <tbody>
          {{-- Per-item rows --}}
          @foreach($items as $i => $reqItem)
          @php
            $rowPrices = $quotes->map(fn($q) => ($q->items->get($i) && !$q->items->get($i)->not_available) ? $q->items->get($i)->unit_price : null)->filter()->values();
            $minPrice  = $rowPrices->count() ? $rowPrices->min() : null;
          @endphp
          <tr>
            <td style="padding:10px 14px;border-bottom:1px solid #f1f5f9;font-weight:500;color:#0f172a;">
              {{ $reqItem->description }}
              <div style="font-size:11px;color:#94a3b8;margin-top:2px;">Qty: {{ $reqItem->quantity }} {{ $reqItem->unit }}</div>
            </td>
            @foreach($quotes as $q)
            @php $qItem = $q->items->get($i); $isMin = $qItem && !$qItem->not_available && $minPrice !== null && (float)$qItem->unit_price === (float)$minPrice && $rowPrices->count() > 1; @endphp
            <td style="padding:10px 14px;border-bottom:1px solid #f1f5f9;text-align:center;background:{{ $isMin ? '#f0fdf4' : '' }};">
              @if($qItem)
                @if($qItem->not_available)
                  <span style="font-size:11px;font-weight:700;color:#dc2626;background:#fef2f2;padding:2px 8px;border-radius:4px;border:1px solid #fecaca;">Not available</span>
                @else
                  @if($qItem->supplier_description)
                    <div style="font-size:10px;color:#64748b;margin-bottom:2px;font-style:italic;">
                      "{{ $qItem->supplier_description }}"
                      <span style="background:#fef3c7;color:#92400e;font-size:9px;font-weight:700;padding:1px 5px;border-radius:3px;border:1px solid #fde68a;font-style:normal;margin-left:2px;">adjusted</span>
                    </div>
                  @endif
                  <div style="font-weight:600;color:{{ $isMin ? '#15803d' : '#0f172a' }};">
                    @if($isMin)<span style="font-size:10px;font-weight:700;color:#15803d;display:block;">LOWEST</span>@endif
                    BD {{ number_format($qItem->unit_price, 3) }}
                  </div>
                  <div style="font-size:11px;color:#64748b;margin-top:2px;">BD {{ number_format($qItem->total_price, 3) }}</div>
                @endif
              @else
                <span style="color:#e2e8f0;">—</span>
              @endif
            </td>
            @endforeach
          </tr>
          @endforeach

          {{-- Grand totals --}}
          @php $minTotal = $quotes->min('total_amount'); @endphp
          <tr style="background:#f8fafc;font-weight:700;">
            <td style="padding:12px 14px;border-top:2px solid #e2e8f0;">Grand Total</td>
            @foreach($quotes as $quote)
            @php $isCheapest = (float)$quote->total_amount === (float)$minTotal && $quotes->count() > 1; @endphp
            <td style="padding:12px 14px;text-align:center;border-top:2px solid #e2e8f0;font-size:15px;color:{{ $isCheapest ? '#15803d' : '#0f172a' }};background:{{ $isCheapest ? '#f0fdf4' : '' }};">
              BD {{ number_format($quote->total_amount, 3) }}
            </td>
            @endforeach
          </tr>

          {{-- Lead time --}}
          <tr>
            <td style="padding:10px 14px;color:#64748b;font-size:12px;border-top:1px solid #f1f5f9;">Lead Time</td>
            @foreach($quotes as $quote)
            <td style="padding:10px 14px;text-align:center;font-size:12px;color:#64748b;border-top:1px solid #f1f5f9;">
              {{ $quote->lead_time_days !== null ? $quote->lead_time_days.' days' : '—' }}
            </td>
            @endforeach
          </tr>

          {{-- Payment terms --}}
          <tr>
            <td style="padding:10px 14px;color:#64748b;font-size:12px;">Payment Terms</td>
            @foreach($quotes as $quote)
            <td style="padding:10px 14px;text-align:center;font-size:12px;color:#64748b;">
              {{ $quote->payment_terms ?: '—' }}
            </td>
            @endforeach
          </tr>

          {{-- Award buttons --}}
          @if(!$awardedQuote)
          <tr style="background:#fffbeb;">
            <td style="padding:12px 14px;font-size:12px;font-weight:700;color:#92400e;border-top:2px solid #fde68a;">Award to:</td>
            @foreach($quotes as $quote)
            <td style="padding:12px 14px;text-align:center;border-top:2px solid #fde68a;">
              <button type="button"
                onclick="openAwardModal({{ $quote->id }}, '{{ addslashes($quote->supplier->name) }}', 'BD {{ number_format($quote->total_amount, 3) }}')"
                style="padding:8px 16px;background:#f59e0b;color:#fff;border:none;border-radius:7px;font-size:12px;font-weight:700;cursor:pointer;">
                Award →
              </button>
            </td>
            @endforeach
          </tr>
          @else
          <tr style="background:#f0fdf4;">
            <td colspan="{{ $quotes->count() + 1 }}" style="padding:14px;text-align:center;font-size:13px;font-weight:700;color:#15803d;border-top:2px solid #bbf7d0;">
              ✓ Awarded to {{ $awardedQuote->supplier->name }} on {{ $awardedQuote->awarded_at?->format('d M Y') }}
            </td>
          </tr>
          @endif
        </tbody>
      </table>
      @endif
    </div>
  </div>
</div>

{{-- Award modal --}}
<div id="award-modal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.6);backdrop-filter:blur(4px);z-index:9999;align-items:center;justify-content:center;padding:20px;">
  <div style="background:#fff;border-radius:18px;width:100%;max-width:440px;overflow:hidden;box-shadow:0 24px 60px rgba(0,0,0,.25);">
    <div style="height:3px;background:linear-gradient(90deg,#f59e0b,#fbbf24);"></div>
    <div style="padding:28px 28px 0;">
      <div style="font-size:17px;font-weight:700;color:#0f172a;margin-bottom:4px;">Award Quote</div>
      <div style="font-size:13px;color:#64748b;margin-bottom:4px;" id="award-supplier-line"></div>
      <div style="font-size:13px;color:#64748b;margin-bottom:20px;" id="award-amount-line"></div>
      <form id="award-form" method="POST">
        @csrf
        <label style="display:block;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;margin-bottom:6px;">Reason for selection (required)</label>
        <textarea name="award_reason" rows="3" required
          style="width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;margin-bottom:16px;resize:vertical;"
          placeholder="e.g. Lowest price, reliable supplier, suitable lead time…"></textarea>
        <div style="display:flex;gap:10px;padding-bottom:24px;">
          <button type="button" onclick="closeAwardModal()"
            style="flex:1;padding:11px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:13px;font-weight:600;background:#f8fafc;color:#475569;cursor:pointer;">
            Cancel
          </button>
          <button type="submit"
            style="flex:2;padding:11px;background:#f59e0b;color:#fff;border:none;border-radius:9px;font-size:13px;font-weight:700;cursor:pointer;">
            Confirm Award
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openAwardModal(quoteId, supplierName, amount) {
  document.getElementById('award-form').action = '/purchase/requests/{{ $request->id }}/quotes/' + quoteId + '/award';
  document.getElementById('award-supplier-line').textContent = 'Supplier: ' + supplierName;
  document.getElementById('award-amount-line').textContent   = 'Total: ' + amount;
  document.getElementById('award-modal').style.display = 'flex';
}
function closeAwardModal() {
  document.getElementById('award-modal').style.display = 'none';
}
document.getElementById('award-modal').addEventListener('click', function(e) {
  if (e.target === this) closeAwardModal();
});
</script>
@endsection
