<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Quote Request</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:system-ui,-apple-system,sans-serif;">
  <div style="max-width:520px;margin:32px auto;background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.08);">

    <!-- Header bar -->
    <div style="background:linear-gradient(135deg,#2563eb,#1d4ed8);padding:28px 32px;">
      <div style="font-size:12px;font-weight:600;color:rgba(255,255,255,.7);text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px;">Quote Request</div>
      <div style="font-size:22px;font-weight:700;color:#fff;">{{ $invitation->purchaseRequest->request_number }}</div>
      @if($invitation->purchaseRequest->project_name)
      <div style="font-size:13px;color:rgba(255,255,255,.8);margin-top:4px;">{{ $invitation->purchaseRequest->project_name }}</div>
      @endif
    </div>

    <!-- Body -->
    <div style="padding:32px;">
      <p style="font-size:15px;color:#334155;margin:0 0 12px;">Dear <strong>{{ $invitation->supplier->name }}</strong>,</p>
      <p style="font-size:14px;color:#475569;line-height:1.65;margin:0 0 28px;">
        You have been invited to submit a price quotation. Please click the button below to view the required items and submit your quote. The link is private to your company and expires in 7 days.
      </p>

      <a href="{{ route('rfq.show', $invitation->token) }}"
         style="display:inline-block;background:#2563eb;color:#fff;text-decoration:none;padding:13px 32px;border-radius:9px;font-size:14px;font-weight:700;letter-spacing:.02em;">
        Submit My Quote →
      </a>

      <p style="font-size:12px;color:#94a3b8;margin:28px 0 0;">
        This link can only be submitted once and expires on <strong>{{ $invitation->expires_at->format('d M Y') }}</strong>.
        If the button doesn't work, copy this URL into your browser:<br>
        <span style="color:#2563eb;word-break:break-all;">{{ route('rfq.show', $invitation->token) }}</span>
      </p>
    </div>
  </div>
</body>
</html>
