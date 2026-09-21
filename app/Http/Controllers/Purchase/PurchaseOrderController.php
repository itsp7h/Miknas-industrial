<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Services\LpoDeliveryService;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * What is left of the LPO's server-rendered half: the two documents DomPDF and
 * a printer render, which React cannot.
 *
 * The emailing that used to sit here as a private method nothing called now
 * lives in LpoDeliveryService, where the pipeline actually invokes it.
 */
class PurchaseOrderController extends Controller
{
    public function __construct(private LpoDeliveryService $lpo) {}

    public function print(PurchaseOrder $order)
    {
        return view('purchase.orders.print', $this->lpo->documentData($order));
    }

    public function pdf(PurchaseOrder $order)
    {
        $pdf = Pdf::loadView('purchase.orders.pdf', $this->lpo->documentData($order))
            ->setPaper('a4', 'portrait');

        return $pdf->download(($order->po_number ?? 'PO-'.str_pad($order->id, 5, '0', STR_PAD_LEFT)).'.pdf');
    }
}
