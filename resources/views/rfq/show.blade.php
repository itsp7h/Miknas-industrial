<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Quote Request — {{ $purchaseRequest->request_number }}</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0;}
    body{font-family:system-ui,-apple-system,sans-serif;background:#f1f5f9;min-height:100vh;padding:20px 16px;}
    .card{background:#fff;border-radius:16px;box-shadow:0 4px 24px rgba(0,0,0,.08);max-width:740px;margin:0 auto;overflow:hidden;}
    .hd{background:linear-gradient(135deg,#2563eb,#1d4ed8);padding:28px 32px;}
    .body{padding:32px;}
    label.fl{display:block;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.05em;margin-bottom:5px;}
    input[type=text],input[type=number],textarea{width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;outline:none;}
    input:focus,textarea:focus{border-color:#2563eb;}
    .btn{width:100%;padding:14px;background:linear-gradient(135deg,#2563eb,#1d4ed8);color:#fff;border:none;border-radius:10px;font-size:15px;font-weight:700;cursor:pointer;margin-top:24px;}
    table{width:100%;border-collapse:collapse;font-size:13px;}
    thead th{background:#f8fafc;padding:10px 12px;text-align:left;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;}
    tbody td{padding:10px 12px;border-bottom:1px solid #f1f5f9;vertical-align:middle;}
    tfoot td{padding:12px;font-weight:700;}
    input[type=number].price{width:130px;text-align:right;}
    @media(max-width:600px){
      .body{padding:20px;}
      table,thead,tbody,tfoot,tr,th,td{display:block;}
      thead{display:none;}
      tbody td{border:none;padding:4px 0;}
      tbody tr{border:1px solid #e2e8f0;border-radius:10px;padding:12px;margin-bottom:10px;}
      input[type=number].price{width:100%;}
    }
  </style>
</head>
<body>
<div class="card">
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
              <th style="text-align:right;">Unit Price (BD)</th>
              <th style="text-align:right;">Total (BD)</th>
            </tr>
          </thead>
          <tbody>
            @foreach($items as $i => $item)
            <tr>
              <td style="color:#94a3b8;font-size:12px;">{{ $i + 1 }}</td>
              <td style="font-weight:500;">{{ $item->description }}</td>
              <td>{{ rtrim(rtrim(number_format((float)$item->quantity, 3), '0'), '.') }}</td>
              <td style="color:#64748b;">{{ $item->unit ?: '—' }}</td>
              <td style="text-align:right;">
                <input type="number" class="price" name="items[{{ $i }}][unit_price]"
                       min="0" step="0.001" required placeholder="0.000"
                       oninput="calcRow({{ $i }}, {{ (float)$item->quantity }})">
              </td>
              <td style="text-align:right;font-weight:600;" id="tot-{{ $i }}">—</td>
            </tr>
            @endforeach
          </tbody>
          <tfoot>
            <tr style="background:#f8fafc;">
              <td colspan="5" style="text-align:right;font-size:13px;color:#475569;">Grand Total:</td>
              <td style="text-align:right;font-size:15px;color:#2563eb;" id="grand-total">BD 0.000</td>
            </tr>
          </tfoot>
        </table>
      </div>

      {{-- Terms --}}
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
        <div>
          <label class="fl">Lead Time (days)</label>
          <input type="number" name="lead_time_days" min="0" placeholder="e.g. 14">
        </div>
        <div>
          <label class="fl">Payment Terms</label>
          <input type="text" name="payment_terms" placeholder="e.g. 30 days net">
        </div>
      </div>
      <div>
        <label class="fl">Notes / Remarks</label>
        <textarea name="notes" rows="3" placeholder="Any additional notes, conditions, or remarks…"></textarea>
      </div>

      <button class="btn" type="submit">Submit My Quote →</button>
    </form>

    <p style="font-size:11px;color:#cbd5e1;margin-top:16px;text-align:center;">
      Link expires {{ $invitation->expires_at->format('d M Y') }} · One submission only
    </p>
  </div>
</div>

<script>
var _totals = {};
function calcRow(i, qty) {
  var inp = document.querySelector('input[name="items[' + i + '][unit_price]"]');
  var up  = parseFloat(inp ? inp.value : 0) || 0;
  var tot = Math.round(up * qty * 1000) / 1000;
  _totals[i] = tot;
  var el = document.getElementById('tot-' + i);
  if (el) el.textContent = 'BD ' + tot.toFixed(3);
  var grand = Object.values(_totals).reduce(function(a,b){return a+b;}, 0);
  var ge = document.getElementById('grand-total');
  if (ge) ge.textContent = 'BD ' + grand.toFixed(3);
}
</script>
</body>
</html>
