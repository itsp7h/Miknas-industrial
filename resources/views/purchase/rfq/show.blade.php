@extends('layouts.app')

@section('title', 'Send RFQ — ' . $request->request_number)

@section('content')
<div style="max-width:760px;margin:0 auto;">
  <div style="background:#fff;border-radius:16px;box-shadow:0 4px 24px rgba(0,0,0,.08);overflow:hidden;">

    <!-- Header -->
    <div style="background:linear-gradient(135deg,#0ea5e9,#0284c7);padding:24px 28px;">
      <div style="font-size:11px;font-weight:600;color:rgba(255,255,255,.7);text-transform:uppercase;letter-spacing:.06em;">Send RFQ Invitations</div>
      <div style="font-size:20px;font-weight:700;color:#fff;margin-top:4px;">{{ $request->request_number }}</div>
      @if($request->project_name)
      <div style="font-size:13px;color:rgba(255,255,255,.8);margin-top:2px;">{{ $request->project_name }}</div>
      @endif
    </div>

    <div style="padding:28px;">

      {{-- Already invited --}}
      @if($invitations->count())
      <div style="margin-bottom:24px;">
        <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.05em;margin-bottom:10px;">Already Invited</div>
        <div style="display:flex;flex-direction:column;gap:8px;">
          @foreach($invitations as $inv)
          @php
            $statusColors = [
              'sent'      => ['bg'=>'#eff6ff','border'=>'#bfdbfe','color'=>'#1d4ed8'],
              'opened'    => ['bg'=>'#fffbeb','border'=>'#fde68a','color'=>'#92400e'],
              'submitted' => ['bg'=>'#f0fdf4','border'=>'#bbf7d0','color'=>'#15803d'],
              'declined'  => ['bg'=>'#fef2f2','border'=>'#fecaca','color'=>'#dc2626'],
              'pending'   => ['bg'=>'#f8fafc','border'=>'#e2e8f0','color'=>'#64748b'],
            ];
            $sc = $statusColors[$inv->status] ?? $statusColors['pending'];
          @endphp
          <div style="display:flex;align-items:center;justify-content:space-between;background:{{ $sc['bg'] }};border:1px solid {{ $sc['border'] }};border-radius:10px;padding:12px 16px;">
            <div>
              <div style="font-size:13px;font-weight:600;color:#0f172a;">{{ $inv->supplier->name }}</div>
              <div style="font-size:11px;color:#64748b;margin-top:2px;">
                via {{ ucfirst($inv->channel) }}
                @if($inv->sent_at) · sent {{ $inv->sent_at->format('d M Y') }} @endif
              </div>
            </div>
            <div style="display:flex;align-items:center;gap:8px;">
              @if(in_array($inv->channel, ['whatsapp', 'both']))
              @php $wsLink = app(\App\Services\RfqInvitationService::class)->whatsappLink($inv); @endphp
              <a href="{{ $wsLink }}" target="_blank"
                style="font-size:11px;font-weight:700;padding:4px 10px;background:#25d366;color:#fff;border-radius:6px;text-decoration:none;">
                WhatsApp
              </a>
              @endif
              <span style="font-size:11px;font-weight:700;padding:4px 12px;border-radius:20px;background:{{ $sc['bg'] }};color:{{ $sc['color'] }};border:1px solid {{ $sc['border'] }};">
                {{ ucfirst($inv->status) }}
              </span>
            </div>
          </div>
          @endforeach
        </div>
      </div>
      @endif

      {{-- Invite more suppliers --}}
      <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.05em;margin-bottom:10px;">
        {{ $invitations->count() ? 'Add More Suppliers' : 'Select Suppliers' }}
      </div>

      <input type="text" id="supplier-search" placeholder="Search suppliers…" oninput="filterSuppliers(this.value)"
        style="width:100%;padding:9px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;margin-bottom:12px;box-sizing:border-box;">

      <form method="POST" action="{{ route('purchase.requests.rfq.store', $request) }}" id="rfq-form">
        @csrf
        <input type="hidden" name="_token" value="{{ csrf_token() }}">

        <div id="supplier-list" style="display:flex;flex-direction:column;gap:6px;max-height:400px;overflow-y:auto;padding-right:2px;">
          @php $alreadyIds = $invitations->pluck('supplier_id')->toArray(); @endphp
          @forelse($suppliers as $supplier)
          @php $already = in_array($supplier->id, $alreadyIds); @endphp
          <div class="sup-row" data-name="{{ strtolower($supplier->name) }}"
               style="border:1.5px solid #e2e8f0;border-radius:10px;padding:12px 14px;transition:border-color .15s;{{ $already ? 'opacity:.45;' : '' }}">
            <label style="display:flex;align-items:center;gap:12px;cursor:{{ $already ? 'default' : 'pointer' }};">
              <input type="checkbox" name="supplier_ids[]" value="{{ $supplier->id }}"
                     data-sid="{{ $supplier->id }}" onchange="toggleRow(this)"
                     {{ $already ? 'disabled' : '' }}
                     style="width:17px;height:17px;cursor:pointer;flex-shrink:0;">
              <div style="flex:1;min-width:0;">
                <div style="font-size:13px;font-weight:600;color:#0f172a;">{{ $supplier->name }}</div>
                <div style="font-size:11px;color:#64748b;margin-top:1px;">
                  {{ $supplier->email ?: '—' }} · {{ $supplier->phone ?: '—' }}
                </div>
              </div>
              @if($already)
              <span style="font-size:11px;font-weight:600;color:#64748b;background:#f1f5f9;padding:3px 10px;border-radius:20px;">Already invited</span>
              @endif
            </label>
            {{-- Channel selector (hidden until checked) --}}
            <div id="ch-{{ $supplier->id }}" style="display:none;margin-top:10px;padding-top:10px;border-top:1px dashed #e2e8f0;">
              <div style="font-size:11px;font-weight:700;color:#64748b;margin-bottom:8px;">Send via:</div>
              <div style="display:flex;gap:8px;">
                @foreach(['whatsapp' => 'WhatsApp', 'email' => 'Email', 'both' => 'Both'] as $val => $lbl)
                <label style="flex:1;cursor:pointer;">
                  <input type="radio" name="channel_{{ $supplier->id }}" value="{{ $val }}" {{ $val === 'both' ? 'checked' : '' }}
                         style="display:none;" id="ch-{{ $supplier->id }}-{{ $val }}"
                         onchange="styleChannelBtns({{ $supplier->id }})">
                  <span id="chbtn-{{ $supplier->id }}-{{ $val }}"
                        onclick="document.getElementById('ch-{{ $supplier->id }}-{{ $val }}').checked=true;styleChannelBtns({{ $supplier->id }});"
                        style="display:block;text-align:center;padding:7px 6px;border-radius:7px;font-size:12px;font-weight:600;cursor:pointer;
                               border:1.5px solid {{ $val === 'both' ? '#0ea5e9' : '#e2e8f0' }};
                               background:{{ $val === 'both' ? '#eff6ff' : '#fff' }};
                               color:{{ $val === 'both' ? '#0284c7' : '#64748b' }};">
                    {{ $lbl }}
                  </span>
                </label>
                @endforeach
              </div>
            </div>
          </div>
          @empty
          <p style="text-align:center;color:#94a3b8;padding:24px;">No active suppliers found. Add suppliers first.</p>
          @endforelse
        </div>

        <button type="submit"
          style="width:100%;margin-top:20px;padding:13px;background:linear-gradient(135deg,#0ea5e9,#0284c7);color:#fff;border:none;border-radius:9px;font-size:14px;font-weight:700;cursor:pointer;">
          Send Invitations →
        </button>
      </form>
    </div>
  </div>
</div>

<script>
function filterSuppliers(q) {
  document.querySelectorAll('.sup-row').forEach(function(row) {
    row.style.display = row.dataset.name.includes(q.toLowerCase()) ? '' : 'none';
  });
}

function toggleRow(cb) {
  var ch = document.getElementById('ch-' + cb.dataset.sid);
  if (ch) ch.style.display = cb.checked ? 'block' : 'none';
}

function styleChannelBtns(sid) {
  ['whatsapp','email','both'].forEach(function(v) {
    var inp = document.getElementById('ch-' + sid + '-' + v);
    var btn = document.getElementById('chbtn-' + sid + '-' + v);
    if (!inp || !btn) return;
    if (inp.checked) {
      btn.style.borderColor = '#0ea5e9'; btn.style.background = '#eff6ff'; btn.style.color = '#0284c7';
    } else {
      btn.style.borderColor = '#e2e8f0'; btn.style.background = '#fff'; btn.style.color = '#64748b';
    }
  });
}
</script>
@endsection
