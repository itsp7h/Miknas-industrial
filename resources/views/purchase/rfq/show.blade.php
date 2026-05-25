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

      @php $alreadyIds = $invitations->pluck('supplier_id')->toArray(); @endphp
      <x-purchase.supplier-invite-list
        :suppliers="$suppliers"
        :alreadyIds="$alreadyIds"
        :formAction="route('purchase.requests.rfq.store', $request)"
      />
    </div>
  </div>
</div>
@endsection
