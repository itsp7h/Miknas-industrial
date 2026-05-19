<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Link Expired</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0;}
    body{font-family:system-ui,-apple-system,sans-serif;background:#fef2f2;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;}
    .card{background:#fff;border-radius:20px;box-shadow:0 8px 32px rgba(0,0,0,.10);padding:52px 44px;max-width:480px;width:100%;text-align:center;}
  </style>
</head>
<body>
  <div class="card">
    <div style="font-size:60px;margin-bottom:20px;">⏰</div>
    <div style="font-size:24px;font-weight:700;color:#dc2626;margin-bottom:10px;">Link Expired</div>
    <div style="font-size:14px;color:#475569;line-height:1.65;">
      This quote invitation link has expired (valid for 7 days after sending).
      Please contact the purchasing team if you still wish to submit a quote.
    </div>
    @if($invitation->expires_at)
    <div style="margin-top:28px;padding:14px;background:#fef2f2;border-radius:10px;font-size:12px;color:#64748b;">
      Expired on {{ $invitation->expires_at->format('d M Y') }}
    </div>
    @endif
  </div>
</body>
</html>
