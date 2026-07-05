@extends('layouts.app')

@section('title', 'Quotes — ' . $request->request_number)

@section('content')
<div style="max-width:760px;margin:0 auto;">
  <div style="margin-bottom:16px;">
    <a href="{{ route('purchase.pipeline.show', $request) }}"
       style="font-size:13px;color:#2563eb;text-decoration:none;display:inline-flex;align-items:center;gap:5px;">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
      </svg>
      Back to Pipeline
    </a>
  </div>
  <div style="background:#fff;border-radius:16px;box-shadow:0 4px 24px rgba(0,0,0,.08);overflow:hidden;">

    <div style="background:linear-gradient(135deg,#f59e0b,#d97706);padding:24px 28px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
      <div>
        <div style="font-size:11px;font-weight:600;color:rgba(255,255,255,.7);text-transform:uppercase;letter-spacing:.06em;">Supplier Quotes</div>
        <div style="font-size:20px;font-weight:700;color:#fff;margin-top:4px;">{{ $request->request_number }}</div>
      </div>
      @if($quotes->count() >= 1)
      <a href="{{ route('purchase.requests.compare', $request) }}"
         style="padding:10px 20px;background:rgba(255,255,255,.2);color:#fff;border:1.5px solid rgba(255,255,255,.4);border-radius:8px;font-size:13px;font-weight:700;text-decoration:none;">
        Compare All →
      </a>
      @endif
    </div>

    <div style="padding:24px;">

      @if($quotes->isEmpty())
        <div style="text-align:center;padding:60px 0;color:#94a3b8;">
          <div style="font-size:40px;margin-bottom:12px;">📬</div>
          <div style="font-size:15px;font-weight:600;margin-bottom:4px;">No quotes yet</div>
          <div style="font-size:13px;">Waiting for suppliers to submit their quotes via the private links.</div>
        </div>
      @else
        <div style="display:flex;flex-direction:column;gap:12px;">
          @foreach($quotes->sortBy('total_amount') as $quote)
          @php $isLowest = $quote->total_amount == $quotes->min('total_amount') && $quotes->count() > 1; $hasAwardedItems = $quote->hasAwardedItems(); @endphp
          <div style="border:1.5px solid {{ $hasAwardedItems ? '#bbf7d0' : ($isLowest ? '#bfdbfe' : '#e2e8f0') }};border-radius:12px;padding:18px 20px;background:{{ $hasAwardedItems ? '#f0fdf4' : ($isLowest ? '#eff6ff' : '#fff') }};">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;">
              <div>
                <div style="font-size:14px;font-weight:700;color:#0f172a;">{{ $quote->supplier->name }}</div>
                <div style="font-size:12px;color:#64748b;margin-top:3px;">
                  Lead time: {{ $quote->lead_time_days !== null ? $quote->lead_time_days.' days' : '—' }}
                  @if($quote->payment_terms) · {{ $quote->payment_terms }} @endif
                </div>
                <div style="font-size:11px;color:#94a3b8;margin-top:2px;">Submitted {{ $quote->submitted_at->format('d M Y, H:i') }}</div>
              </div>
              <div style="text-align:right;flex-shrink:0;">
                <div style="font-size:18px;font-weight:700;color:{{ $isLowest ? '#2563eb' : '#0f172a' }};">BD {{ number_format($quote->total_amount, 3) }}</div>
                @if($isLowest && $quotes->count() > 1)
                <div style="font-size:10px;font-weight:700;color:#2563eb;margin-top:2px;">LOWEST PRICE</div>
                @endif
                @if($hasAwardedItems)
                <div style="font-size:11px;font-weight:700;color:#15803d;margin-top:4px;">✓ {{ $quote->awardedItems()->count() }} item(s) awarded</div>
                @endif
              </div>
            </div>

            @if($quote->notes)
            <div style="margin-top:10px;padding-top:10px;border-top:1px dashed #e2e8f0;font-size:12px;color:#64748b;">
              {{ $quote->notes }}
            </div>
            @endif

            {{-- Item breakdown --}}
            <details style="margin-top:10px;">
              <summary style="font-size:12px;font-weight:600;color:#2563eb;cursor:pointer;">View item breakdown ({{ $quote->items->count() }} items)</summary>
              <div style="margin-top:8px;overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:12px;">
                  <thead>
                    <tr style="background:#f8fafc;">
                      <th style="padding:6px 10px;text-align:left;color:#64748b;">Item</th>
                      <th style="padding:6px 10px;text-align:right;color:#64748b;">Qty</th>
                      <th style="padding:6px 10px;text-align:right;color:#64748b;">Unit Price</th>
                      <th style="padding:6px 10px;text-align:right;color:#64748b;">Total</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach($quote->items as $qi)
                    <tr style="border-top:1px solid #f1f5f9;">
                      <td style="padding:6px 10px;">{{ $qi->description }}</td>
                      <td style="padding:6px 10px;text-align:right;">{{ $qi->quantity }}</td>
                      <td style="padding:6px 10px;text-align:right;">BD {{ number_format($qi->unit_price, 3) }}</td>
                      <td style="padding:6px 10px;text-align:right;font-weight:600;">BD {{ number_format($qi->total_price, 3) }}</td>
                    </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>
            </details>
          </div>
          @endforeach
        </div>

        @if(!$request->isFullyAwarded() && $quotes->count() >= 1)
        <div style="margin-top:20px;padding:14px;background:#fffbeb;border:1px solid #fde68a;border-radius:10px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
          <div style="font-size:13px;color:#92400e;font-weight:500;">Ready to pick a winner?</div>
          <a href="{{ route('purchase.requests.compare', $request) }}"
             style="padding:9px 20px;background:#f59e0b;color:#fff;border-radius:8px;font-size:13px;font-weight:700;text-decoration:none;">
            Open Comparison Table →
          </a>
        </div>
        @endif
      @endif
    </div>
  </div>
</div>
@endsection
