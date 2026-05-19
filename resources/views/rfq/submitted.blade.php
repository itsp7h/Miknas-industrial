<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Quote Submitted</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0;}
    body{font-family:system-ui,-apple-system,sans-serif;background:#f0fdf4;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;}
    .card{background:#fff;border-radius:20px;box-shadow:0 8px 32px rgba(0,0,0,.10);padding:52px 44px;max-width:480px;width:100%;text-align:center;}
  </style>
</head>
<body>
  <div class="card">
    <div style="font-size:60px;margin-bottom:20px;">✅</div>
    <div style="font-size:24px;font-weight:700;color:#15803d;margin-bottom:10px;">Quote Submitted!</div>
    <div style="font-size:14px;color:#475569;line-height:1.65;">
      Thank you, <strong>{{ $invitation->supplier->name }}</strong>. Your quote for
      <strong>{{ $invitation->purchaseRequest->request_number }}</strong> has been received
      and is under review. You will be contacted if you are selected.
    </div>
    <div style="margin-top:28px;padding:14px;background:#f0fdf4;border-radius:10px;font-size:12px;color:#64748b;">
      Submitted on {{ now()->format('d M Y, H:i') }}
    </div>
  </div>
</body>
</html>
