<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Quote Submitted — Thank You</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0;}
    body{font-family:system-ui,-apple-system,sans-serif;min-height:100vh;}
    @keyframes popIn{from{opacity:0;transform:scale(.88) translateY(18px);}to{opacity:1;transform:scale(1) translateY(0);}}
    @keyframes checkDraw{from{stroke-dashoffset:60;}to{stroke-dashoffset:0;}}
    @keyframes ringPulse{0%,100%{transform:scale(1);opacity:.3;}50%{transform:scale(1.2);opacity:.1;}}
    .detail-row{display:flex;align-items:center;justify-content:space-between;gap:12px;font-size:13px;padding:8px 0;}

    /* ── Desktop: centered card ── */
    @media(min-width:641px){
      body{background:linear-gradient(135deg,#f0fdf4 0%,#ecfdf5 50%,#d1fae5 100%);display:flex;align-items:center;justify-content:center;padding:28px 16px;}
      .card{background:#fff;border-radius:24px;box-shadow:0 20px 60px rgba(0,0,0,.10),0 4px 16px rgba(0,0,0,.06);max-width:500px;width:100%;overflow:hidden;animation:popIn .5s cubic-bezier(.34,1.56,.64,1) forwards;}
      .inner{padding:44px 40px 36px;text-align:center;}
      .footer-bar{padding:14px 40px;background:#f8fafc;border-top:1px solid #f1f5f9;text-align:center;}
    }

    /* ── Mobile: full page ── */
    @media(max-width:640px){
      body{background:#fff;display:flex;flex-direction:column;}
      .card{background:#fff;border-radius:0;box-shadow:none;width:100%;flex:1;display:flex;flex-direction:column;}
      .inner{padding:36px 20px 28px;text-align:center;flex:1;}
      .footer-bar{padding:14px 20px;background:#f8fafc;border-top:1px solid #f1f5f9;text-align:center;}
      .icon-wrap{margin-bottom:20px !important;}
    }
  </style>
</head>
<body>
<div class="card">

  <div style="height:6px;background:linear-gradient(90deg,#16a34a,#22c55e,#4ade80);"></div>

  <div class="inner">

    {{-- Animated check --}}
    <div class="icon-wrap" style="position:relative;display:inline-block;margin-bottom:28px;">
      <div style="position:absolute;inset:-12px;border-radius:50%;background:#dcfce7;animation:ringPulse 2.5s ease-in-out infinite;"></div>
      <div style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,#16a34a,#22c55e);display:flex;align-items:center;justify-content:center;position:relative;box-shadow:0 8px 24px rgba(22,163,74,.3);">
        <svg width="38" height="38" viewBox="0 0 38 38" fill="none">
          <path d="M10 19.5L16 25.5L28 13" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"
            style="stroke-dasharray:60;stroke-dashoffset:60;animation:checkDraw .6s .35s ease forwards;"/>
        </svg>
      </div>
    </div>

    <div style="font-size:11px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#16a34a;margin-bottom:8px;">Quote Received</div>
    <h1 style="font-size:26px;font-weight:800;color:#0f172a;margin-bottom:14px;line-height:1.25;">
      Thank you,<br>{{ $invitation->supplier->name }}!
    </h1>
    <p style="font-size:15px;color:#475569;line-height:1.75;max-width:360px;margin:0 auto 24px;">
      Your quote for <strong style="color:#0f172a;">{{ $invitation->purchaseRequest->request_number }}</strong>
      has been successfully received. Our team will review all submitted quotes and get back to you shortly.
    </p>

    {{-- Details --}}
    <div style="background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:12px;padding:4px 20px;text-align:left;margin-bottom:24px;">
      <div class="detail-row">
        <span style="color:#64748b;">Reference</span>
        <span style="font-weight:700;color:#0f172a;">{{ $invitation->purchaseRequest->request_number }}</span>
      </div>
      <div class="detail-row" style="border-top:1px solid #f1f5f9;">
        <span style="color:#64748b;">Submitted</span>
        <span style="font-weight:600;color:#0f172a;">{{ now()->format('d M Y, H:i') }}</span>
      </div>
      @if($invitation->purchaseRequest->project_name)
      <div class="detail-row" style="border-top:1px solid #f1f5f9;">
        <span style="color:#64748b;">Project</span>
        <span style="font-weight:600;color:#0f172a;">{{ $invitation->purchaseRequest->project_name }}</span>
      </div>
      @endif
    </div>

    <p style="font-size:13px;color:#94a3b8;line-height:1.65;">
      This link has now been closed. No further action is required.<br>
      We appreciate your time and look forward to working with you.
    </p>

  </div>

  <div class="footer-bar">
    <span style="font-size:11px;color:#cbd5e1;font-weight:500;letter-spacing:.03em;">SteelERP · Procurement Portal</span>
  </div>

</div>
</body>
</html>
