@extends('layouts.app')

@section('title', 'Compare Quotes — ' . $request->request_number)

@section('content')
<div style="max-width:900px;margin:0 auto;">

  <div style="margin-bottom:16px;">
    <a href="{{ route('purchase.pipeline.show', $request) }}"
       style="font-size:13px;color:#2563eb;text-decoration:none;display:inline-flex;align-items:center;gap:5px;">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
      </svg>
      Back to Pipeline
    </a>
  </div>

  <div style="background:linear-gradient(135deg,#f59e0b,#d97706);border-radius:16px;padding:24px 28px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px;box-shadow:0 4px 24px rgba(0,0,0,.08);">
    <div>
      <div style="font-size:11px;font-weight:600;color:rgba(255,255,255,.7);text-transform:uppercase;letter-spacing:.06em;">Quote Comparison &amp; Award</div>
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

  @if($quotes->isEmpty())
    <div style="background:#fff;border-radius:16px;box-shadow:0 4px 24px rgba(0,0,0,.08);padding:60px 0;text-align:center;color:#94a3b8;">
      No quotes submitted yet.
    </div>
  @else

    {{-- Tabs --}}
    <div style="display:flex;gap:4px;margin-bottom:16px;background:#f1f5f9;padding:4px;border-radius:10px;width:fit-content;">
      <button type="button" id="tab-btn-comparison" onclick="showCompareTab('comparison')"
        style="padding:8px 18px;border:none;border-radius:7px;font-size:12.5px;font-weight:700;cursor:pointer;background:#fff;color:#0f172a;box-shadow:0 1px 3px rgba(0,0,0,.08);">
        Comparison &amp; Breakdown
      </button>
      <button type="button" id="tab-btn-awarded" onclick="showCompareTab('awarded')"
        style="padding:8px 18px;border:none;border-radius:7px;font-size:12.5px;font-weight:700;cursor:pointer;background:transparent;color:#64748b;">
        Awarded Suppliers
      </button>
    </div>

    {{-- ===================== TAB: Comparison & Breakdown ===================== --}}
    <div id="tab-panel-comparison">

    <div style="font-size:12px;color:#64748b;margin-bottom:16px;">
      Each item is its own decision — award it to whichever supplier offers the best terms for that item. Different items can go to different suppliers.
    </div>

    {{-- One card per item --}}
    @foreach($items as $item)
    @php
      $rowEntries = $quotes->map(fn($q) => ['quote' => $q, 'item' => $q->itemsByRequestItem->get($item->id)]);
      $validEntries = $rowEntries->filter(fn($e) => $e['item'] && !$e['item']->not_available);
      $awardedEntry = $validEntries->firstWhere('item.is_awarded', true);
      $minPrice = $validEntries->count() ? $validEntries->min(fn($e) => (float)$e['item']->unit_price) : null;

      if ($awardedEntry) {
          $badgeBg = '#dcfce7'; $badgeFg = '#15803d'; $badgeLabel = '✓ Awarded to ' . $awardedEntry['quote']->supplier->name;
      } elseif ($validEntries->count() >= 2) {
          $badgeBg = '#dbeafe'; $badgeFg = '#1d4ed8'; $badgeLabel = $validEntries->count() . ' suppliers competing';
      } elseif ($validEntries->count() === 1) {
          $badgeBg = '#fef3c7'; $badgeFg = '#92400e'; $badgeLabel = 'Sole-sourced';
      } else {
          $badgeBg = '#f1f5f9'; $badgeFg = '#64748b'; $badgeLabel = 'No quotes yet';
      }
    @endphp
    <div id="item-card-{{ $item->id }}" style="background:#fff;border-radius:14px;box-shadow:0 2px 10px rgba(0,0,0,.05);overflow:hidden;margin-bottom:16px;">
      <div style="padding:16px 20px;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
        <div>
          <div style="font-size:15px;font-weight:700;color:#0f172a;">{{ $item->description }}</div>
          <div style="font-size:12px;color:#94a3b8;margin-top:2px;">Qty: {{ $item->quantity_required }} {{ $item->unit }}</div>
        </div>
        <span id="item-badge-{{ $item->id }}" style="background:{{ $badgeBg }};color:{{ $badgeFg }};padding:4px 12px;border-radius:12px;font-weight:700;font-size:11px;white-space:nowrap;">
          {{ $badgeLabel }}
        </span>
      </div>

      <div style="padding:8px 20px 16px;overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:13px;table-layout:fixed;min-width:520px;">
          <colgroup>
            <col style="width:34%;">
            <col style="width:20%;">
            <col style="width:20%;">
            <col style="width:26%;">
          </colgroup>
          <thead>
            <tr>
              <th style="padding:8px 10px;text-align:left;color:#64748b;font-weight:600;font-size:11px;text-transform:uppercase;">Supplier</th>
              <th style="padding:8px 10px;text-align:center;color:#64748b;font-weight:600;font-size:11px;text-transform:uppercase;">Unit Price</th>
              <th style="padding:8px 10px;text-align:center;color:#64748b;font-weight:600;font-size:11px;text-transform:uppercase;">Total</th>
              <th style="padding:8px 10px;text-align:center;color:#64748b;font-weight:600;font-size:11px;text-transform:uppercase;">Status</th>
            </tr>
          </thead>
          <tbody id="item-rows-{{ $item->id }}">
            @foreach($rowEntries as $entry)
            @php $qi = $entry['item']; $q = $entry['quote']; $isMin = $qi && !$qi->not_available && $minPrice !== null && (float)$qi->unit_price === $minPrice && $validEntries->count() > 1; @endphp
            <tr style="border-top:1px solid #f8fafc;background:{{ $isMin ? '#f0fdf4' : '' }};">
              <td style="padding:8px 10px;text-align:left;color:#0f172a;font-weight:500;">
                {{ $q->supplier->name }}
                @if($qi && $qi->supplier_description)
                  <div style="font-size:10px;color:#64748b;font-style:italic;margin-top:2px;">
                    "{{ $qi->supplier_description }}"
                    <span style="background:#fef3c7;color:#92400e;font-size:9px;font-weight:700;padding:1px 5px;border-radius:3px;border:1px solid #fde68a;font-style:normal;margin-left:2px;">adjusted</span>
                  </div>
                @endif
              </td>
              @if(!$qi)
                <td colspan="2" style="padding:8px 10px;text-align:center;">
                  <span style="font-size:11px;font-weight:700;color:#64748b;background:#f1f5f9;padding:2px 8px;border-radius:4px;border:1px solid #e2e8f0;">Not quoted</span>
                </td>
                <td style="padding:8px 10px;text-align:center;"></td>
              @elseif($qi->not_available)
                <td colspan="2" style="padding:8px 10px;text-align:center;">
                  <span style="font-size:11px;font-weight:700;color:#dc2626;background:#fef2f2;padding:2px 8px;border-radius:4px;border:1px solid #fecaca;">Not available</span>
                </td>
                <td style="padding:8px 10px;text-align:center;"></td>
              @else
                <td style="padding:8px 10px;text-align:center;font-weight:600;color:{{ $qi->is_awarded ? '#15803d' : ($isMin ? '#2563eb' : '#0f172a') }};">
                  BD {{ number_format($qi->unit_price, 3) }}
                  @if($qi->is_vatable)
                    <span title="VAT applicable" style="font-size:9px;font-weight:700;color:#0ea5e9;background:#e0f2fe;padding:1px 4px;border-radius:3px;border:1px solid #bae6fd;margin-left:2px;">VAT</span>
                  @endif
                </td>
                <td style="padding:8px 10px;text-align:center;color:#64748b;">BD {{ number_format($qi->total_price, 3) }}</td>
                <td style="padding:8px 10px;text-align:center;">
                  <div style="display:flex;align-items:center;justify-content:center;gap:6px;">
                    @if($isMin)<span style="font-size:10px;font-weight:700;color:#2563eb;">LOWEST</span>@endif
                    @if($qi->is_awarded)
                      <button type="button" onclick="openAwardDetailModal({{ $qi->id }})"
                        style="font-size:10px;font-weight:700;color:#15803d;background:#dcfce7;padding:3px 10px;border-radius:10px;border:none;cursor:pointer;">
                        ✓ AWARDED
                      </button>
                    @elseif(!$awardedEntry)
                      <button type="button"
                        onclick="openAwardModal({{ $qi->id }}, '{{ addslashes($q->supplier->name) }}', '{{ addslashes($item->description) }}', 'BD {{ number_format($qi->unit_price, 3) }}')"
                        style="padding:5px 12px;background:#f59e0b;color:#fff;border:none;border-radius:6px;font-size:11px;font-weight:700;cursor:pointer;">
                        Award →
                      </button>
                    @endif
                  </div>
                </td>
              @endif
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
    @endforeach

    {{-- Grand total across every item, taking whichever supplier wins each one --}}
    <div style="margin-top:12px;padding:18px 20px;background:#0f172a;border-radius:12px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
      <div>
        <div style="font-size:11px;font-weight:600;color:#94a3b8;text-transform:uppercase;letter-spacing:.05em;">Grand Total</div>
        <div id="grand-total-subtitle" style="font-size:11px;color:#64748b;margin-top:2px;">
          {{ $fullyAwarded ? 'Based on awarded suppliers per item' : 'Based on the awarded price where decided, lowest offer otherwise' }}
          @if($unresolvedItems > 0)
            · {{ $unresolvedItems }} item(s) with no quotes excluded
          @endif
        </div>
      </div>
      <div style="text-align:right;">
        <div id="grand-total-breakdown" style="display:{{ $vatAmount > 0 ? 'block' : 'none' }};font-size:11px;color:#94a3b8;margin-bottom:2px;">
          Subtotal <span id="grand-total-subtotal-value">BD {{ number_format($subtotal, 3) }}</span>
          &nbsp;+&nbsp; VAT (<span id="grand-total-vat-rate">{{ rtrim(rtrim(number_format($vatRate, 2), '0'), '.') }}</span>%) <span id="grand-total-vat-value">BD {{ number_format($vatAmount, 3) }}</span>
        </div>
        <div id="grand-total-value" style="font-size:24px;font-weight:700;color:#fff;">BD {{ number_format($grandTotal, 3) }}</div>
      </div>
    </div>

    <div id="fully-awarded-banner" style="display:{{ $fullyAwarded ? 'block' : 'none' }};margin-top:16px;padding:14px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;text-align:center;font-size:13px;font-weight:700;color:#15803d;">
      ✓ All quoted items have been awarded. Ready to issue LPO(s).
    </div>

    </div>{{-- /tab-panel-comparison --}}

    {{-- ===================== TAB: Awarded Suppliers ===================== --}}
    <div id="tab-panel-awarded" style="display:none;">
      <div id="awarded-tab-body"></div>
    </div>

    @php
      $awardDataForJs = $quotes->flatMap(fn ($q) => $q->items)->filter(fn ($qi) => $qi->is_awarded)->keyBy('id')->map(function ($qi) {
          return [
              'item'        => $qi->description,
              'quantity'    => $qi->quantity,
              'unit'        => $qi->unit,
              'supplier'    => $qi->quote->supplier->name,
              'unitPrice'   => number_format($qi->unit_price, 3),
              'totalPrice'  => number_format($qi->total_price, 3),
              'reason'      => $qi->award_reason,
              'awardedAt'   => $qi->awarded_at?->format('d M Y, H:i'),
              'awardedBy'   => $qi->awardedBy?->name,
          ];
      });
    @endphp
    <script>
      var AWARD_DATA   = @json($awardDataForJs);
      var ITEMS_TOTAL  = {{ $items->count() }};
    </script>
  @endif
</div>

{{-- Award detail modal --}}
<div id="award-detail-modal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.6);backdrop-filter:blur(4px);z-index:9999;align-items:center;justify-content:center;padding:20px;">
  <div style="background:#fff;border-radius:18px;width:100%;max-width:440px;overflow:hidden;box-shadow:0 24px 60px rgba(0,0,0,.25);">
    <div style="height:3px;background:linear-gradient(90deg,#16a34a,#22c55e);"></div>
    <div style="padding:28px 28px 0;">
      <div style="font-size:17px;font-weight:700;color:#0f172a;margin-bottom:14px;">Award Details</div>
      <dl style="display:flex;flex-direction:column;gap:8px;font-size:13px;margin:0 0 18px;">
        <div style="display:flex;justify-content:space-between;gap:8px;">
          <dt style="color:#64748b;">Item</dt>
          <dd id="adm-item" style="color:#0f172a;font-weight:600;text-align:right;margin:0;"></dd>
        </div>
        <div style="display:flex;justify-content:space-between;gap:8px;">
          <dt style="color:#64748b;">Awarded to</dt>
          <dd id="adm-supplier" style="color:#15803d;font-weight:700;text-align:right;margin:0;"></dd>
        </div>
        <div style="display:flex;justify-content:space-between;gap:8px;">
          <dt style="color:#64748b;">Unit Price</dt>
          <dd id="adm-unit-price" style="color:#0f172a;font-weight:600;text-align:right;margin:0;"></dd>
        </div>
        <div style="display:flex;justify-content:space-between;gap:8px;">
          <dt style="color:#64748b;">Total</dt>
          <dd id="adm-total-price" style="color:#0f172a;font-weight:600;text-align:right;margin:0;"></dd>
        </div>
        <div style="display:flex;justify-content:space-between;gap:8px;">
          <dt style="color:#64748b;">Awarded By</dt>
          <dd id="adm-awarded-by" style="color:#0f172a;font-weight:600;text-align:right;margin:0;"></dd>
        </div>
        <div style="display:flex;justify-content:space-between;gap:8px;">
          <dt style="color:#64748b;">Awarded On</dt>
          <dd id="adm-awarded-at" style="color:#0f172a;font-weight:600;text-align:right;margin:0;"></dd>
        </div>
      </dl>
      <div style="padding:12px 14px;background:#f8fafc;border-radius:10px;border:1px solid #f1f5f9;margin-bottom:20px;">
        <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;">Reason</div>
        <div id="adm-reason" style="font-size:13px;color:#374151;"></div>
      </div>
      <div style="display:flex;gap:10px;padding-bottom:24px;">
        <button type="button" onclick="closeAwardDetailModal()"
          style="flex:1;padding:11px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:13px;font-weight:600;background:#f8fafc;color:#475569;cursor:pointer;">
          Close
        </button>
        <button type="button" id="adm-remove-btn"
          style="flex:2;padding:11px;background:#fff;color:#dc2626;border:1.5px solid #fecaca;border-radius:9px;font-size:13px;font-weight:700;cursor:pointer;">
          Remove Award
        </button>
      </div>
    </div>
  </div>
</div>

{{-- Award modal --}}
<div id="award-modal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.6);backdrop-filter:blur(4px);z-index:9999;align-items:center;justify-content:center;padding:20px;">
  <div style="background:#fff;border-radius:18px;width:100%;max-width:440px;overflow:hidden;box-shadow:0 24px 60px rgba(0,0,0,.25);">
    <div style="height:3px;background:linear-gradient(90deg,#f59e0b,#fbbf24);"></div>
    <div style="padding:28px 28px 0;">
      <div style="font-size:17px;font-weight:700;color:#0f172a;margin-bottom:4px;">Award Item</div>
      <div style="font-size:13px;color:#64748b;margin-bottom:2px;" id="award-item-line"></div>
      <div style="font-size:13px;color:#64748b;margin-bottom:2px;" id="award-supplier-line"></div>
      <div style="font-size:13px;color:#64748b;margin-bottom:20px;" id="award-amount-line"></div>
      <label style="display:block;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;margin-bottom:6px;">Reason for selection (required)</label>
      <textarea id="award-reason-input" rows="3"
        style="width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;margin-bottom:16px;resize:vertical;"
        placeholder="e.g. Lowest price, reliable supplier, suitable lead time…"></textarea>
      <div style="display:flex;gap:10px;padding-bottom:24px;">
        <button type="button" onclick="closeAwardModal()"
          style="flex:1;padding:11px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:13px;font-weight:600;background:#f8fafc;color:#475569;cursor:pointer;">
          Cancel
        </button>
        <button type="button" id="award-confirm-btn"
          style="flex:2;padding:11px;background:#f59e0b;color:#fff;border:none;border-radius:9px;font-size:13px;font-weight:700;cursor:pointer;">
          Confirm Award
        </button>
      </div>
    </div>
  </div>
</div>

<script>
var CSRF = document.querySelector('meta[name="csrf-token"]').content;
function api(url, method, data) {
  var opts = { method: method, headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' } };
  if (data) opts.body = JSON.stringify(data);
  return fetch(url, opts).then(function (r) {
    return r.json().then(function (body) {
      if (!r.ok) return Promise.reject(body);
      return body;
    });
  });
}

var currentAwardQuoteItemId = null;

function openAwardModal(quoteItemId, supplierName, itemDescription, amount) {
  currentAwardQuoteItemId = quoteItemId;
  document.getElementById('award-reason-input').value = '';
  document.getElementById('award-item-line').textContent     = 'Item: ' + itemDescription;
  document.getElementById('award-supplier-line').textContent = 'Supplier: ' + supplierName;
  document.getElementById('award-amount-line').textContent   = 'Unit price: ' + amount;
  document.getElementById('award-modal').style.display = 'flex';
}
function closeAwardModal() {
  document.getElementById('award-modal').style.display = 'none';
}
document.getElementById('award-modal').addEventListener('click', function (e) {
  if (e.target === this) closeAwardModal();
});
document.getElementById('award-confirm-btn').addEventListener('click', function () {
  var reason = document.getElementById('award-reason-input').value.trim();
  if (reason.length < 5) {
    showToast('Please enter a reason of at least 5 characters.', 'error');
    return;
  }
  api('/purchase/requests/{{ $request->id }}/quotes/items/' + currentAwardQuoteItemId + '/award', 'POST', { award_reason: reason })
    .then(function (body) {
      closeAwardModal();
      applyItemUpdate(body);
      showToast(body.message, 'success');
    })
    .catch(function (err) { showToast(err.message || 'Could not award this item.', 'error'); });
});

function unawardItem(quoteItemId, supplierName, itemDescription) {
  confirmAction(
    'Remove this award?',
    'This will unaward "' + itemDescription + '" from ' + supplierName + ', so you can pick a different supplier.',
    function () {
      api('/purchase/requests/{{ $request->id }}/quotes/items/' + quoteItemId + '/unaward', 'POST')
        .then(function (body) {
          applyItemUpdate(body);
          showToast(body.message, 'success');
        })
        .catch(function (err) { showToast(err.message || 'Could not remove this award.', 'error'); });
    }
  );
}

function openAwardDetailModal(quoteItemId) {
  var a = AWARD_DATA[quoteItemId];
  if (!a) return;

  document.getElementById('adm-item').textContent        = a.item;
  document.getElementById('adm-supplier').textContent    = a.supplier;
  document.getElementById('adm-unit-price').textContent  = 'BD ' + a.unitPrice;
  document.getElementById('adm-total-price').textContent = 'BD ' + a.totalPrice;
  document.getElementById('adm-awarded-by').textContent  = a.awardedBy || '—';
  document.getElementById('adm-awarded-at').textContent  = a.awardedAt || '—';
  document.getElementById('adm-reason').textContent      = a.reason || '—';

  document.getElementById('adm-remove-btn').onclick = function () {
    closeAwardDetailModal();
    unawardItem(quoteItemId, a.supplier, a.item);
  };

  document.getElementById('award-detail-modal').style.display = 'flex';
}
function closeAwardDetailModal() {
  document.getElementById('award-detail-modal').style.display = 'none';
}
document.getElementById('award-detail-modal').addEventListener('click', function (e) {
  if (e.target === this) closeAwardDetailModal();
});

function escHtmlCmp(s) {
  return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

// Redraw the one item card that changed, plus the page-wide totals — no reload.
function applyItemUpdate(body) {
  if (body.item) renderItemCard(body.item);

  document.getElementById('grand-total-value').textContent = 'BD ' + Number(body.grandTotal).toFixed(3);
  document.getElementById('grand-total-subtotal-value').textContent = 'BD ' + Number(body.subtotal).toFixed(3);
  document.getElementById('grand-total-vat-value').textContent = 'BD ' + Number(body.vatAmount).toFixed(3);
  document.getElementById('grand-total-vat-rate').textContent = String(body.vatRate).replace(/\.?0+$/, '');
  document.getElementById('grand-total-breakdown').style.display = body.vatAmount > 0 ? 'block' : 'none';

  var subtitle = body.fullyAwarded ? 'Based on awarded suppliers per item' : 'Based on the awarded price where decided, lowest offer otherwise';
  if (body.unresolvedItems > 0) subtitle += ' · ' + body.unresolvedItems + ' item(s) with no quotes excluded';
  document.getElementById('grand-total-subtitle').textContent = subtitle;
  document.getElementById('fully-awarded-banner').style.display = body.fullyAwarded ? 'block' : 'none';

  renderAwardedTab();
}

function renderItemCard(item) {
  var badge = document.getElementById('item-badge-' + item.id);
  badge.style.background = item.badge.bg;
  badge.style.color = item.badge.fg;
  badge.textContent = item.badge.label;

  var tbody = document.getElementById('item-rows-' + item.id);
  tbody.innerHTML = '';

  // Keep AWARD_DATA in sync so the detail modal reflects the latest state.
  item.rows.forEach(function (row) {
    if (row.item && row.item.isAwarded) {
      AWARD_DATA[row.item.id] = {
        item: item.description, supplier: row.supplier,
        unitPrice: row.item.unitPrice, totalPrice: row.item.totalPrice,
        reason: row.item.awardReason, awardedAt: row.item.awardedAt, awardedBy: row.item.awardedBy,
      };
    } else if (row.item) {
      delete AWARD_DATA[row.item.id];
    }
  });

  item.rows.forEach(function (row) {
    var qi = row.item;
    var tr = document.createElement('tr');
    tr.style.borderTop = '1px solid #f8fafc';
    tr.style.background = row.isMin ? '#f0fdf4' : '';

    var supplierCell = '<td style="padding:8px 10px;text-align:left;color:#0f172a;font-weight:500;">' + escHtmlCmp(row.supplier);
    if (qi && qi.supplierDescription) {
      supplierCell += '<div style="font-size:10px;color:#64748b;font-style:italic;margin-top:2px;">"' + escHtmlCmp(qi.supplierDescription) + '" ' +
        '<span style="background:#fef3c7;color:#92400e;font-size:9px;font-weight:700;padding:1px 5px;border-radius:3px;border:1px solid #fde68a;font-style:normal;margin-left:2px;">adjusted</span></div>';
    }
    supplierCell += '</td>';

    var restCells;
    if (!qi) {
      restCells = '<td colspan="2" style="padding:8px 10px;text-align:center;">' +
        '<span style="font-size:11px;font-weight:700;color:#64748b;background:#f1f5f9;padding:2px 8px;border-radius:4px;border:1px solid #e2e8f0;">Not quoted</span></td>' +
        '<td style="padding:8px 10px;text-align:center;"></td>';
    } else if (qi.notAvailable) {
      restCells = '<td colspan="2" style="padding:8px 10px;text-align:center;">' +
        '<span style="font-size:11px;font-weight:700;color:#dc2626;background:#fef2f2;padding:2px 8px;border-radius:4px;border:1px solid #fecaca;">Not available</span></td>' +
        '<td style="padding:8px 10px;text-align:center;"></td>';
    } else {
      var priceColor = qi.isAwarded ? '#15803d' : (row.isMin ? '#2563eb' : '#0f172a');
      var statusHtml = '';
      if (row.isMin) statusHtml += '<span style="font-size:10px;font-weight:700;color:#2563eb;">LOWEST</span>';
      if (qi.isAwarded) {
        statusHtml += '<button type="button" onclick="openAwardDetailModal(' + qi.id + ')" ' +
          'style="font-size:10px;font-weight:700;color:#15803d;background:#dcfce7;padding:3px 10px;border-radius:10px;border:none;cursor:pointer;">✓ AWARDED</button>';
      } else if (!item.hasAward) {
        statusHtml += '<button type="button" onclick="openAwardModal(' + qi.id + ', \'' + escHtmlCmp(row.supplier).replace(/'/g, "\\'") + '\', \'' + escHtmlCmp(item.description).replace(/'/g, "\\'") + '\', \'BD ' + qi.unitPrice + '\')" ' +
          'style="padding:5px 12px;background:#f59e0b;color:#fff;border:none;border-radius:6px;font-size:11px;font-weight:700;cursor:pointer;">Award →</button>';
      }
      var vatBadge = qi.isVatable ? ' <span title="VAT applicable" style="font-size:9px;font-weight:700;color:#0ea5e9;background:#e0f2fe;padding:1px 4px;border-radius:3px;border:1px solid #bae6fd;">VAT</span>' : '';
      restCells = '<td style="padding:8px 10px;text-align:center;font-weight:600;color:' + priceColor + ';">BD ' + qi.unitPrice + vatBadge + '</td>' +
        '<td style="padding:8px 10px;text-align:center;color:#64748b;">BD ' + qi.totalPrice + '</td>' +
        '<td style="padding:8px 10px;text-align:center;"><div style="display:flex;align-items:center;justify-content:center;gap:6px;">' + statusHtml + '</div></td>';
    }

    tr.innerHTML = supplierCell + restCells;
    tbody.appendChild(tr);
  });
}

// ---- Tabs ----
function showCompareTab(tab) {
  var activeStyle   = 'background:#fff;color:#0f172a;box-shadow:0 1px 3px rgba(0,0,0,.08);';
  var inactiveStyle = 'background:transparent;color:#64748b;box-shadow:none;';
  document.getElementById('tab-panel-comparison').style.display = tab === 'comparison' ? 'block' : 'none';
  document.getElementById('tab-panel-awarded').style.display    = tab === 'awarded'    ? 'block' : 'none';
  document.getElementById('tab-btn-comparison').style.cssText += activeStyle;
  document.getElementById('tab-btn-awarded').style.cssText    += activeStyle;
  document.getElementById('tab-btn-' + (tab === 'comparison' ? 'awarded' : 'comparison')).style.cssText += inactiveStyle;
  if (tab === 'awarded') renderAwardedTab();
}

function renderAwardedTab() {
  var body = document.getElementById('awarded-tab-body');
  if (!body) return;

  var ids = Object.keys(AWARD_DATA || {});
  if (ids.length === 0) {
    body.innerHTML = '<div style="background:#fff;border-radius:14px;box-shadow:0 2px 10px rgba(0,0,0,.05);padding:50px 0;text-align:center;color:#94a3b8;font-size:13px;">No items have been awarded yet.</div>';
    return;
  }

  // Group by supplier
  var bySupplier = {};
  ids.forEach(function (id) {
    var a = AWARD_DATA[id];
    if (!bySupplier[a.supplier]) bySupplier[a.supplier] = [];
    bySupplier[a.supplier].push(a);
  });

  var grandTotal = 0;
  var html = '';
  Object.keys(bySupplier).sort().forEach(function (supplierName) {
    var rows = bySupplier[supplierName];
    var supplierTotal = rows.reduce(function (sum, r) { return sum + parseFloat(r.totalPrice.replace(/,/g, '')); }, 0);
    grandTotal += supplierTotal;

    html += '<div style="background:#fff;border-radius:14px;box-shadow:0 2px 10px rgba(0,0,0,.05);overflow:hidden;margin-bottom:16px;">';
    html += '<div style="padding:14px 20px;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between;align-items:center;background:#f0fdf4;">';
    html += '<div style="font-size:14px;font-weight:700;color:#15803d;">✓ ' + escHtmlCmp(supplierName) + '</div>';
    html += '<div style="font-size:13px;font-weight:700;color:#15803d;">BD ' + supplierTotal.toFixed(3) + '</div>';
    html += '</div>';
    html += '<div style="padding:6px 20px 4px;">';
    rows.forEach(function (r, i) {
      html += '<div style="padding:12px 0;' + (i > 0 ? 'border-top:1px solid #f8fafc;' : '') + '">';
      html += '<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;">';
      html += '<div>';
      html += '<div style="font-size:13px;font-weight:600;color:#0f172a;">' + escHtmlCmp(r.item) + '</div>';
      html += '<div style="font-size:11px;color:#94a3b8;margin-top:2px;">' + (r.quantity !== undefined ? 'Qty: ' + r.quantity + (r.unit ? ' ' + escHtmlCmp(r.unit) : '') + ' · ' : '') + 'BD ' + r.unitPrice + ' / unit</div>';
      html += '</div>';
      html += '<div style="font-size:13px;font-weight:700;color:#0f172a;white-space:nowrap;">BD ' + r.totalPrice + '</div>';
      html += '</div>';
      if (r.reason) {
        html += '<div style="margin-top:8px;padding:9px 12px;background:#f8fafc;border-radius:8px;border:1px solid #f1f5f9;">';
        html += '<div style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.05em;margin-bottom:3px;">Reason</div>';
        html += '<div style="font-size:12.5px;color:#374151;">' + escHtmlCmp(r.reason) + '</div>';
        html += '</div>';
      }
      html += '<div style="font-size:10.5px;color:#cbd5e1;margin-top:6px;">' + (r.awardedBy ? 'Awarded by ' + escHtmlCmp(r.awardedBy) : '') + (r.awardedAt ? ' · ' + r.awardedAt : '') + '</div>';
      html += '</div>';
    });
    html += '</div></div>';
  });

  var unresolvedCount = (typeof ITEMS_TOTAL === 'number' ? ITEMS_TOTAL : 0) - ids.length;

  html += '<div style="padding:18px 20px;background:#0f172a;border-radius:12px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">';
  html += '<div>';
  html += '<div style="font-size:11px;font-weight:600;color:#94a3b8;text-transform:uppercase;letter-spacing:.05em;">Grand Total (Awarded)</div>';
  html += '<div style="font-size:11px;color:#64748b;margin-top:2px;">' + ids.length + ' item(s) awarded' + (unresolvedCount > 0 ? ' · ' + unresolvedCount + ' item(s) not yet awarded' : '') + '</div>';
  html += '</div>';
  html += '<div style="font-size:24px;font-weight:700;color:#fff;">BD ' + grandTotal.toFixed(3) + '</div>';
  html += '</div>';

  body.innerHTML = html;
}

document.addEventListener('DOMContentLoaded', function () {
  if (typeof AWARD_DATA !== 'undefined') renderAwardedTab();
});
</script>
@endsection
