<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Purchase Order {{ $order->po_number }}</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:'Segoe UI',system-ui,-apple-system,Arial,sans-serif;">
  <div style="max-width:580px;margin:32px auto;background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08);">

    {{-- Header --}}
    <div style="background:linear-gradient(135deg,#16a34a,#15803d);padding:30px 36px;">
      <div style="font-size:11px;font-weight:700;color:rgba(255,255,255,.75);text-transform:uppercase;letter-spacing:.08em;margin-bottom:8px;">
        Purchase Order
      </div>
      <div style="font-size:24px;font-weight:700;color:#fff;line-height:1.2;">{{ $order->po_number }}</div>
      @if($order->purchaseRequest?->project_name)
      <div style="font-size:13px;color:rgba(255,255,255,.85);margin-top:6px;">{{ $order->purchaseRequest->project_name }}</div>
      @endif
    </div>

    {{-- Body --}}
    <div style="padding:36px;">
      <p style="font-size:15px;color:#1e293b;margin:0 0 18px;">
        Dear <strong>{{ $order->supplier->contact_person ?: $order->supplier->name }}</strong>,
      </p>

      <p style="font-size:14px;color:#475569;line-height:1.75;margin:0 0 18px;">
        We are pleased to confirm <strong>Purchase Order {{ $order->po_number }}</strong>, issued in your company's
        favour and enclosed with this email as a PDF document.
        Kindly review the attached order carefully and proceed to deliver the item(s) in accordance with the
        specifications, quantities, and terms stated within it.
      </p>

      {{-- Order summary card --}}
      <table cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;margin:0 0 22px;overflow:hidden;">
        <tr>
          <td style="padding:14px 18px;font-size:12px;color:#64748b;border-bottom:1px solid #e2e8f0;">PO Number</td>
          <td style="padding:14px 18px;font-size:13px;color:#0f172a;font-weight:700;text-align:right;border-bottom:1px solid #e2e8f0;">{{ $order->po_number }}</td>
        </tr>
        <tr>
          <td style="padding:14px 18px;font-size:12px;color:#64748b;border-bottom:1px solid #e2e8f0;">Order Date</td>
          <td style="padding:14px 18px;font-size:13px;color:#0f172a;font-weight:700;text-align:right;border-bottom:1px solid #e2e8f0;">{{ optional($order->po_date)->format('d M Y') ?? '—' }}</td>
        </tr>
        @if($order->expected_delivery_date)
        <tr>
          <td style="padding:14px 18px;font-size:12px;color:#64748b;border-bottom:1px solid #e2e8f0;">Requested Delivery Date</td>
          <td style="padding:14px 18px;font-size:13px;color:#0f172a;font-weight:700;text-align:right;border-bottom:1px solid #e2e8f0;">{{ $order->expected_delivery_date->format('d M Y') }}</td>
        </tr>
        @endif
        <tr>
          <td style="padding:14px 18px;font-size:12px;color:#64748b;">Total Amount</td>
          <td style="padding:14px 18px;font-size:15px;color:#15803d;font-weight:700;text-align:right;">BD {{ number_format((float) $order->total_amount, 3) }}</td>
        </tr>
      </table>

      <p style="font-size:14px;color:#475569;line-height:1.75;margin:0 0 18px;">
        Please treat the attached document as the official reference for item descriptions, quantities, pricing,
        and delivery terms. Kindly deliver the item(s) at your earliest convenience, and in line with the delivery
        timeframe agreed upon.
      </p>

      <p style="font-size:14px;color:#475569;line-height:1.75;margin:0 0 28px;">
        Should you have any questions regarding this order, please do not hesitate to contact us — we would be
        happy to assist.
      </p>

      <p style="font-size:14px;color:#1e293b;margin:0;">Thank you for your continued partnership.</p>
      <p style="font-size:14px;color:#1e293b;margin:6px 0 0;">Best regards,</p>
      <p style="font-size:14px;color:#0f172a;font-weight:700;margin:2px 0 0;">{{ $order->createdBy->name ?? config('app.name') }}</p>

      <div style="margin-top:30px;padding-top:20px;border-top:1px solid #e2e8f0;">
        <p style="font-size:11px;color:#94a3b8;margin:0;display:flex;align-items:center;gap:6px;">
          📎 <span>Attached: <strong>{{ $order->po_number }}.pdf</strong></span>
        </p>
      </div>
    </div>
  </div>

  <p style="max-width:580px;margin:16px auto 32px;text-align:center;font-size:11px;color:#94a3b8;">
    This is an automated message from {{ config('app.name') }}. Please do not reply directly to this email.
  </p>
</body>
</html>
