@props(['pr'])

@php
  $receivablePOs = $pr->purchaseOrders->whereIn('status', ['sent', 'partial']);
@endphp

{{-- ============================================================
     SELECT SUPPLIER / PO TO RECEIVE MODAL
     ============================================================ --}}
<div id="grn-select-modal" class="pipe-modal" role="dialog" aria-modal="true" onclick="if(event.target===this)closeGrnSelectModal()">
  <div style="background:#fff;border-radius:20px;width:100%;max-width:520px;max-height:90vh;display:flex;flex-direction:column;box-shadow:0 30px 60px rgba(0,0,0,.3);overflow:hidden;">

    {{-- Header --}}
    <div style="padding:20px 24px;border-bottom:1px solid #f1f5f9;flex-shrink:0;display:flex;align-items:flex-start;justify-content:space-between;gap:12px;">
      <div>
        <div style="font-size:17px;font-weight:700;color:#0f172a;">Record Goods Receipt</div>
        <div style="font-size:12px;color:#64748b;margin-top:3px;">Choose the supplier / LPO you're receiving against</div>
      </div>
      <button onclick="closeGrnSelectModal()" aria-label="Close"
        style="width:32px;height:32px;border-radius:8px;border:none;background:#f1f5f9;cursor:pointer;font-size:18px;color:#64748b;display:flex;align-items:center;justify-content:center;flex-shrink:0;">×</button>
    </div>

    <div style="flex:1;overflow-y:auto;overscroll-behavior:contain;padding:10px 0;">
      @forelse($receivablePOs as $po)
      <a href="/app/purchase/grns?purchase_order_id={{ $po->id }}"
         style="display:flex;justify-content:space-between;align-items:center;gap:12px;padding:14px 24px;text-decoration:none;border-bottom:1px solid #f8fafc;transition:background .1s;"
         onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''">
        <div>
          <div style="font-size:14px;font-weight:700;color:#0f172a;">{{ $po->supplier->name ?? '—' }}</div>
          <div style="font-size:11px;color:#94a3b8;margin-top:2px;">
            {{ $po->po_number ?? 'PO-' . str_pad($po->id, 5, '0', STR_PAD_LEFT) }} · BD {{ number_format($po->total_amount, 3) }}
          </div>
        </div>
        <svg width="16" height="16" fill="none" stroke="#94a3b8" stroke-width="2.5" viewBox="0 0 24 24" style="flex-shrink:0;">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 18l6-6-6-6"/>
        </svg>
      </a>
      @empty
      <div style="padding:40px 24px;text-align:center;color:#94a3b8;font-size:13px;">No outstanding LPOs to receive against.</div>
      @endforelse
    </div>

    {{-- Footer --}}
    <div style="padding:14px 24px;border-top:1px solid #f1f5f9;display:flex;justify-content:flex-end;flex-shrink:0;background:#fafafa;">
      <button type="button" onclick="closeGrnSelectModal()"
        style="padding:8px 18px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;font-weight:600;color:#64748b;background:#fff;cursor:pointer;">
        Cancel
      </button>
    </div>
  </div>
</div>

<script>
function openGrnSelectModal() { document.getElementById('grn-select-modal').classList.add('open'); }
function closeGrnSelectModal() { document.getElementById('grn-select-modal').classList.remove('open'); }
</script>
