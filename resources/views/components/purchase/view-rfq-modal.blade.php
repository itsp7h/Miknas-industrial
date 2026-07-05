@props(['pr'])

@php
  $statusMap = [
      'pending'   => ['bg'=>'#f1f5f9','fg'=>'#64748b','label'=>'Pending'],
      'sent'      => ['bg'=>'#dbeafe','fg'=>'#1d4ed8','label'=>'Sent'],
      'opened'    => ['bg'=>'#e0e7ff','fg'=>'#3730a3','label'=>'Opened'],
      'submitted' => ['bg'=>'#dcfce7','fg'=>'#15803d','label'=>'Submitted'],
      'declined'  => ['bg'=>'#fee2e2','fg'=>'#991b1b','label'=>'Declined'],
  ];
  $chanLabel = ['email' => 'Email', 'whatsapp' => 'WhatsApp', 'both' => 'Email + WA'];

  $itemRows = $pr->items->map(function ($item) use ($pr) {
      $invitedFor = $pr->rfqInvitations->filter(function ($inv) use ($item) {
          return empty($inv->item_ids) || in_array($item->id, $inv->item_ids ?? []);
      });
      return ['item' => $item, 'invitations' => $invitedFor];
  });
@endphp

{{-- ============================================================
     VIEW RFQ / SUPPLIERS MODAL (read-only)
     ============================================================ --}}
<div id="view-rfq-modal" class="pipe-modal" role="dialog" aria-modal="true" onclick="if(event.target===this)closeViewRfqModal()">
  <div style="background:#fff;border-radius:20px;width:100%;max-width:680px;max-height:90vh;display:flex;flex-direction:column;box-shadow:0 30px 60px rgba(0,0,0,.3);overflow:hidden;">

    {{-- Header --}}
    <div style="padding:20px 24px;border-bottom:1px solid #f1f5f9;flex-shrink:0;display:flex;align-items:flex-start;justify-content:space-between;gap:12px;">
      <div>
        <div style="font-size:17px;font-weight:700;color:#0f172a;">Request for Quotation</div>
        <div style="font-size:12px;color:#64748b;margin-top:3px;">
          Sent to {{ $pr->rfqInvitations->count() }} supplier{{ $pr->rfqInvitations->count() === 1 ? '' : 's' }}
        </div>
      </div>
      <button onclick="closeViewRfqModal()" aria-label="Close"
        style="width:32px;height:32px;border-radius:8px;border:none;background:#f1f5f9;cursor:pointer;font-size:18px;color:#64748b;display:flex;align-items:center;justify-content:center;flex-shrink:0;">×</button>
    </div>

    <div style="flex:1;overflow-y:auto;overscroll-behavior:contain;">

      {{-- Items & which suppliers were invited for each --}}
      <div style="padding:18px 24px 6px;">
        <div style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.05em;margin-bottom:10px;">
          Items ({{ $pr->items->count() }})
        </div>
        <div style="display:flex;flex-direction:column;gap:8px;">
          @forelse($itemRows as $row)
          <div style="border:1px solid #f1f5f9;border-radius:10px;padding:11px 14px;background:#f8fafc;">
            <div style="font-size:13px;font-weight:600;color:#0f172a;">{{ $row['item']->description }}</div>
            <div style="font-size:11px;color:#94a3b8;margin-top:1px;">
              Qty: {{ rtrim(rtrim(number_format($row['item']->quantity_required, 2), '0'), '.') }}{{ $row['item']->unit ? ' '.$row['item']->unit : '' }}
            </div>
            <div style="margin-top:8px;display:flex;flex-wrap:wrap;gap:6px;">
              @forelse($row['invitations'] as $inv)
                <span style="font-size:11px;font-weight:600;color:#0f172a;background:#fff;border:1px solid #e2e8f0;padding:3px 9px;border-radius:20px;">
                  {{ $inv->supplier->name }}
                </span>
              @empty
                <span style="font-size:11px;color:#94a3b8;">No supplier assigned</span>
              @endforelse
            </div>
          </div>
          @empty
          <div style="padding:20px;text-align:center;color:#94a3b8;font-size:13px;">This request has no items.</div>
          @endforelse
        </div>
      </div>

      {{-- Suppliers & status --}}
      <div style="padding:18px 24px 22px;">
        <div style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.05em;margin-bottom:10px;">
          Suppliers ({{ $pr->rfqInvitations->count() }})
        </div>
        <div style="display:flex;flex-direction:column;gap:6px;">
          @forelse($pr->rfqInvitations as $inv)
          @php $sc = $statusMap[$inv->status] ?? $statusMap['pending']; @endphp
          <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;font-size:12.5px;padding:9px 12px;border-bottom:1px solid #f8fafc;">
            <div>
              <div style="font-weight:600;color:#0f172a;">{{ $inv->supplier->name }}</div>
              <div style="font-size:10.5px;color:#94a3b8;margin-top:1px;">
                {{ $chanLabel[$inv->channel] ?? ucfirst($inv->channel) }}
                @if($inv->sent_at) · Sent {{ $inv->sent_at->format('d M Y, H:i') }} @endif
              </div>
            </div>
            <span style="background:{{ $sc['bg'] }};color:{{ $sc['fg'] }};padding:2px 9px;border-radius:12px;font-weight:700;font-size:10px;flex-shrink:0;">
              {{ $sc['label'] }}
            </span>
          </div>
          @empty
          <div style="padding:20px;text-align:center;color:#94a3b8;font-size:13px;">No suppliers have been invited yet.</div>
          @endforelse
        </div>
      </div>
    </div>

    {{-- Footer --}}
    <div style="padding:14px 24px;border-top:1px solid #f1f5f9;display:flex;justify-content:flex-end;flex-shrink:0;background:#fafafa;">
      <button type="button" onclick="closeViewRfqModal()"
        style="padding:8px 18px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;font-weight:600;color:#64748b;background:#fff;cursor:pointer;">
        Close
      </button>
    </div>
  </div>
</div>

<script>
function openViewRfqModal() { document.getElementById('view-rfq-modal').classList.add('open'); }
function closeViewRfqModal() { document.getElementById('view-rfq-modal').classList.remove('open'); }
</script>
