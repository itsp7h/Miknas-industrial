@extends('layouts.app')

@section('title', 'GM Signature — ' . $request->request_number)

@section('content')
<div style="max-width:640px;margin:0 auto;">
  <div style="background:#fff;border-radius:16px;box-shadow:0 4px 24px rgba(0,0,0,.08);overflow:hidden;">

    <!-- Header -->
    <div style="background:linear-gradient(135deg,#7c3aed,#4f46e5);padding:24px 28px;">
      <div style="font-size:11px;font-weight:600;color:rgba(255,255,255,.7);text-transform:uppercase;letter-spacing:.06em;">GM Digital Signature</div>
      <div style="font-size:20px;font-weight:700;color:#fff;margin-top:4px;">{{ $request->request_number }}</div>
      @if($request->project_name)
      <div style="font-size:13px;color:rgba(255,255,255,.8);margin-top:2px;">{{ $request->project_name }}</div>
      @endif
    </div>

    <div style="padding:28px;">

      @if($request->signature)
        <!-- Already signed -->
        <div style="text-align:center;margin-bottom:8px;">
          <img src="{{ $request->signature->signature_image }}"
               style="max-width:100%;border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc;">
          <div style="font-size:12px;color:#64748b;margin-top:10px;">
            Signed by <strong>{{ $request->signature->signedBy->name }}</strong>
            on {{ $request->signature->signed_at->format('d M Y, H:i') }}
          </div>
        </div>
        <a href="/app/purchase/pipeline"
           style="display:block;text-align:center;margin-top:20px;padding:10px;background:#f1f5f9;color:#475569;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;">
          ← Back to Pipeline
        </a>

      @else
        <!-- Signature pad -->
        <p style="font-size:13px;color:#475569;margin-bottom:16px;">
          Draw your signature below using your mouse or touch screen to approve this purchase request.
        </p>

        <div style="position:relative;">
          <canvas id="sig-canvas" width="580" height="200"
            style="width:100%;border:2px dashed #cbd5e1;border-radius:10px;cursor:crosshair;touch-action:none;background:#fafafa;display:block;">
          </canvas>
          <div id="sig-hint" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none;">
            <span style="font-size:13px;color:#cbd5e1;font-style:italic;">Sign here</span>
          </div>
        </div>

        <div style="display:flex;gap:10px;margin-top:14px;">
          <button type="button" onclick="clearCanvas()"
            style="flex:1;padding:11px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;font-weight:600;color:#64748b;background:#f8fafc;cursor:pointer;">
            Clear
          </button>
          <button type="button" onclick="submitSignature()"
            style="flex:2;padding:11px;border:none;border-radius:8px;font-size:13px;font-weight:700;color:#fff;background:linear-gradient(135deg,#7c3aed,#4f46e5);cursor:pointer;">
            Confirm Signature →
          </button>
        </div>

        <form id="sig-form" method="POST" action="{{ route('purchase.requests.sign.store', $request) }}">
          @csrf
          <input type="hidden" name="signature_image" id="sig-data">
        </form>

        <p style="font-size:11px;color:#94a3b8;margin-top:14px;text-align:center;">
          By signing, you approve this purchase request. This action is recorded with your name, timestamp, and IP address.
        </p>
      @endif

    </div>
  </div>
</div>

<script>
(function () {
  const canvas = document.getElementById('sig-canvas');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  const hint = document.getElementById('sig-hint');
  let drawing = false;
  let hasMark = false;

  ctx.lineWidth   = 2.5;
  ctx.lineCap     = 'round';
  ctx.lineJoin    = 'round';
  ctx.strokeStyle = '#1e293b';

  function pos(e) {
    const r = canvas.getBoundingClientRect();
    const sx = canvas.width  / r.width;
    const sy = canvas.height / r.height;
    const src = e.touches ? e.touches[0] : e;
    return { x: (src.clientX - r.left) * sx, y: (src.clientY - r.top) * sy };
  }

  function start(e) { e.preventDefault(); drawing = true; const p = pos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); if (!hasMark) { hasMark = true; if (hint) hint.style.display = 'none'; } }
  function move(e)  { e.preventDefault(); if (!drawing) return; const p = pos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); ctx.beginPath(); ctx.moveTo(p.x, p.y); }
  function end()    { drawing = false; }

  canvas.addEventListener('mousedown',  start);
  canvas.addEventListener('mousemove',  move);
  canvas.addEventListener('mouseup',    end);
  canvas.addEventListener('mouseleave', end);
  canvas.addEventListener('touchstart', start, { passive: false });
  canvas.addEventListener('touchmove',  move,  { passive: false });
  canvas.addEventListener('touchend',   end);

  window.clearCanvas = function () {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    hasMark = false;
    if (hint) hint.style.display = 'flex';
  };

  window.submitSignature = function () {
    if (!hasMark) { showToast('Please draw your signature first.', 'warn'); return; }
    document.getElementById('sig-data').value = canvas.toDataURL('image/png');
    document.getElementById('sig-form').submit();
  };
})();
</script>
@endsection
