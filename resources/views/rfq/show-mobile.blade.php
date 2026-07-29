<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Quote Request — {{ $purchaseRequest->request_number }}</title>
  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background-color: #f8fafc;
      font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
      -webkit-font-smoothing: antialiased;
      padding-bottom: 90px; /* space for sticky bottom bar */
    }

    .form-control, .form-select {
      border: 1px solid #e2e8f0;
      background-color: #ffffff;
      border-radius: 12px;
    }
    .form-control:focus {
      border-color: #3b82f6;
      box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
    }

    .form-floating > label {
      color: #64748b;
      font-weight: 500;
    }

    .list-group-item {
      border-color: #f1f5f9;
      padding: 1rem 0;
      background: transparent;
    }

    .code-display {
      letter-spacing: 0.25em;
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    }
    .confirm-input {
      letter-spacing: 0.1em;
      text-align: center;
    }

    .item-card.is-na { opacity: .55; }

    .sticky-bottom-bar {
      position: fixed;
      bottom: 0;
      left: 0;
      right: 0;
      background: rgba(255, 255, 255, 0.9);
      backdrop-filter: blur(10px);
      -webkit-backdrop-filter: blur(10px);
      border-top: 1px solid #e2e8f0;
      padding: 1rem 1.5rem;
      padding-bottom: calc(1rem + env(safe-area-inset-bottom));
      z-index: 1030;
    }
  </style>
</head>
<body>

  <div class="mx-auto" style="max-width: 600px;">

    {{-- Top banner --}}
    <div class="bg-dark text-white p-4 mx-3 mt-3 shadow-sm" style="border-radius: 16px;">
      <div class="text-uppercase fw-bold text-white-50" style="font-size: 0.7rem; letter-spacing: 0.1em;">
        Request for Quotation
      </div>
      <h2 class="fw-bolder mb-1 fs-3 mt-1">{{ $purchaseRequest->request_number }}</h2>
      @if($purchaseRequest->project_name)
      <div class="text-white-50 small fw-medium">{{ $purchaseRequest->project_name }}</div>
      @endif
    </div>

    <div class="p-4">

      <div class="mb-4">
        <h1 class="fs-4 fw-bold text-dark mb-2">Hello, {{ $invitation->supplier->name }}</h1>
        <p class="text-secondary small lh-sm">
          Please enter your unit prices below. This secure link is private to your company and allows a single submission.
        </p>
      </div>

      <form method="POST" action="{{ route('rfq.submit', $invitation->token) }}">
        @csrf

        <h6 class="text-uppercase fw-bold text-muted mb-3 mt-4" style="font-size: 0.75rem; letter-spacing: 0.05em;">Requested Items</h6>

        @foreach($items as $i => $item)
        <div class="bg-white p-3 rounded-4 shadow-sm border border-light mb-4 item-card" id="row-{{ $i }}">

          <div class="d-flex justify-content-between align-items-start mb-3">
            <div>
              <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-light text-secondary border px-2 py-1" style="font-size: 0.7rem;">Item #{{ $i + 1 }}</span>
                <span class="text-primary small fw-bold">{{ rtrim(rtrim(number_format((float)$item->quantity_required, 3), '0'), '.') }} {{ $item->unit ?: '' }}</span>
              </div>
              <div class="d-flex align-items-center gap-2 flex-wrap">
                <span id="desc-text-{{ $i }}" class="fw-bold text-dark fs-5">{{ $item->description }}</span>
                <span id="desc-adj-{{ $i }}" class="badge bg-warning text-dark d-none" style="font-size: 0.65rem;">adjusted</span>
              </div>
            </div>
            <button type="button" onclick="editDesc({{ $i }})" class="btn btn-sm btn-light border text-primary fw-medium rounded-pill px-3 py-1 flex-shrink-0">
              ✎ Edit Name
            </button>
          </div>

          <div id="desc-edit-{{ $i }}" class="d-none align-items-center gap-2 mb-3 bg-light p-2 rounded-3">
            <input type="text" id="desc-input-{{ $i }}" value="{{ $item->description }}" class="form-control border-primary">
            <button type="button" onclick="saveDesc({{ $i }}, {{ json_encode($item->description) }})" class="btn btn-primary fw-bold px-3">✓</button>
            <button type="button" onclick="cancelDesc({{ $i }})" class="btn btn-outline-secondary px-3">✕</button>
          </div>
          <input type="hidden" name="items[{{ $i }}][supplier_description]" id="desc-hidden-{{ $i }}" value="">

          <ul class="list-group list-group-flush mb-3">
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <label class="form-check-label fw-medium text-dark w-100" for="na-{{ $i }}">Item Not Available</label>
              <div class="form-check form-switch m-0 fs-5">
                <input class="form-check-input" type="checkbox" role="switch" name="items[{{ $i }}][not_available]" id="na-{{ $i }}" value="1"
                       onchange="toggleNA({{ $i }}, {{ (float)$item->quantity_required }})">
              </div>
            </li>
            @if($vatRate > 0)
            <li class="list-group-item d-flex justify-content-between align-items-center border-bottom-0">
              <label class="form-check-label fw-medium text-dark w-100" for="vat-cb-{{ $i }}">Apply {{ rtrim(rtrim(number_format($vatRate, 2), '0'), '.') }}% VAT</label>
              <div class="form-check form-switch m-0 fs-5">
                <input class="form-check-input" type="checkbox" role="switch" name="items[{{ $i }}][is_vatable]" id="vat-cb-{{ $i }}" value="1"
                       onchange="calcRow({{ $i }}, {{ (float)$item->quantity_required }})">
              </div>
            </li>
            @endif
          </ul>

          <div class="form-floating mb-3">
            <input type="number" class="form-control form-control-lg bg-light border-0 fs-4 fw-bold text-primary" id="price-{{ $i }}" name="items[{{ $i }}][unit_price]"
                   min="0" step="0.001" required placeholder="0.000" oninput="calcRow({{ $i }}, {{ (float)$item->quantity_required }})">
            <label for="price-{{ $i }}">Unit Price (BD)</label>
          </div>

          <div class="d-flex justify-content-between align-items-center bg-light rounded-3 p-3">
            <span class="text-secondary small fw-medium">Item Total</span>
            <span class="fw-bolder text-dark fs-5" id="tot-{{ $i }}">—</span>
          </div>
        </div>
        @endforeach

        <h6 class="text-uppercase fw-bold text-muted mb-3 mt-4" style="font-size: 0.75rem; letter-spacing: 0.05em;">Summary</h6>
        <div class="bg-dark text-white rounded-4 p-4 shadow-sm mb-4">
          <div class="d-flex justify-content-between mb-2 text-white-50 small fw-medium">
            <span>Subtotal</span>
            <span id="subtotal-row">BD 0.000</span>
          </div>
          <div class="d-flex justify-content-between mb-3 text-info small fw-medium" id="vat-tr" style="{{ $vatRate > 0 ? '' : 'display:none;' }}">
            <span>VAT ({{ rtrim(rtrim(number_format($vatRate, 2), '0'), '.') }}%)</span>
            <span id="vat-row">BD 0.000</span>
          </div>
          <hr class="border-secondary opacity-25 my-2">
          <div class="d-flex justify-content-between align-items-end mt-2">
            <span class="fw-medium text-white-50">Grand Total</span>
            <span id="grand-total" class="fw-bolder fs-3 text-white">BD 0.000</span>
          </div>
        </div>

        <h6 class="text-uppercase fw-bold text-muted mb-3 mt-4" style="font-size: 0.75rem; letter-spacing: 0.05em;">Logistics & Terms</h6>
        <div class="bg-white rounded-4 p-3 shadow-sm border border-light mb-4">
          <div class="form-floating mb-3">
            <input type="number" class="form-control bg-light border-0" id="lead_time_days" name="lead_time_days" min="0" placeholder="Days">
            <label for="lead_time_days">Delivery Time (days)</label>
          </div>
          <div class="form-floating mb-3">
            <input type="text" class="form-control bg-light border-0" id="payment_terms" name="payment_terms" placeholder="Terms">
            <label for="payment_terms">Payment Terms (e.g. 30 days net)</label>
          </div>
          <div class="form-floating">
            <textarea class="form-control bg-light border-0" id="notes" name="notes" style="height: 100px" placeholder="Notes"></textarea>
            <label for="notes">Additional Notes / Remarks</label>
          </div>
        </div>

        <h6 class="text-uppercase fw-bold text-muted mb-3 mt-4" style="font-size: 0.75rem; letter-spacing: 0.05em;">Agreement</h6>
        <div class="bg-white rounded-4 p-4 shadow-sm border border-light mb-4">
          <ul class="list-unstyled mb-4 d-flex flex-column gap-3 text-secondary small fw-medium">
            <li class="d-flex gap-3">
              <span class="text-primary fs-6">✓</span>
              Prices are valid for <strong class="text-dark">30 days</strong> from submission.
            </li>
            <li class="d-flex gap-3">
              <span class="text-primary fs-6">✓</span>
              Delivery will be made within the stated timeframe.
            </li>
            <li class="d-flex gap-3">
              <span class="text-primary fs-6">✓</span>
              All items supplied will meet the required specifications and quality standards.
            </li>
            <li class="d-flex gap-3">
              <span class="text-primary fs-6">✓</span>
              This quote is <strong class="text-dark">binding upon acceptance</strong>.
            </li>
          </ul>

          <div class="form-check bg-light p-3 rounded-3 d-flex align-items-center gap-2 m-0">
            <input class="form-check-input m-0 flex-shrink-0 fs-4" type="checkbox" name="terms" id="terms-cb" value="1" required onchange="checkReady()">
            <label class="form-check-label fw-bold text-dark m-0" for="terms-cb" style="cursor: pointer; padding-top: 2px;">
              I agree to the terms above
            </label>
          </div>
          @error('terms')
            <div class="text-danger small mt-2">{{ $message }}</div>
          @enderror
        </div>

        <div class="bg-warning bg-opacity-10 rounded-4 p-4 mb-4 border border-warning border-opacity-25">
          <h6 class="text-uppercase fw-bold mb-3 text-center" style="color: #b45309; font-size: 0.75rem; letter-spacing: 0.05em;">Security Confirmation</h6>

          <div class="text-center mb-3">
            <div class="small mb-1 fw-medium" style="color: #b45309;">Copy this code:</div>
            <div class="code-display text-dark fs-2 fw-bold">{{ $confirmCode }}</div>
          </div>

          <div class="form-floating">
            <input type="text" class="form-control form-control-lg confirm-input bg-white border-warning fw-bold text-uppercase" name="confirm_code" id="confirm-input"
                   autocomplete="off" autocorrect="off" autocapitalize="characters" spellcheck="false" placeholder="Code" oninput="checkReady()">
            <label for="confirm-input" style="color: #b45309;">Paste confirmation code</label>
          </div>
          @error('confirm_code')
            <div class="text-danger small mt-2">{{ $message }}</div>
          @enderror
        </div>

        <p class="text-center text-secondary small mb-4 pb-4">
          Link expires {{ $invitation->expires_at->format('d M Y') }}<br>One submission only
        </p>

        <div class="sticky-bottom-bar mx-auto" style="max-width: 600px;">
          <button class="btn btn-primary btn-lg w-100 fw-bold rounded-pill py-3 shadow-sm" type="submit" id="submit-btn" disabled style="opacity: 0.5; transition: opacity 0.3s ease;">
            Submit Quotation
          </button>
        </div>

      </form>
    </div>
  </div>

  <!-- Bootstrap 5 JS Bundle -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
  var _vatRate  = {{ $vatRate }};
  var _totals   = {};
  var _vatable  = {};
  var _navail   = {};

  // ── Description inline edit ──────────────────────────────
  function editDesc(i) {
    document.getElementById('desc-edit-' + i).classList.remove('d-none');
    document.getElementById('desc-edit-' + i).classList.add('d-flex');
    document.getElementById('desc-input-' + i).focus();
  }

  function saveDesc(i, original) {
    var val = document.getElementById('desc-input-' + i).value.trim();
    if (!val) val = original;
    document.getElementById('desc-text-' + i).textContent = val;
    document.getElementById('desc-hidden-' + i).value = (val !== original) ? val : '';
    var adj = document.getElementById('desc-adj-' + i);
    adj.classList.toggle('d-none', val === original);
    document.getElementById('desc-edit-' + i).classList.add('d-none');
    document.getElementById('desc-edit-' + i).classList.remove('d-flex');
  }

  function cancelDesc(i) {
    var original = document.getElementById('desc-text-' + i).textContent;
    document.getElementById('desc-input-' + i).value = original;
    document.getElementById('desc-edit-' + i).classList.add('d-none');
    document.getElementById('desc-edit-' + i).classList.remove('d-flex');
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
      if (vatCb) { vatCb.checked = false; vatCb.disabled = true; }
      totEl.innerHTML = '<span class="badge bg-danger-subtle text-danger">Not available</span>';
      row.classList.add('is-na');
      _totals[i]  = 0;
      _vatable[i] = false;
    } else {
      priceIn.disabled = false;
      priceIn.setAttribute('required', '');
      if (vatCb) { vatCb.disabled = false; }
      totEl.textContent = '—';
      row.classList.remove('is-na');
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
    btn.style.opacity = ok ? '1' : '.5';
  }
  </script>
</body>
</html>
