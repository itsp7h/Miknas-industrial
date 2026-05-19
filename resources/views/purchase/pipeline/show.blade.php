@extends('layouts.app')

@section('title', 'Pipeline — ' . $pr->request_number)

@section('content')
<style>
  .action-btn { display:inline-flex;align-items:center;gap:5px;font-size:12px;font-weight:700;padding:6px 14px;border-radius:7px;text-decoration:none;white-space:nowrap;cursor:pointer;border:none; }
  .sup-item:hover { background:#f8fafc; }
  .pipe-modal { display:none;position:fixed;inset:0;background:rgba(15,23,42,.55);z-index:9999;align-items:center;justify-content:center;padding:16px; }
  .pipe-modal.open { display:flex; }
</style>

{{-- Breadcrumb --}}
<div style="margin-bottom:20px;">
  <a href="{{ route('purchase.pipeline.index') }}"
     style="font-size:13px;color:#2563eb;text-decoration:none;display:inline-flex;align-items:center;gap:5px;">
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
    </svg>
    Purchase Pipeline
  </a>
</div>

@php
  $stageIdx  = $stages->stageIndex($pr->stage);
  $allStages = \App\Services\PurchaseStageService::STAGES;
  $total     = count($allStages);
  $pct       = $total > 1 ? round(($stageIdx / ($total - 1)) * 100) : 100;
  $isDone    = $pr->stage === 'complete';
  $pendingInv = $pr->rfqInvitations->where('status', 'pending');
  $sentInv    = $pr->rfqInvitations->where('status', '!=', 'pending');
  $selectedIds = $pr->rfqInvitations->pluck('supplier_id')->toArray();
@endphp

{{-- Header card --}}
<div style="background:#fff;border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,.06);overflow:hidden;margin-bottom:20px;">
  <div style="padding:20px 24px;display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;">
    <div>
      <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
        <h1 style="font-size:20px;font-weight:700;color:#0f172a;margin:0;">{{ $pr->request_number }}</h1>
        <span style="font-size:11px;font-weight:700;padding:4px 12px;border-radius:20px;
          background:{{ $isDone ? '#dcfce7' : '#fffbeb' }};
          color:{{ $isDone ? '#15803d' : '#92400e' }};">
          {{ $stages->stageLabel($pr->stage) }}
        </span>
      </div>
      <div style="font-size:13px;color:#64748b;margin-top:6px;display:flex;flex-wrap:wrap;gap:14px;">
        @if($pr->project_name) <span>📁 {{ $pr->project_name }}</span> @endif
        @if($pr->department)   <span>🏢 {{ $pr->department }}</span> @endif
        @if($pr->requested_by_name ?? $pr->requestedBy)  <span>👤 {{ $pr->requested_by_name ?? $pr->requestedBy->name }}</span> @endif
        @if($pr->date)         <span>📅 {{ \Carbon\Carbon::parse($pr->date)->format('d M Y') }}</span> @endif
      </div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
      <a href="{{ route('purchase.requests.edit', $pr) }}"
         style="font-size:12px;color:#2563eb;text-decoration:none;border:1px solid #bfdbfe;padding:6px 14px;border-radius:7px;white-space:nowrap;background:#eff6ff;">
        ✏️ Edit Request
      </a>
      <a href="{{ route('purchase.requests.show', $pr) }}"
         style="font-size:12px;color:#64748b;text-decoration:none;border:1px solid #e2e8f0;padding:6px 14px;border-radius:7px;white-space:nowrap;">
        View Full Request →
      </a>
    </div>
  </div>
  <div style="height:4px;background:#f1f5f9;">
    <div style="height:4px;background:{{ $isDone ? '#22c55e' : '#f59e0b' }};width:{{ $pct }}%;transition:width .4s ease;"></div>
  </div>
</div>

{{-- Two-column layout --}}
<div style="display:grid;grid-template-columns:1fr 320px;gap:20px;align-items:start;">

  {{-- Timeline --}}
  <div style="background:#fff;border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,.06);padding:24px;">
    <h2 style="font-size:14px;font-weight:700;color:#0f172a;margin:0 0 20px;">Pipeline Stages</h2>
    <div style="display:flex;flex-direction:column;gap:0;">
      @foreach($allStages as $i => $stage)
      @php
        $done    = $i < $stageIdx;
        $current = $i === $stageIdx;
        $isLast  = $i === $total - 1;
      @endphp
      <div style="display:flex;align-items:stretch;gap:16px;">

        {{-- Dot + line --}}
        <div style="display:flex;flex-direction:column;align-items:center;width:20px;flex-shrink:0;padding-top:2px;">
          <div style="width:18px;height:18px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;
            {{ $done ? 'background:#2563eb;' : ($current ? 'background:#f59e0b;box-shadow:0 0 0 5px #fde68a;' : 'background:#e2e8f0;') }}">
            @if($done)
              <svg width="9" height="9" viewBox="0 0 8 8" fill="none">
                <path d="M1.5 4L3 5.5L6.5 2" stroke="#fff" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            @endif
          </div>
          @if(!$isLast)
            <div style="width:2px;flex:1;min-height:12px;margin:4px 0;background:{{ $done ? '#2563eb' : '#e2e8f0' }};"></div>
          @endif
        </div>

        {{-- Stage content --}}
        <div style="flex:1;min-width:0;padding-bottom:{{ $isLast ? '0' : '16' }}px;">
          <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:8px;">
            <div>
              <div style="font-size:14px;font-weight:{{ $current ? '700' : ($done ? '600' : '400') }};
                color:{{ $done ? '#1d4ed8' : ($current ? '#d97706' : '#94a3b8') }};">
                {{ $stages->stageLabel($stage) }}
              </div>

              @if($done || $current)
              <div style="font-size:12px;color:#94a3b8;margin-top:2px;">
                @if($stage === 'draft')
                  Created by {{ $pr->requested_by_name ?? $pr->requestedBy?->name ?? '—' }}
                  @if($pr->created_at) · {{ $pr->created_at->format('d M Y') }} @endif
                @elseif($stage === 'gm_approval')
                  @if($pr->signature)
                    Signed by {{ $pr->signature->signedBy?->name ?? '—' }} · {{ $pr->signature->signed_at?->format('d M Y') }}
                  @elseif($current)
                    Awaiting GM signature
                  @endif
                @elseif($stage === 'rfq')
                  @if($pr->rfqInvitations->count())
                    {{ $pr->rfqInvitations->count() }} supplier(s) selected
                    @if($pendingInv->count()) · {{ $pendingInv->count() }} unsent @endif
                  @elseif($current)
                    Select suppliers to receive quote requests
                  @endif
                @elseif($stage === 'quoting')
                  {{ $pr->supplierQuotes->count() }} quote(s) received · {{ $sentInv->count() }} invited
                @elseif($stage === 'comparison')
                  {{ $pr->supplierQuotes->count() }} quote(s) ready to compare
                @elseif($stage === 'lpo' && $pr->awardedQuote)
                  Awarded to {{ $pr->awardedQuote->supplier->name }}
                @endif
              </div>
              @endif
            </div>

            {{-- Action buttons per stage --}}
            @if($current)
              @if($stage === 'draft')
                <button type="button" onclick="openSignModal()"
                   class="action-btn" style="background:#7c3aed;color:#fff;">
                  @if($pr->signature)
                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    View Signature
                  @else
                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                    Sign
                  @endif
                </button>

              @elseif($stage === 'gm_approval')
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                  <button type="button" onclick="openSignModal()"
                     class="action-btn" style="background:#7c3aed;color:#fff;">
                    @if($pr->signature)
                      <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                      View Signature
                    @else
                      <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                      Sign
                    @endif
                  </button>
                  <button type="button" onclick="openSupplierModal()"
                     class="action-btn" style="background:#2563eb;color:#fff;">
                    🏭 Select Suppliers
                  </button>
                </div>

              @elseif($stage === 'rfq')
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                  <button type="button" onclick="openSupplierModal()"
                     class="action-btn" style="background:#2563eb;color:#fff;">
                    + Add Suppliers
                  </button>
                  @if($pendingInv->count() > 0)
                  <form action="{{ route('purchase.requests.rfq.send-all', $pr) }}" method="POST" style="display:inline;">
                    @csrf
                    <button type="submit" class="action-btn" style="background:#16a34a;color:#fff;">
                      📨 Send ({{ $pendingInv->count() }})
                    </button>
                  </form>
                  @endif
                </div>

              @elseif($stage === 'quoting')
                <a href="{{ route('purchase.requests.quotes', $pr) }}"
                   class="action-btn" style="background:#f59e0b;color:#fff;">
                  View Quotes ({{ $pr->supplierQuotes->count() }}) →
                </a>

              @elseif($stage === 'comparison')
                <a href="{{ route('purchase.requests.compare', $pr) }}"
                   class="action-btn" style="background:#f59e0b;color:#fff;">
                  Compare & Award →
                </a>

              @elseif($stage === 'lpo')
                <a href="{{ route('purchase.orders.create') }}"
                   class="action-btn" style="background:#16a34a;color:#fff;">
                  Issue LPO →
                </a>

              @elseif($stage === 'receiving')
                <a href="{{ route('purchase.grns.create') }}"
                   class="action-btn" style="background:#16a34a;color:#fff;">
                  Record GRN →
                </a>

              @elseif($stage === 'payment')
                <a href="{{ route('purchase.payments.create') }}"
                   class="action-btn" style="background:#0f172a;color:#fff;">
                  Issue Payment →
                </a>
              @endif
            @endif
          </div>
        </div>
      </div>
      @endforeach
    </div>
  </div>

  {{-- Info sidebar --}}
  <div style="display:flex;flex-direction:column;gap:16px;">

    {{-- Request details --}}
    <div style="background:#fff;border-radius:14px;box-shadow:0 2px 10px rgba(0,0,0,.05);padding:20px;">
      <h3 style="font-size:13px;font-weight:700;color:#0f172a;margin:0 0 14px;">Request Details</h3>
      <dl style="display:flex;flex-direction:column;gap:8px;font-size:13px;">
        @if($pr->location)
        <div style="display:flex;justify-content:space-between;gap:8px;">
          <dt style="color:#64748b;flex-shrink:0;">Location</dt>
          <dd style="color:#0f172a;font-weight:600;text-align:right;margin:0;">{{ $pr->location }}</dd>
        </div>
        @endif
        @if($pr->required_date_text)
        <div style="display:flex;justify-content:space-between;gap:8px;">
          <dt style="color:#64748b;flex-shrink:0;">Required By</dt>
          <dd style="color:#0f172a;font-weight:600;text-align:right;margin:0;">{{ $pr->required_date_text }}</dd>
        </div>
        @endif
        @if($pr->verified_by_name)
        <div style="display:flex;justify-content:space-between;gap:8px;">
          <dt style="color:#64748b;flex-shrink:0;">Verified By</dt>
          <dd style="color:#0f172a;font-weight:600;text-align:right;margin:0;">{{ $pr->verified_by_name }}</dd>
        </div>
        @endif
        <div style="display:flex;justify-content:space-between;gap:8px;">
          <dt style="color:#64748b;flex-shrink:0;">Status</dt>
          <dd style="color:#0f172a;font-weight:600;text-align:right;margin:0;">{{ ucfirst($pr->status ?? '—') }}</dd>
        </div>
      </dl>
    </div>

    {{-- Selected suppliers (rfq stage+) --}}
    @if($pr->rfqInvitations->isNotEmpty())
    <div style="background:#fff;border-radius:14px;box-shadow:0 2px 10px rgba(0,0,0,.05);padding:20px;">
      <h3 style="font-size:13px;font-weight:700;color:#0f172a;margin:0 0 14px;">
        Suppliers ({{ $pr->rfqInvitations->count() }})
      </h3>
      <div style="display:flex;flex-direction:column;gap:6px;">
        @foreach($pr->rfqInvitations as $inv)
        @php
          $statusMap = [
            'pending'   => ['bg'=>'#f1f5f9','fg'=>'#64748b','label'=>'Pending'],
            'sent'      => ['bg'=>'#dbeafe','fg'=>'#1d4ed8','label'=>'Sent'],
            'opened'    => ['bg'=>'#e0e7ff','fg'=>'#3730a3','label'=>'Opened'],
            'submitted' => ['bg'=>'#dcfce7','fg'=>'#15803d','label'=>'Submitted'],
            'declined'  => ['bg'=>'#fee2e2','fg'=>'#991b1b','label'=>'Declined'],
          ];
          $sc = $statusMap[$inv->status] ?? $statusMap['pending'];
        @endphp
        <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;font-size:12px;padding:7px 0;border-bottom:1px solid #f8fafc;">
          <div>
            <div style="font-weight:600;color:#0f172a;">{{ $inv->supplier->name }}</div>
            @if($inv->channel !== 'email')
              <div style="font-size:10px;color:#94a3b8;">{{ ucfirst($inv->channel) }}</div>
            @endif
          </div>
          <div style="display:flex;align-items:center;gap:6px;">
            @if($inv->status === 'pending' && in_array($pr->stage, ['rfq','quoting']))
              {{-- WhatsApp link for pending --}}
              @if($inv->supplier->phone)
              <a href="{{ app(\App\Services\RfqInvitationService::class)->whatsappLink($inv) }}" target="_blank"
                 style="font-size:10px;background:#dcfce7;color:#15803d;padding:2px 7px;border-radius:10px;text-decoration:none;font-weight:700;">WA</a>
              @endif
            @endif
            <span style="background:{{ $sc['bg'] }};color:{{ $sc['fg'] }};padding:2px 8px;border-radius:12px;font-weight:700;font-size:10px;">
              {{ $sc['label'] }}
            </span>
          </div>
        </div>
        @endforeach
      </div>
    </div>
    @endif

    {{-- Quotes summary --}}
    @if($pr->supplierQuotes->isNotEmpty())
    <div style="background:#fff;border-radius:14px;box-shadow:0 2px 10px rgba(0,0,0,.05);padding:20px;">
      <h3 style="font-size:13px;font-weight:700;color:#0f172a;margin:0 0 14px;">
        Quotes ({{ $pr->supplierQuotes->count() }})
      </h3>
      <div style="display:flex;flex-direction:column;gap:8px;">
        @foreach($pr->supplierQuotes->sortBy('total_amount') as $quote)
        <div style="display:flex;justify-content:space-between;align-items:center;font-size:12px;padding:8px 10px;border-radius:8px;
          background:{{ $quote->is_awarded ? '#f0fdf4' : '#f8fafc' }};
          border:1px solid {{ $quote->is_awarded ? '#bbf7d0' : '#f1f5f9' }};">
          <div style="font-weight:600;color:#0f172a;">{{ $quote->supplier->name }}</div>
          <div style="color:{{ $quote->is_awarded ? '#15803d' : '#374151' }};font-weight:700;">
            {{ number_format($quote->total_amount, 2) }}
            @if($quote->is_awarded)
              <span style="font-size:10px;background:#22c55e;color:#fff;padding:1px 6px;border-radius:10px;margin-left:4px;">Awarded</span>
            @endif
          </div>
        </div>
        @endforeach
      </div>
      <a href="{{ route('purchase.requests.quotes', $pr) }}"
         style="display:block;margin-top:12px;text-align:center;font-size:12px;color:#2563eb;text-decoration:none;font-weight:600;">
        View All Quotes →
      </a>
    </div>
    @endif

  </div>
</div>

{{-- ============================================================
     GM SIGNATURE MODAL  (always in DOM so the button always works)
     ============================================================ --}}
<div id="sign-modal" class="pipe-modal" role="dialog" aria-modal="true" aria-labelledby="sign-modal-title">
  <div style="background:#fff;border-radius:20px;width:100%;max-width:560px;display:flex;flex-direction:column;box-shadow:0 30px 60px rgba(0,0,0,.3);overflow:hidden;">

    {{-- Header --}}
    <div style="background:linear-gradient(135deg,#7c3aed,#4f46e5);padding:22px 24px;display:flex;align-items:flex-start;justify-content:space-between;">
      <div>
        <div style="font-size:11px;font-weight:600;color:rgba(255,255,255,.7);text-transform:uppercase;letter-spacing:.06em;">GM Digital Signature</div>
        <div id="sign-modal-title" style="font-size:18px;font-weight:700;color:#fff;margin-top:4px;">{{ $pr->request_number }}</div>
        @if($pr->project_name)
          <div style="font-size:12px;color:rgba(255,255,255,.75);margin-top:2px;">{{ $pr->project_name }}</div>
        @endif
      </div>
      <button onclick="closeSignModal()" aria-label="Close"
        style="width:32px;height:32px;border-radius:8px;border:none;background:rgba(255,255,255,.2);cursor:pointer;font-size:18px;color:#fff;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        ×
      </button>
    </div>

    {{-- Body --}}
    <div style="padding:24px;">

      @if($pr->signature)
        {{-- Already signed — show the captured signature --}}
        <div style="text-align:center;">
          <img src="{{ $pr->signature->signature_image }}"
               style="max-width:100%;border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc;">
          <div style="font-size:12px;color:#64748b;margin-top:10px;">
            Signed by <strong>{{ $pr->signature->signedBy?->name ?? '—' }}</strong>
            on {{ $pr->signature->signed_at?->format('d M Y, H:i') }}
          </div>
        </div>
        <button type="button" onclick="closeSignModal()"
          style="width:100%;margin-top:18px;padding:11px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;font-weight:600;color:#64748b;background:#f8fafc;cursor:pointer;">
          Close
        </button>

      @else
        {{-- Signature pad --}}
        <p style="font-size:13px;color:#475569;margin:0 0 14px;">
          Draw your signature below to approve this purchase request. This is recorded with your name, timestamp, and IP.
        </p>

        <div style="position:relative;">
          <canvas id="sig-canvas" width="510" height="180"
            style="width:100%;border:2px dashed #cbd5e1;border-radius:10px;cursor:crosshair;touch-action:none;background:#fafafa;display:block;">
          </canvas>
          <div id="sig-hint" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none;">
            <span style="font-size:13px;color:#cbd5e1;font-style:italic;">Sign here</span>
          </div>
        </div>

        <form id="sig-form" method="POST" action="{{ route('purchase.requests.sign.store', $pr) }}">
          @csrf
          <input type="hidden" name="signature_image" id="sig-data">
        </form>

        <div style="display:flex;gap:10px;margin-top:14px;">
          <button type="button" onclick="clearSigCanvas()"
            style="flex:1;padding:11px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;font-weight:600;color:#64748b;background:#f8fafc;cursor:pointer;">
            Clear
          </button>
          <button type="button" onclick="submitSignature()"
            style="flex:2;padding:11px;border:none;border-radius:8px;font-size:13px;font-weight:700;color:#fff;background:linear-gradient(135deg,#7c3aed,#4f46e5);cursor:pointer;">
            Confirm Signature →
          </button>
        </div>
      @endif

    </div>
  </div>
</div>

{{-- ============================================================
     SELECT SUPPLIERS MODAL
     ============================================================ --}}
<div id="supplier-modal" class="pipe-modal" role="dialog" aria-modal="true" aria-labelledby="sup-modal-title">
  <div style="background:#fff;border-radius:20px;width:100%;max-width:680px;max-height:90vh;display:flex;flex-direction:column;box-shadow:0 30px 60px rgba(0,0,0,.3);overflow:hidden;">

    {{-- Header --}}
    <div style="padding:20px 24px 0;border-bottom:1px solid #f1f5f9;flex-shrink:0;">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:16px;">
        <div>
          <div id="sup-modal-title" style="font-size:17px;font-weight:700;color:#0f172a;">Select Suppliers</div>
          <div style="font-size:12px;color:#64748b;margin-top:3px;">Choose who receives the quote request</div>
        </div>
        <button onclick="closeSupplierModal()" aria-label="Close"
          style="width:32px;height:32px;border-radius:8px;border:none;background:#f1f5f9;cursor:pointer;font-size:18px;color:#64748b;display:flex;align-items:center;justify-content:center;flex-shrink:0;">×</button>
      </div>

      {{-- Tab bar --}}
      <div style="display:flex;gap:0;">
        <button id="stab-global" type="button" onclick="switchSupTab('global')"
          style="padding:10px 20px;font-size:13px;font-weight:700;border:none;background:none;cursor:pointer;color:#2563eb;border-bottom:2px solid #2563eb;margin-bottom:-1px;">
          Full Order
        </button>
        <button id="stab-item" type="button" onclick="switchSupTab('item')"
          style="padding:10px 20px;font-size:13px;font-weight:700;border:none;background:none;cursor:pointer;color:#94a3b8;border-bottom:2px solid transparent;margin-bottom:-1px;">
          By Item
        </button>
      </div>
    </div>

    {{-- Single form wrapping both panes --}}
    <form id="sup-form" action="{{ route('purchase.requests.rfq.select', $pr) }}" method="POST"
          style="flex:1;overflow:hidden;display:flex;flex-direction:column;min-height:0;">
      @csrf
      <input type="hidden" name="mode" id="sup-mode" value="global">

      {{-- ── GLOBAL PANE ── --}}
      <div id="sup-global-pane" style="flex:1;overflow-y:auto;overscroll-behavior:contain;display:flex;flex-direction:column;">
        {{-- Search --}}
        <div style="padding:14px 24px 10px;flex-shrink:0;">
          <div style="position:relative;">
            <svg style="position:absolute;left:11px;top:50%;transform:translateY(-50%);pointer-events:none;"
                 width="14" height="14" fill="none" stroke="#94a3b8" stroke-width="2" viewBox="0 0 24 24">
              <circle cx="11" cy="11" r="8"/><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35"/>
            </svg>
            <input id="sup-search" type="text" placeholder="Search suppliers…" oninput="filterGlobalSups(this.value)"
              style="width:100%;box-sizing:border-box;padding:9px 12px 9px 34px;border:1px solid #e2e8f0;border-radius:9px;font-size:13px;outline:none;"
              onfocus="this.style.borderColor='#2563eb'" onblur="this.style.borderColor='#e2e8f0'">
          </div>
        </div>
        <div id="sup-list" style="flex:1;">
          @forelse($suppliers as $sup)
          @php $alreadyAdded = in_array($sup->id, $selectedIds); @endphp
          <div class="g-sup-item" data-name="{{ strtolower($sup->name) }}"
            style="padding:11px 24px;display:flex;align-items:center;gap:14px;border-bottom:1px solid #f8fafc;transition:background .1s;
                   {{ $alreadyAdded ? 'opacity:.45;pointer-events:none;' : '' }}">
            <input type="checkbox" name="supplier_ids[]" value="{{ $sup->id }}" id="gsup-{{ $sup->id }}"
              {{ $alreadyAdded ? 'disabled checked' : '' }}
              onchange="toggleGlobalChan({{ $sup->id }}, this.checked); updateGlobalCount();"
              style="width:17px;height:17px;accent-color:#2563eb;cursor:pointer;flex-shrink:0;">
            <label for="gsup-{{ $sup->id }}" style="flex:1;cursor:{{ $alreadyAdded ? 'default' : 'pointer' }};">
              <div style="font-size:13px;font-weight:600;color:#0f172a;">
                {{ $sup->name }}
                @if($alreadyAdded)
                  <span style="font-size:10px;background:#dcfce7;color:#15803d;padding:1px 6px;border-radius:10px;margin-left:6px;font-weight:700;">Added</span>
                @endif
              </div>
              @if($sup->email || $sup->phone)
              <div style="font-size:11px;color:#94a3b8;margin-top:1px;">
                {{ collect([$sup->email, $sup->phone])->filter()->implode(' · ') }}
              </div>
              @endif
            </label>
            @if(!$alreadyAdded)
            <div id="gchan-{{ $sup->id }}" style="display:none;flex-shrink:0;">
              <input type="hidden" name="channel_{{ $sup->id }}" id="gchan-val-{{ $sup->id }}" value="email">
              <div style="display:flex;border:1px solid #e2e8f0;border-radius:7px;overflow:hidden;">
                <span onclick="setGChan({{ $sup->id }},'email')" id="gce-{{ $sup->id }}"
                  style="padding:5px 9px;font-size:10px;font-weight:700;cursor:pointer;background:#eff6ff;color:#2563eb;">Email</span>
                <span onclick="setGChan({{ $sup->id }},'whatsapp')" id="gcw-{{ $sup->id }}"
                  style="padding:5px 9px;font-size:10px;font-weight:700;cursor:pointer;background:#fff;color:#94a3b8;border-left:1px solid #e2e8f0;">WA</span>
                <span onclick="setGChan({{ $sup->id }},'both')" id="gcb-{{ $sup->id }}"
                  style="padding:5px 9px;font-size:10px;font-weight:700;cursor:pointer;background:#fff;color:#94a3b8;border-left:1px solid #e2e8f0;">Both</span>
              </div>
            </div>
            @endif
          </div>
          @empty
          <div style="padding:40px;text-align:center;color:#94a3b8;font-size:13px;">No active suppliers found.</div>
          @endforelse
          <div id="no-sup-msg" style="display:none;padding:30px;text-align:center;color:#94a3b8;font-size:13px;">No suppliers match your search.</div>
        </div>
      </div>

      {{-- ── BY ITEM PANE ── --}}
      <div id="sup-item-pane" style="flex:1;overflow-y:auto;overscroll-behavior:contain;display:none;flex-direction:column;">
        @if($pr->items->isEmpty())
          <div style="padding:40px;text-align:center;color:#94a3b8;font-size:13px;">This request has no items yet.</div>
        @else
          {{-- Column headers --}}
          <div style="display:grid;grid-template-columns:1fr 250px;gap:12px;padding:9px 24px;background:#f8fafc;border-bottom:1px solid #e2e8f0;flex-shrink:0;">
            <div style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.06em;">Item</div>
            <div style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.06em;">Assign Suppliers</div>
          </div>

          {{-- Item rows --}}
          @foreach($pr->items as $item)
          <div style="display:grid;grid-template-columns:1fr 250px;gap:12px;align-items:center;padding:13px 24px;border-bottom:1px solid #f8fafc;">
            {{-- Left: item info --}}
            <div>
              <div style="font-size:13px;font-weight:600;color:#0f172a;line-height:1.3;">{{ $item->description }}</div>
              <div style="font-size:11px;color:#94a3b8;margin-top:2px;">
                Qty: {{ rtrim(rtrim(number_format($item->quantity_required,2),'0'),'.') }}{{ $item->unit ? ' '.$item->unit : '' }}
              </div>
            </div>
            {{-- Right: multi-select dropdown trigger --}}
            <div>
              <button type="button" id="idd-btn-{{ $item->id }}"
                onclick="toggleItemDd(event,{{ $item->id }})"
                style="width:100%;padding:8px 11px;border:1.5px solid #e2e8f0;border-radius:8px;background:#fff;cursor:pointer;
                       display:flex;align-items:center;justify-content:space-between;gap:6px;text-align:left;transition:border .15s;">
                <span id="idd-label-{{ $item->id }}" style="font-size:12px;color:#94a3b8;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;flex:1;">
                  Select suppliers…
                </span>
                <svg width="11" height="11" fill="none" stroke="#94a3b8" stroke-width="2.5" viewBox="0 0 24 24" style="flex-shrink:0;">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
              </button>
            </div>
          </div>
          @endforeach

          {{-- Per-supplier channel settings --}}
          <div id="item-chan-section" style="padding:14px 24px;background:#fafafa;border-top:2px solid #f1f5f9;display:none;flex-shrink:0;">
            <div style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px;">Notify via</div>
            @foreach($suppliers as $sup)
            <div id="ic-{{ $sup->id }}" style="display:none;align-items:center;justify-content:space-between;padding:7px 0;border-bottom:1px solid #f1f5f9;">
              <span style="font-size:12px;font-weight:600;color:#0f172a;">{{ $sup->name }}</span>
              <div>
                <input type="hidden" name="channel_{{ $sup->id }}" id="ichan-val-{{ $sup->id }}" value="email">
                <div style="display:flex;border:1px solid #e2e8f0;border-radius:7px;overflow:hidden;">
                  <span onclick="setIChan({{ $sup->id }},'email')" id="ice-{{ $sup->id }}"
                    style="padding:5px 10px;font-size:10px;font-weight:700;cursor:pointer;background:#eff6ff;color:#2563eb;">Email</span>
                  <span onclick="setIChan({{ $sup->id }},'whatsapp')" id="icw-{{ $sup->id }}"
                    style="padding:5px 10px;font-size:10px;font-weight:700;cursor:pointer;background:#fff;color:#94a3b8;border-left:1px solid #e2e8f0;">WA</span>
                  <span onclick="setIChan({{ $sup->id }},'both')" id="icb-{{ $sup->id }}"
                    style="padding:5px 10px;font-size:10px;font-weight:700;cursor:pointer;background:#fff;color:#94a3b8;border-left:1px solid #e2e8f0;">Both</span>
                </div>
              </div>
            </div>
            @endforeach
          </div>
        @endif
      </div>

      {{-- Dropdown panels: fixed-positioned, one per item — must be inside form so checkboxes are submitted --}}
      @foreach($pr->items as $item)
      <div id="idd-{{ $item->id }}"
         style="display:none;position:fixed;z-index:99999;background:#fff;border:1.5px solid #e2e8f0;
                border-radius:12px;box-shadow:0 12px 32px rgba(0,0,0,.18);overflow:hidden;min-width:240px;">
      {{-- Search --}}
      <div style="padding:10px 10px 6px;">
        <input type="text" placeholder="Search suppliers…"
          oninput="filterItemDd({{ $item->id }}, this.value)"
          style="width:100%;box-sizing:border-box;padding:7px 10px;border:1px solid #e2e8f0;border-radius:7px;font-size:12px;outline:none;"
          onfocus="this.style.borderColor='#2563eb'" onblur="this.style.borderColor='#e2e8f0'">
      </div>
      {{-- Supplier list --}}
      <div style="max-height:210px;overflow-y:auto;padding-bottom:4px;">
        @forelse($suppliers as $sup)
        @php $alreadyAdded = in_array($sup->id, $selectedIds); @endphp
        <label class="idd-row-{{ $item->id }}" data-name="{{ strtolower($sup->name) }}"
          style="display:flex;align-items:center;gap:10px;padding:8px 12px;cursor:{{ $alreadyAdded ? 'default' : 'pointer' }};
                 {{ $alreadyAdded ? 'opacity:.45;' : '' }}"
          onmouseover="{{ $alreadyAdded ? '' : "this.style.background='#f8fafc'" }}"
          onmouseout="this.style.background='transparent'">
          <input type="checkbox" name="item_suppliers[{{ $item->id }}][]" value="{{ $sup->id }}"
            data-supname="{{ $sup->name }}"
            id="isup-{{ $item->id }}-{{ $sup->id }}"
            {{ $alreadyAdded ? 'disabled checked' : '' }}
            onchange="onItemSupChange({{ $item->id }}, {{ $sup->id }})"
            style="width:15px;height:15px;accent-color:#2563eb;cursor:{{ $alreadyAdded ? 'default' : 'pointer' }};flex-shrink:0;">
          <div style="min-width:0;">
            <div style="font-size:12px;font-weight:600;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
              {{ $sup->name }}
              @if($alreadyAdded)
                <span style="font-size:9px;background:#dcfce7;color:#15803d;padding:1px 5px;border-radius:8px;margin-left:4px;">Added</span>
              @endif
            </div>
            @if($sup->email)
            <div style="font-size:10px;color:#94a3b8;">{{ $sup->email }}</div>
            @endif
          </div>
        </label>
        @empty
        <div style="padding:16px;text-align:center;color:#94a3b8;font-size:12px;">No suppliers found.</div>
        @endforelse
        <div id="idd-no-{{ $item->id }}" style="display:none;padding:14px;text-align:center;color:#94a3b8;font-size:12px;">No results</div>
      </div>
      </div>
      @endforeach
    </form>

    {{-- Footer --}}
    <div style="padding:14px 24px;border-top:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;background:#fafafa;">
      <div style="font-size:12px;color:#64748b;" id="sup-footer-msg">0 selected</div>
      <div style="display:flex;gap:10px;">
        <button type="button" onclick="closeSupplierModal()"
          style="padding:8px 18px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;font-weight:600;color:#64748b;background:#fff;cursor:pointer;">
          Cancel
        </button>
        <button type="button" onclick="submitSuppliers()"
          style="padding:8px 22px;background:#2563eb;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;">
          Save &amp; Continue →
        </button>
      </div>
    </div>
  </div>
</div>

<script>
// ---- Signature modal ----
(function () {
  var canvas, ctx, hint, drawing = false, hasMark = false;

  function initCanvas() {
    canvas = document.getElementById('sig-canvas');
    if (!canvas || canvas._bound) return;
    canvas._bound = true;
    ctx   = canvas.getContext('2d');
    hint  = document.getElementById('sig-hint');
    ctx.lineWidth = 2.5; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.strokeStyle = '#1e293b';

    function pos(e) {
      var r = canvas.getBoundingClientRect();
      var src = e.touches ? e.touches[0] : e;
      return { x: (src.clientX - r.left) * (canvas.width / r.width),
               y: (src.clientY - r.top)  * (canvas.height / r.height) };
    }
    function start(e) { e.preventDefault(); drawing = true; var p = pos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); if (!hasMark) { hasMark = true; if (hint) hint.style.display = 'none'; } }
    function move(e)  { e.preventDefault(); if (!drawing) return; var p = pos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); ctx.beginPath(); ctx.moveTo(p.x, p.y); }
    function end()    { drawing = false; }

    canvas.addEventListener('mousedown',  start);
    canvas.addEventListener('mousemove',  move);
    canvas.addEventListener('mouseup',    end);
    canvas.addEventListener('mouseleave', end);
    canvas.addEventListener('touchstart', start, { passive: false });
    canvas.addEventListener('touchmove',  move,  { passive: false });
    canvas.addEventListener('touchend',   end);
  }

  window.openSignModal = function () {
    document.getElementById('sign-modal').classList.add('open');
    initCanvas();
  };
  window.closeSignModal = function () {
    document.getElementById('sign-modal').classList.remove('open');
  };
  window.clearSigCanvas = function () {
    if (!canvas) return;
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    hasMark = false;
    if (hint) hint.style.display = 'flex';
  };
  window.submitSignature = function () {
    if (!hasMark) { showToast('Please draw your signature first.', 'warn'); return; }
    document.getElementById('sig-data').value = canvas.toDataURL('image/png');
    document.getElementById('sig-form').submit();
  };

  document.getElementById('sign-modal').addEventListener('click', function (e) {
    if (e.target === this) closeSignModal();
  });
}());

// ---- Supplier modal ----
var _supTab = 'global';

function openSupplierModal() {
  document.getElementById('supplier-modal').classList.add('open');
  switchSupTab('global');
  document.getElementById('sup-search').focus();
}
function closeSupplierModal() {
  closeAllItemDd();
  document.getElementById('supplier-modal').classList.remove('open');
}
document.getElementById('supplier-modal').addEventListener('click', function(e) {
  if (e.target === this) closeSupplierModal();
});
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') { closeSignModal(); closeSupplierModal(); }
});

// Tab switching
function switchSupTab(tab) {
  _supTab = tab;
  document.getElementById('sup-mode').value = tab === 'item' ? 'by_item' : 'global';

  document.getElementById('sup-global-pane').style.display = tab === 'global' ? 'flex' : 'none';
  document.getElementById('sup-item-pane').style.display   = tab === 'item'   ? 'flex' : 'none';

  var gBtn = document.getElementById('stab-global');
  var iBtn = document.getElementById('stab-item');
  if (tab === 'global') {
    gBtn.style.color = '#2563eb'; gBtn.style.borderBottomColor = '#2563eb';
    iBtn.style.color = '#94a3b8'; iBtn.style.borderBottomColor = 'transparent';
  } else {
    iBtn.style.color = '#2563eb'; iBtn.style.borderBottomColor = '#2563eb';
    gBtn.style.color = '#94a3b8'; gBtn.style.borderBottomColor = 'transparent';
  }
  updateFooter();
}

// ── Global tab helpers ──
function filterGlobalSups(q) {
  q = q.toLowerCase().trim();
  var items = document.querySelectorAll('.g-sup-item');
  var visible = 0;
  items.forEach(function(item) {
    var match = !q || item.dataset.name.indexOf(q) !== -1;
    item.style.display = match ? 'flex' : 'none';
    if (match) visible++;
  });
  document.getElementById('no-sup-msg').style.display = (visible === 0 && q) ? 'block' : 'none';
}

function toggleGlobalChan(id, checked) {
  var el = document.getElementById('gchan-' + id);
  if (el) el.style.display = checked ? 'block' : 'none';
  updateFooter();
}

var chanStyles = {
  email:    { bg:'#eff6ff', fg:'#2563eb' },
  whatsapp: { bg:'#f0fdf4', fg:'#15803d' },
  both:     { bg:'#fef3c7', fg:'#92400e' },
};
function applyBtnStyles(prefix, id, val) {
  [['email','e'],['whatsapp','w'],['both','b']].forEach(function(p) {
    var el = document.getElementById(prefix + p[1] + '-' + id);
    if (!el) return;
    if (p[0] === val) { el.style.background = chanStyles[val].bg; el.style.color = chanStyles[val].fg; }
    else              { el.style.background = '#fff'; el.style.color = '#94a3b8'; }
  });
}
function setGChan(id, val) {
  document.getElementById('gchan-val-' + id).value = val;
  applyBtnStyles('gc', id, val);
}

function updateGlobalCount() { updateFooter(); }

// ── By-item tab helpers ──
var _openItemDd = null;

function toggleItemDd(event, itemId) {
  event.stopPropagation();
  var dd = document.getElementById('idd-' + itemId);
  if (!dd) return;

  // close if already open
  if (_openItemDd === itemId) {
    dd.style.display = 'none';
    _openItemDd = null;
    return;
  }
  // close any other open dropdown
  closeAllItemDd();

  // position relative to the trigger button
  var btn = document.getElementById('idd-btn-' + itemId);
  var rect = btn.getBoundingClientRect();
  var ddWidth = 260;
  var left = Math.min(rect.left, window.innerWidth - ddWidth - 8);
  dd.style.left   = left + 'px';
  dd.style.top    = (rect.bottom + 4) + 'px';
  dd.style.width  = ddWidth + 'px';
  dd.style.display = 'block';
  _openItemDd = itemId;

  // focus search input
  var inp = dd.querySelector('input[type="text"]');
  if (inp) setTimeout(function(){ inp.focus(); }, 30);
}

function closeAllItemDd() {
  if (_openItemDd !== null) {
    var dd = document.getElementById('idd-' + _openItemDd);
    if (dd) dd.style.display = 'none';
    _openItemDd = null;
  }
}

function filterItemDd(itemId, q) {
  q = q.toLowerCase().trim();
  var rows = document.querySelectorAll('.idd-row-' + itemId);
  var visible = 0;
  rows.forEach(function(row) {
    var match = !q || (row.dataset.name || '').indexOf(q) !== -1;
    row.style.display = match ? 'flex' : 'none';
    if (match) visible++;
  });
  var noEl = document.getElementById('idd-no-' + itemId);
  if (noEl) noEl.style.display = (visible === 0 && q) ? 'block' : 'none';
}

function updateItemDdLabel(itemId) {
  var checked = document.querySelectorAll('[id^="isup-' + itemId + '-"]:checked:not([disabled])');
  var label = document.getElementById('idd-label-' + itemId);
  if (!label) return;
  if (checked.length === 0) {
    label.textContent = 'Select suppliers…';
    label.style.color = '#94a3b8';
  } else {
    var names = Array.from(checked).map(function(c) { return c.dataset.supname || c.value; });
    label.textContent = names.join(', ');
    label.style.color = '#0f172a';
  }
}

function onItemSupChange(itemId, supId) {
  // update dropdown trigger label for this item
  updateItemDdLabel(itemId);

  // show/hide channel row for this supplier (appears if checked in ANY item)
  var anyChecked = document.querySelectorAll('input[name^="item_suppliers["][value="' + supId + '"]:checked:not([disabled])').length > 0;
  var row = document.getElementById('ic-' + supId);
  if (row) row.style.display = anyChecked ? 'flex' : 'none';

  // show/hide entire channel section
  var anyItemChecked = document.querySelectorAll('#sup-item-pane input[type="checkbox"]:checked:not([disabled])').length > 0;
  document.getElementById('item-chan-section').style.display = anyItemChecked ? 'block' : 'none';

  updateFooter();
}

function setIChan(id, val) {
  document.getElementById('ichan-val-' + id).value = val;
  applyBtnStyles('ic', id, val);
}

// close dropdown when clicking outside
document.addEventListener('click', function(e) {
  if (_openItemDd === null) return;
  var dd = document.getElementById('idd-' + _openItemDd);
  var btn = document.getElementById('idd-btn-' + _openItemDd);
  if (dd && !dd.contains(e.target) && btn && !btn.contains(e.target)) {
    closeAllItemDd();
  }
});

// ── Footer count ──
function updateFooter() {
  var msg = document.getElementById('sup-footer-msg');
  if (_supTab === 'global') {
    var n = document.querySelectorAll('#sup-list input[type="checkbox"]:checked:not([disabled])').length;
    msg.textContent = n + ' supplier' + (n === 1 ? '' : 's') + ' selected';
  } else {
    var checked = document.querySelectorAll('#sup-item-pane input[type="checkbox"]:checked:not([disabled])');
    var supIds = new Set();
    checked.forEach(function(c) { supIds.add(c.value); });
    var n = supIds.size;
    msg.textContent = n + ' supplier' + (n === 1 ? '' : 's') + ' assigned';
  }
}

// ── Submit ──
function submitSuppliers() {
  if (_supTab === 'global') {
    var checked = document.querySelectorAll('#sup-list input[type="checkbox"]:checked:not([disabled])');
    if (checked.length === 0) { showToast('Please select at least one supplier.', 'warn'); return; }
  } else {
    var checked = document.querySelectorAll('#sup-item-pane input[type="checkbox"]:checked:not([disabled])');
    if (checked.length === 0) { showToast('Please assign at least one supplier to an item.', 'warn'); return; }
  }
  document.getElementById('sup-form').submit();
}
</script>
@endsection
