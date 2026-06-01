<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Quote Request — {{ $purchaseRequest->request_number }}</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0;}
    body{font-family:system-ui,-apple-system,sans-serif;background:#f1f5f9;min-height:100vh;}
    label.fl{display:block;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.05em;margin-bottom:5px;}
    input[type=text],input[type=number],textarea{width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;outline:none;background:#fff;}
    input:focus,textarea:focus{border-color:#2563eb;}
    .btn{width:100%;padding:15px;background:linear-gradient(135deg,#2563eb,#1d4ed8);color:#fff;border:none;border-radius:10px;font-size:15px;font-weight:700;cursor:pointer;}
    table{width:100%;border-collapse:collapse;font-size:13px;}
    thead th{background:#f8fafc;padding:10px 12px;text-align:left;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;}
    tbody td{padding:10px 12px;border-bottom:1px solid #f1f5f9;vertical-align:middle;}
    tfoot td{padding:12px;font-weight:700;}
    input[type=number].price{width:130px;text-align:right;}
    .two-col{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;}

    /* ── Desktop: centered card ── */
    @media(min-width:641px){
      body{padding:28px 16px;}
      .card{background:#fff;border-radius:16px;box-shadow:0 4px 24px rgba(0,0,0,.08);max-width:740px;margin:0 auto;overflow:hidden;}
      .hd{background:linear-gradient(135deg,#2563eb,#1d4ed8);padding:28px 32px;}
      .body{padding:32px;}
    }

    /* ── Mobile: full page ── */
    @media(max-width:640px){
      body{background:#fff;padding:0;}
      .card{background:#fff;border-radius:0;box-shadow:none;max-width:100%;}
      .hd{background:linear-gradient(135deg,#2563eb,#1d4ed8);padding:22px 18px;}
      .body{padding:18px 16px 32px;}
      .two-col{grid-template-columns:1fr;}
      /* Items table → stacked cards */
      table,thead,tbody,tfoot,tr,th,td{display:block;}
      thead{display:none;}
      tbody td{border:none;padding:3px 0;}
      tbody tr{border:1px solid #e2e8f0;border-radius:10px;padding:12px 14px;margin-bottom:10px;background:#fff;}
      tfoot tr{border-top:2px solid #e2e8f0;padding:12px 0;}
      input[type=number].price{width:100%;}
      /* Confirm code stacks */
      .code-row{flex-direction:column !important;align-items:stretch !important;}
      .code-display{display:block;text-align:center;}
    }
  </style>
</head>
<body>
<div class="card">

  {{-- Header --}}
  <div class="hd">
    <div style="font-size:11px;font-weight:600;color:rgba(255,255,255,.7);text-transform:uppercase;letter-spacing:.06em;">Request for Quotation</div>
    <div style="font-size:22px;font-weight:700;color:#fff;margin-top:4px;">{{ $purchaseRequest->request_number }}</div>
    @if($purchaseRequest->project_name)
    <div style="font-size:13px;color:rgba(255,255,255,.8);margin-top:2px;">{{ $purchaseRequest->project_name }}</div>
    @endif
  </div>

  <div class="body">
    <p style="font-size:14px;color:#334155;margin-bottom:6px;">
      Hello <strong>{{ $invitation->supplier->name }}</strong>,
    </p>
    <p style="font-size:13px;color:#64748b;margin-bottom:24px;">
      Please enter your unit prices for the items below, then scroll down and submit. This link is private to your company and can only be submitted once.
    </p>

    <form method="POST" action="{{ route('rfq.submit', $invitation->token) }}">
      @csrf

      {{-- Items table --}}
      <div style="overflow-x:auto;margin-bottom:24px;border:1px solid #e2e8f0;border-radius:10px;">
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Description</th>
              <th>Qty</th>
              <th>Unit</th>
              <th style="text-align:center;">N/A?</th>
              <th style="text-align:center;">VAT?</th>
              <th style="text-align:right;">Unit Price (BD)</th>
              <th style="text-align:right;">Total (BD)</th>
            </tr>
          </thead>
          <tbody>
            @foreach($items as $i => $item)
            <tr id="row-{{ $i }}">
              <td style="color:#94a3b8;font-size:12px;">{{ $i + 1 }}</td>
              <td>
                {{-- Display mode --}}
                <div id="desc-display-{{ $i }}" style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                  <span id="desc-text-{{ $i }}" style="font-weight:500;">{{ $item->description }}</span>
                  <button type="button" onclick="editDesc({{ $i }})"
                    title="Edit item name"
                    style="flex-shrink:0;background:none;border:1px solid #e2e8f0;border-radius:5px;padding:2px 6px;cursor:pointer;color:#64748b;font-size:11px;line-height:1.4;"
                    onmouseover="this.style.borderColor='#2563eb';this.style.color='#2563eb'"
                    onmouseout="this.style.borderColor='#e2e8f0';this.style.color='#64748b'">
                    ✎ Edit
                  </button>
                  <span id="desc-adj-{{ $i }}" style="display:none;font-size:10px;font-weight:700;background:#fef3c7;color:#92400e;padding:1px 6px;border-radius:4px;border:1px solid #fde68a;">adjusted</span>
                </div>
                {{-- Edit mode --}}
                <div id="desc-edit-{{ $i }}" style="display:none;align-items:center;gap:6px;">
                  <input type="text" id="desc-input-{{ $i }}"
                    value="{{ $item->description }}"
                    style="flex:1;min-width:0;padding:5px 8px;border:1.5px solid #2563eb;border-radius:6px;font-size:13px;font-weight:500;outline:none;width:auto;">
                  <button type="button" onclick="saveDesc({{ $i }}, {{ json_encode($item->description) }})"
                    style="flex-shrink:0;background:#2563eb;color:#fff;border:none;border-radius:5px;padding:4px 8px;cursor:pointer;font-size:12px;font-weight:700;">✓</button>
                  <button type="button" onclick="cancelDesc({{ $i }})"
                    style="flex-shrink:0;background:#f1f5f9;color:#64748b;border:1px solid #e2e8f0;border-radius:5px;padding:4px 8px;cursor:pointer;font-size:12px;">✕</button>
                </div>
                {{-- Hidden field submitted with form --}}
                <input type="hidden" name="items[{{ $i }}][supplier_description]" id="desc-hidden-{{ $i }}" value="">
              </td>
              <td>{{ rtrim(rtrim(number_format((float)$item->quantity_required, 3), '0'), '.') }}</td>
              <td style="color:#64748b;">{{ $item->unit ?: '—' }}</td>
              {{-- N/A checkbox --}}
              <td style="text-align:center;">
                <input type="checkbox" name="items[{{ $i }}][not_available]" id="na-{{ $i }}" value="1"
                       onchange="toggleNA({{ $i }}, {{ (float)$item->quantity_required }})"
                       style="width:16px;height:16px;accent-color:#dc2626;cursor:pointer;">
              </td>
              {{-- VAT checkbox --}}
              <td style="text-align:center;">
                @if($vatRate > 0)
                <input type="checkbox" name="items[{{ $i }}][is_vatable]" id="vat-cb-{{ $i }}" value="1"
                       onchange="calcRow({{ $i }}, {{ (float)$item->quantity_required }})"
                       style="width:16px;height:16px;accent-color:#2563eb;cursor:pointer;">
                @else
                <span style="color:#cbd5e1;font-size:11px;">—</span>
                @endif
              </td>
              <td style="text-align:right;">
                <input type="number" class="price" id="price-{{ $i }}" name="items[{{ $i }}][unit_price]"
                       min="0" step="0.001" required placeholder="0.000"
                       oninput="calcRow({{ $i }}, {{ (float)$item->quantity_required }})">
              </td>
              <td style="text-align:right;font-weight:600;" id="tot-{{ $i }}">—</td>
            </tr>
            @endforeach
          </tbody>
          <tfoot>
            <tr style="background:#f8fafc;">
              <td colspan="7" style="text-align:right;font-size:13px;color:#475569;">Subtotal:</td>
              <td style="text-align:right;font-size:14px;color:#475569;" id="subtotal-row">BD 0.000</td>
            </tr>
            <tr id="vat-tr" style="background:#fffbeb;{{ $vatRate > 0 ? '' : 'display:none;' }}">
              <td colspan="7" style="text-align:right;font-size:13px;color:#92400e;">VAT ({{ $vatRate }}%):</td>
              <td style="text-align:right;font-size:14px;color:#92400e;" id="vat-row">BD 0.000</td>
            </tr>
            <tr style="background:#f8fafc;border-top:2px solid #e2e8f0;">
              <td colspan="7" style="text-align:right;font-size:13px;color:#475569;font-weight:700;">Grand Total:</td>
              <td style="text-align:right;font-size:15px;color:#2563eb;font-weight:700;" id="grand-total">BD 0.000</td>
            </tr>
          </tfoot>
        </table>
      </div>

      {{-- Delivery / Payment --}}
      <div class="two-col">
        <div>
          <label class="fl">Delivery Time (days)</label>
          <input type="number" name="lead_time_days" min="0" placeholder="e.g. 14">
        </div>
        <div>
          <label class="fl">Payment Terms</label>
          <input type="text" name="payment_terms" placeholder="e.g. 30 days net">
        </div>
      </div>
      <div style="margin-bottom:20px;">
        <label class="fl">Notes / Remarks</label>
        <textarea name="notes" rows="3" placeholder="Any additional notes, conditions, or remarks…"></textarea>
      </div>

      {{-- T&C --}}
      <div style="background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:10px;padding:16px 18px;margin-bottom:16px;">
        <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px;">Terms &amp; Conditions</div>
        <ul style="list-style:none;padding:0;margin:0 0 14px;display:flex;flex-direction:column;gap:7px;">
          <li style="display:flex;align-items:flex-start;gap:9px;font-size:12px;color:#475569;line-height:1.5;">
            <span style="flex-shrink:0;width:18px;height:18px;border-radius:50%;background:#dbeafe;display:flex;align-items:center;justify-content:center;margin-top:1px;">
              <svg width="9" height="9" viewBox="0 0 10 10" fill="none"><path d="M2 5.5L4 7.5L8 3" stroke="#2563eb" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </span>
            Prices stated in this quote are valid for <strong>30 days</strong> from the submission date.
          </li>
          <li style="display:flex;align-items:flex-start;gap:9px;font-size:12px;color:#475569;line-height:1.5;">
            <span style="flex-shrink:0;width:18px;height:18px;border-radius:50%;background:#dbeafe;display:flex;align-items:center;justify-content:center;margin-top:1px;">
              <svg width="9" height="9" viewBox="0 0 10 10" fill="none"><path d="M2 5.5L4 7.5L8 3" stroke="#2563eb" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </span>
            Delivery will be made within the specified delivery time stated above.
          </li>
          <li style="display:flex;align-items:flex-start;gap:9px;font-size:12px;color:#475569;line-height:1.5;">
            <span style="flex-shrink:0;width:18px;height:18px;border-radius:50%;background:#dbeafe;display:flex;align-items:center;justify-content:center;margin-top:1px;">
              <svg width="9" height="9" viewBox="0 0 10 10" fill="none"><path d="M2 5.5L4 7.5L8 3" stroke="#2563eb" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </span>
            All items supplied will meet the required specifications and quality standards.
          </li>
          <li style="display:flex;align-items:flex-start;gap:9px;font-size:12px;color:#475569;line-height:1.5;">
            <span style="flex-shrink:0;width:18px;height:18px;border-radius:50%;background:#dbeafe;display:flex;align-items:center;justify-content:center;margin-top:1px;">
              <svg width="9" height="9" viewBox="0 0 10 10" fill="none"><path d="M2 5.5L4 7.5L8 3" stroke="#2563eb" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </span>
            This quote is <strong>binding upon acceptance</strong> and constitutes a formal offer.
          </li>
        </ul>
        <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;padding:10px 12px;background:#fff;border:1.5px solid #e2e8f0;border-radius:8px;">
          <input type="checkbox" name="terms" id="terms-cb" value="1" required
            onchange="checkReady()"
            style="width:16px;height:16px;margin-top:1px;accent-color:#2563eb;flex-shrink:0;cursor:pointer;">
          <span style="font-size:13px;font-weight:600;color:#0f172a;">I have read and agree to the terms and conditions above</span>
        </label>
        @error('terms')
          <div style="font-size:12px;color:#dc2626;margin-top:6px;">{{ $message }}</div>
        @enderror
      </div>

      {{-- Confirmation code --}}
      <div style="background:#fffbeb;border:1.5px solid #fde68a;border-radius:10px;padding:16px 18px;margin-bottom:20px;">
        <div style="font-size:11px;font-weight:700;color:#92400e;text-transform:uppercase;letter-spacing:.05em;margin-bottom:10px;">Confirmation Required</div>
        <div class="code-row" style="display:flex;align-items:center;gap:14px;">
          <div style="flex-shrink:0;">
            <div style="font-size:11px;color:#92400e;margin-bottom:6px;">Copy this code:</div>
            <div class="code-display"
              style="font-size:24px;font-weight:800;letter-spacing:.2em;color:#92400e;
                     background:#fef3c7;border:2px dashed #f59e0b;border-radius:8px;
                     padding:10px 20px;font-family:monospace;user-select:all;white-space:nowrap;">
              {{ $confirmCode }}
            </div>
          </div>
          <div style="flex:1;min-width:0;">
            <label class="fl" style="color:#92400e;">Paste code here</label>
            <input type="text" name="confirm_code" id="confirm-input"
              autocomplete="off" autocorrect="off" autocapitalize="characters" spellcheck="false"
              placeholder="Paste the code above"
              oninput="checkReady()"
              style="padding:11px 12px;border:1.5px solid #fde68a;
                     font-size:16px;font-weight:700;letter-spacing:.12em;font-family:monospace;
                     text-transform:uppercase;outline:none;background:#fff;">
            @error('confirm_code')
              <div style="font-size:12px;color:#dc2626;margin-top:4px;">{{ $message }}</div>
            @enderror
          </div>
        </div>
      </div>

      <button class="btn" type="submit" id="submit-btn" disabled style="opacity:.4;cursor:not-allowed;">
        Submit My Quote →
      </button>
    </form>

    <p style="font-size:11px;color:#cbd5e1;margin-top:18px;text-align:center;">
      Link expires {{ $invitation->expires_at->format('d M Y') }} · One submission only
    </p>
  </div>
</div>

<script>
var _vatRate  = {{ $vatRate }};
var _totals   = {};
var _vatable  = {};
var _navail   = {};

// ── Description inline edit ──────────────────────────────
function editDesc(i) {
  document.getElementById('desc-display-' + i).style.display = 'none';
  var ed = document.getElementById('desc-edit-' + i);
  ed.style.display = 'flex';
  document.getElementById('desc-input-' + i).focus();
}

function saveDesc(i, original) {
  var val = document.getElementById('desc-input-' + i).value.trim();
  if (!val) val = original;
  document.getElementById('desc-text-' + i).textContent = val;
  document.getElementById('desc-hidden-' + i).value = (val !== original) ? val : '';
  var adj = document.getElementById('desc-adj-' + i);
  adj.style.display = (val !== original) ? 'inline-block' : 'none';
  document.getElementById('desc-edit-' + i).style.display = 'none';
  document.getElementById('desc-display-' + i).style.display = 'flex';
}

function cancelDesc(i) {
  var original = document.getElementById('desc-text-' + i).textContent;
  document.getElementById('desc-input-' + i).value = original;
  document.getElementById('desc-edit-' + i).style.display = 'none';
  document.getElementById('desc-display-' + i).style.display = 'flex';
}

// ── N/A toggle ───────────────────────────────────────────
function toggleNA(i, qty) {
  var naCb    = document.getElementById('na-' + i);
  var priceIn = document.getElementById('price-' + i);
  var vatCb   = document.getElementById('vat-cb-' + i);
  var totEl   = document.getElementById('tot-' + i);
  var row     = document.getElementById('row-' + i);
  var na      = naCb.checked;
  _navail[i]  = na;

  if (na) {
    priceIn.value    = '';
    priceIn.disabled = true;
    priceIn.removeAttribute('required');
    priceIn.style.opacity = '.35';
    if (vatCb) { vatCb.checked = false; vatCb.disabled = true; vatCb.style.opacity = '.35'; }
    totEl.innerHTML = '<span style="font-size:11px;font-weight:700;color:#dc2626;background:#fef2f2;padding:2px 7px;border-radius:4px;">Not available</span>';
    row.style.opacity = '.6';
    _totals[i]  = 0;
    _vatable[i] = false;
  } else {
    priceIn.disabled = false;
    priceIn.setAttribute('required', '');
    priceIn.style.opacity = '1';
    if (vatCb) { vatCb.disabled = false; vatCb.style.opacity = '1'; }
    totEl.textContent = '—';
    row.style.opacity = '1';
    _totals[i]  = 0;
    _vatable[i] = false;
    calcRow(i, qty);
    return;
  }
  recalcTotals();
}

// ── Price / VAT calculation ──────────────────────────────
function calcRow(i, qty) {
  if (_navail[i]) return;
  var inp = document.getElementById('price-' + i);
  var cb  = document.getElementById('vat-cb-' + i);
  var up  = parseFloat(inp ? inp.value : 0) || 0;
  var tot = Math.round(up * qty * 1000) / 1000;
  _totals[i]  = tot;
  _vatable[i] = cb ? cb.checked : false;
  var el = document.getElementById('tot-' + i);
  if (el) el.textContent = tot > 0 ? 'BD ' + tot.toFixed(3) : '—';
  recalcTotals();
}

function recalcTotals() {
  var subtotal  = 0;
  var vatAmount = 0;
  Object.keys(_totals).forEach(function(i) {
    if (_navail[i]) return;
    subtotal += _totals[i];
    if (_vatable[i] && _vatRate > 0) {
      vatAmount += Math.round(_totals[i] * _vatRate / 100 * 1000) / 1000;
    }
  });
  var grand = Math.round((subtotal + vatAmount) * 1000) / 1000;
  var elSub   = document.getElementById('subtotal-row');
  var elVat   = document.getElementById('vat-row');
  var elGrand = document.getElementById('grand-total');
  if (elSub)   elSub.textContent   = 'BD ' + subtotal.toFixed(3);
  if (elVat)   elVat.textContent   = 'BD ' + vatAmount.toFixed(3);
  if (elGrand) elGrand.textContent = 'BD ' + grand.toFixed(3);
}

var _expected = '{{ $confirmCode }}';
function checkReady() {
  var terms = document.getElementById('terms-cb');
  var inp   = document.getElementById('confirm-input');
  var btn   = document.getElementById('submit-btn');
  if (!terms || !inp || !btn) return;
  var codeOk = inp.value.trim().toUpperCase() === _expected;
  var ok = terms.checked && codeOk;
  btn.disabled = !ok;
  btn.style.opacity = ok ? '1' : '.4';
  btn.style.cursor  = ok ? 'pointer' : 'not-allowed';
  inp.style.borderColor = inp.value.length > 0 ? (codeOk ? '#16a34a' : '#ef4444') : '#fde68a';
}
</script>
</body>
</html>
