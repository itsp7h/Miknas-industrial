<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $order->po_number ?? 'PO-' . str_pad($order->id, 5, '0', STR_PAD_LEFT) }}</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
        font-family: 'DejaVu Sans', 'Arial', sans-serif;
        font-size: 11px;
        color: #1e293b;
        /* The browser copy's 1.3. DomPDF lays a line out 1.28 times taller
           than a browser does for the same value (13px comes out 16.64px)
           while sizing the text itself correctly, so the same spacing needs
           1.3 / 1.28 here. */
        line-height: 1.016;
        padding: 2cm 1.6cm;
    }
</style>
</head>
<body>

@include('purchase.orders.partials.document')

</body>
</html>
