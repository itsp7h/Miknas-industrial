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
      <x-purchase.edit-request-modal :purchaseRequest="$pr" />
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
        @php $minTotal = $pr->supplierQuotes->min('total_amount'); @endphp
        @foreach($pr->supplierQuotes->sortBy('total_amount') as $quote)
        @php $isLowest = !$quote->is_awarded && $pr->supplierQuotes->count() > 1 && (float)$quote->total_amount === (float)$minTotal; @endphp
        <div onclick="openQuoteModal({{ $quote->id }})"
             style="display:flex;justify-content:space-between;align-items:center;font-size:12px;padding:8px 10px;border-radius:8px;cursor:pointer;transition:box-shadow .15s,border-color .15s;
               background:{{ $quote->is_awarded ? '#f0fdf4' : ($isLowest ? '#eff6ff' : '#f8fafc') }};
               border:1px solid {{ $quote->is_awarded ? '#bbf7d0' : ($isLowest ? '#bfdbfe' : '#f1f5f9') }};"
             onmouseenter="this.style.boxShadow='0 2px 8px rgba(0,0,0,.08)';this.style.borderColor='{{ $quote->is_awarded ? '#86efac' : ($isLowest ? '#93c5fd' : '#e2e8f0') }}';"
             onmouseleave="this.style.boxShadow='';this.style.borderColor='{{ $quote->is_awarded ? '#bbf7d0' : ($isLowest ? '#bfdbfe' : '#f1f5f9') }}';">
          <div>
            <div style="font-weight:600;color:#0f172a;">{{ $quote->supplier->name }}</div>
            @if($isLowest)
              <div style="font-size:10px;color:#2563eb;font-weight:700;margin-top:1px;">LOWEST</div>
            @endif
          </div>
          <div style="display:flex;align-items:center;gap:6px;">
            <div style="color:{{ $quote->is_awarded ? '#15803d' : ($isLowest ? '#2563eb' : '#374151') }};font-weight:700;">
              BD {{ number_format($quote->total_amount, 3) }}
            </div>
            @if($quote->is_awarded)
              <span style="font-size:10px;background:#22c55e;color:#fff;padding:1px 6px;border-radius:10px;">✓</span>
            @else
              <svg width="12" height="12" fill="none" stroke="#94a3b8" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
              </svg>
            @endif
          </div>
        </div>
        @endforeach
      </div>
      <a href="{{ route('purchase.requests.compare', $pr) }}"
         style="display:block;margin-top:12px;text-align:center;font-size:12px;color:#f59e0b;text-decoration:none;font-weight:700;padding:7px;border:1.5px solid #fde68a;border-radius:8px;background:#fffbeb;">
        Compare All Quotes →
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

<x-purchase.supplier-select-modal :pr="$pr" :suppliers="$suppliers" :selectedIds="$selectedIds" />

{{-- ============================================================
     QUOTE DETAIL MODAL
     ============================================================ --}}
<div id="quote-modal" class="pipe-modal" role="dialog" aria-modal="true" onclick="if(event.target===this)closeQuoteModal()">
  <div style="background:#fff;border-radius:20px;width:100%;max-width:600px;max-height:90vh;display:flex;flex-direction:column;box-shadow:0 30px 60px rgba(0,0,0,.25);overflow:hidden;">

    {{-- Header --}}
    <div id="qm-header" style="background:linear-gradient(135deg,#f59e0b,#d97706);padding:20px 24px;display:flex;align-items:flex-start;justify-content:space-between;flex-shrink:0;">
      <div>
        <div style="font-size:11px;font-weight:600;color:rgba(255,255,255,.7);text-transform:uppercase;letter-spacing:.06em;">Supplier Quote</div>
        <div id="qm-supplier" style="font-size:19px;font-weight:700;color:#fff;margin-top:4px;"></div>
        <div id="qm-submitted" style="font-size:12px;color:rgba(255,255,255,.75);margin-top:2px;"></div>
      </div>
      <div style="display:flex;align-items:center;gap:10px;">
        <div style="text-align:right;">
          <div style="font-size:11px;font-weight:600;color:rgba(255,255,255,.7);">Total</div>
          <div id="qm-total" style="font-size:22px;font-weight:800;color:#fff;"></div>
        </div>
        <button onclick="closeQuoteModal()"
          style="width:32px;height:32px;border-radius:8px;border:none;background:rgba(255,255,255,.2);cursor:pointer;font-size:18px;color:#fff;display:flex;align-items:center;justify-content:center;flex-shrink:0;">×</button>
      </div>
    </div>

    {{-- Body --}}
    <div style="overflow-y:auto;flex:1;">

      {{-- Meta row --}}
      <div id="qm-meta" style="display:flex;gap:0;border-bottom:1px solid #f1f5f9;"></div>

      {{-- Items table --}}
      <div style="padding:20px 24px 0;">
        <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.05em;margin-bottom:10px;">Line Items</div>
        <div style="overflow-x:auto;">
          <table style="width:100%;border-collapse:collapse;font-size:12.5px;" id="qm-items-table">
            <thead>
              <tr style="background:#f8fafc;">
                <th style="padding:8px 10px;text-align:left;color:#64748b;font-weight:600;border-radius:6px 0 0 6px;">Item</th>
                <th style="padding:8px 10px;text-align:right;color:#64748b;font-weight:600;white-space:nowrap;">Qty</th>
                <th style="padding:8px 10px;text-align:right;color:#64748b;font-weight:600;white-space:nowrap;">Unit Price</th>
                <th style="padding:8px 10px;text-align:right;color:#64748b;font-weight:600;border-radius:0 6px 6px 0;white-space:nowrap;">Total</th>
              </tr>
            </thead>
            <tbody id="qm-items"></tbody>
          </table>
        </div>
      </div>

      {{-- Notes --}}
      <div id="qm-notes-wrap" style="margin:14px 24px 0;padding:12px 14px;background:#f8fafc;border-radius:10px;border:1px solid #f1f5f9;display:none;">
        <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;">Notes</div>
        <div id="qm-notes" style="font-size:13px;color:#374151;"></div>
      </div>

      {{-- Awarded banner --}}
      <div id="qm-awarded-banner" style="margin:14px 24px 0;padding:10px 14px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;font-size:13px;font-weight:700;color:#15803d;display:none;">
        ✓ This quote was awarded
      </div>

      <div style="height:20px;"></div>
    </div>

    {{-- Footer --}}
    <div style="padding:16px 24px;border-top:1px solid #f1f5f9;display:flex;gap:10px;flex-shrink:0;background:#fff;">
      <button onclick="closeQuoteModal()"
        style="flex:1;padding:10px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:13px;font-weight:600;color:#475569;background:#f8fafc;cursor:pointer;">
        Close
      </button>
      <a id="qm-compare-btn" href="{{ route('purchase.requests.compare', $pr) }}"
        style="flex:2;padding:10px;background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;border:none;border-radius:9px;font-size:13px;font-weight:700;cursor:pointer;text-decoration:none;display:flex;align-items:center;justify-content:center;gap:6px;">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
        </svg>
        Compare All Quotes
      </a>
    </div>
  </div>
</div>

@php
$quoteDataForJs = $pr->supplierQuotes->keyBy('id')->map(function($q) {
    return [
        'id'          => $q->id,
        'supplier'    => $q->supplier->name,
        'total'       => number_format($q->total_amount, 3),
        'isAwarded'   => (bool)$q->is_awarded,
        'leadTime'    => $q->lead_time_days !== null ? $q->lead_time_days . ' days' : null,
        'paymentTerms'=> $q->payment_terms,
        'notes'       => $q->notes,
        'submittedAt' => $q->submitted_at->format('d M Y, H:i'),
        'items'       => $q->items->map(function($i) {
            return [
                'description'         => $i->description,
                'supplierDescription' => $i->supplier_description,
                'quantity'            => $i->quantity,
                'unit'                => $i->unit,
                'unitPrice'           => number_format($i->unit_price, 3),
                'totalPrice'          => number_format($i->total_price, 3),
                'isVatable'           => (bool)$i->is_vatable,
                'notAvailable'        => (bool)$i->not_available,
            ];
        })->values(),
    ];
});
@endphp
<script>
var QUOTE_DATA = @json($quoteDataForJs);
</script>

<script>
// ---- Quote detail modal ----
(function () {
  window.openQuoteModal = function (id) {
    var q = QUOTE_DATA[id];
    if (!q) return;

    // Header
    document.getElementById('qm-supplier').textContent  = q.supplier;
    document.getElementById('qm-total').textContent     = 'BD ' + q.total;
    document.getElementById('qm-submitted').textContent = 'Submitted ' + q.submittedAt;

    // Awarded header tint
    var hdr = document.getElementById('qm-header');
    hdr.style.background = q.isAwarded
      ? 'linear-gradient(135deg,#16a34a,#15803d)'
      : 'linear-gradient(135deg,#f59e0b,#d97706)';

    // Meta chips
    var meta = document.getElementById('qm-meta');
    meta.innerHTML = '';
    var chips = [];
    if (q.leadTime)     chips.push(['⏱ Lead Time', q.leadTime]);
    if (q.paymentTerms) chips.push(['💳 Payment', q.paymentTerms]);
    chips.forEach(function (c) {
      var d = document.createElement('div');
      d.style.cssText = 'flex:1;padding:10px 16px;border-right:1px solid #f1f5f9;';
      d.innerHTML = '<div style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.05em;margin-bottom:2px;">' + c[0] + '</div>'
                  + '<div style="font-size:13px;font-weight:600;color:#0f172a;">' + c[1] + '</div>';
      meta.appendChild(d);
    });

    // Items
    var tbody = document.getElementById('qm-items');
    tbody.innerHTML = '';
    q.items.forEach(function (item, i) {
      var tr = document.createElement('tr');
      tr.style.borderTop = i > 0 ? '1px solid #f8fafc' : '';
      if (item.notAvailable) {
        tr.innerHTML =
          '<td style="padding:8px 10px;color:#0f172a;font-weight:500;">' +
            escHtml(item.description) +
            (item.supplierDescription ? '<div style="font-size:11px;color:#94a3b8;font-style:italic;margin-top:2px;">' + escHtml(item.supplierDescription) + '</div>' : '') +
          '</td>' +
          '<td style="padding:8px 10px;text-align:right;color:#94a3b8;">' + item.quantity + '</td>' +
          '<td colspan="2" style="padding:8px 10px;text-align:center;">' +
            '<span style="font-size:11px;font-weight:700;color:#dc2626;background:#fef2f2;padding:2px 8px;border-radius:4px;border:1px solid #fecaca;">N/A</span>' +
          '</td>';
      } else {
        tr.innerHTML =
          '<td style="padding:8px 10px;color:#0f172a;font-weight:500;">' +
            escHtml(item.description) +
            (item.supplierDescription ? '<div style="font-size:11px;color:#64748b;font-style:italic;margin-top:2px;">' + escHtml(item.supplierDescription) + ' <span style="background:#fef3c7;color:#92400e;font-size:9px;font-weight:700;padding:1px 4px;border-radius:3px;border:1px solid #fde68a;font-style:normal;">adjusted</span></div>' : '') +
          '</td>' +
          '<td style="padding:8px 10px;text-align:right;color:#64748b;">' + item.quantity + (item.unit ? ' ' + escHtml(item.unit) : '') + '</td>' +
          '<td style="padding:8px 10px;text-align:right;font-weight:600;color:#0f172a;">' +
            'BD ' + item.unitPrice +
            (item.isVatable ? ' <span title="VAT applicable" style="font-size:9px;font-weight:700;color:#0ea5e9;background:#e0f2fe;padding:1px 4px;border-radius:3px;border:1px solid #bae6fd;">VAT</span>' : '') +
          '</td>' +
          '<td style="padding:8px 10px;text-align:right;font-weight:700;color:#0f172a;">BD ' + item.totalPrice + '</td>';
      }
      tbody.appendChild(tr);
    });

    // Notes
    var nw = document.getElementById('qm-notes-wrap');
    if (q.notes) {
      document.getElementById('qm-notes').textContent = q.notes;
      nw.style.display = 'block';
    } else {
      nw.style.display = 'none';
    }

    // Awarded banner
    document.getElementById('qm-awarded-banner').style.display = q.isAwarded ? 'block' : 'none';

    document.getElementById('quote-modal').classList.add('open');
  };

  window.closeQuoteModal = function () {
    document.getElementById('quote-modal').classList.remove('open');
  };

  function escHtml(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }
}());
</script>

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

</script>
@endsection
