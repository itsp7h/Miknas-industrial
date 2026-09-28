<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>LPO {{ $order->po_number ?? 'PO-' . str_pad($order->id, 5, '0', STR_PAD_LEFT) }}</title>
<style>
    /* The font DomPDF draws the downloaded copy in, served so the browser
       draws this one in it too — without it a browser falls back to Arial. */
    @font-face { font-family: 'DejaVu Sans'; font-weight: 400; src: url('{{ asset('fonts/DejaVuSans.ttf') }}') format('truetype'); }
    @font-face { font-family: 'DejaVu Sans'; font-weight: 700; src: url('{{ asset('fonts/DejaVuSans-Bold.ttf') }}') format('truetype'); }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
        font-family: 'DejaVu Sans', 'Arial', sans-serif;
        font-size: 11px;
        color: #1e293b;
        /* Set, not left to each engine's own "normal", which differ. */
        line-height: 1.3;
        background: #f1f5f9;
    }

    /* An A4 sheet with the PDF's own margins, so the page shows what the
       download holds. */
    .sheet {
        width: 21cm;
        min-height: 29.7cm;
        margin: 24px auto;
        background: #fff;
        box-shadow: 0 4px 24px rgba(0,0,0,.08);
        padding: 2cm 1.6cm;
    }

    .print-bar {
        width: 21cm;
        margin: 16px auto 0;
        text-align: right;
    }
    .print-btn {
        padding: 10px 20px;
        background: #16a34a;
        color: #fff;
        border: none;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
    }

    /* A phone narrower than the sheet scrolls it sideways rather than
       reflowing it — reflowed, it would no longer be the document. */
    @media screen and (max-width: 21cm) {
        body { overflow-x: auto; }
        .sheet, .print-bar { margin-left: 16px; margin-right: 16px; }
    }

    @page { size: A4; margin: 0; }
    @media print {
        body { background: #fff; }
        .sheet { box-shadow: none; margin: 0; min-height: 0; }
        .print-bar { display: none; }
    }
</style>
</head>
<body>

<div class="print-bar">
    <button class="print-btn" onclick="window.print()">🖨 Print</button>
</div>

<div class="sheet">
    @include('purchase.orders.partials.document')
</div>

</body>
</html>
