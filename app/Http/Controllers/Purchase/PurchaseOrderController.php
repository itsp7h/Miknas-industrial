<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Mail\LpoIssuedMail;
use App\Models\MailAccount;
use App\Models\PurchaseOrder;
use App\Models\Setting;
use App\Models\Settings\ProjectSetting;
use App\Models\Supplier;
use App\Notifications\Purchase\PurchaseOrderConfirmedNotification;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class PurchaseOrderController extends Controller
{
    /**
     * Auto-generate the LPO(s) for a request straight from its awarded quote items —
     * one LPO per winning supplier, since items on the same request can be split
     * across different suppliers.
     */
    public function print(PurchaseOrder $order)
    {
        $data = $this->lpoDocumentData($order);

        return view('purchase.orders.print', $data);
    }

    public function pdf(PurchaseOrder $order)
    {
        $data = $this->lpoDocumentData($order);

        $pdf = Pdf::loadView('purchase.orders.pdf', $data)
            ->setPaper('a4', 'portrait');

        return $pdf->download(($order->po_number ?? 'PO-'.str_pad($order->id, 5, '0', STR_PAD_LEFT)).'.pdf');
    }

    /**
     * Notify the winning supplier that their LPO was issued — email with the
     * signed PDF attached, plus a WhatsApp heads-up if a number is on file.
     * Failures here must not block LPO issuance, but must be logged so a
     * failed send is discoverable instead of silently disappearing.
     */
    private function issueLpoToSupplier(PurchaseOrder $order): void
    {
        $order->loadMissing('supplier');

        if ($order->supplier && $order->supplier->email) {
            $account = MailAccount::where('enabled', true)->first();

            try {
                if (! $account) {
                    throw new RuntimeException('No enabled mail account is configured.');
                }

                $pdf = Pdf::loadView('purchase.orders.pdf', $this->lpoDocumentData($order))
                    ->setPaper('a4', 'portrait')
                    ->output();

                Mail::mailer($account->name)
                    ->to($order->supplier->email)
                    ->send(new LpoIssuedMail($order, $pdf));
            } catch (\Throwable $e) {
                Log::error('LPO email failed to send', [
                    'purchase_order_id' => $order->id,
                    'po_number' => $order->po_number,
                    'supplier_id' => $order->supplier->id,
                    'supplier_email' => $order->supplier->email,
                    'mail_account' => $account?->name,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($order->supplier && $order->supplier->whatsapp_number) {
            $order->supplier->notify(new PurchaseOrderConfirmedNotification($order));
        }
    }

    private function lpoDocumentData(PurchaseOrder $order): array
    {
        $order->load(['supplier', 'items.item', 'createdBy', 'purchaseRequest']);

        $company = $order->purchaseRequest
            ? ProjectSetting::where('name', $order->purchaseRequest->project_name)->with('company')->first()?->company
            : null;

        $subtotal = (float) $order->items->sum('total_amount');
        $vatRate = (float) Setting::get('vat_rate', 0);
        $vatAmount = $vatRate > 0 ? round($subtotal * $vatRate / 100, 3) : 0;
        $discount = 0;
        $total = $subtotal + $vatAmount - $discount;

        return compact('order', 'company', 'subtotal', 'vatRate', 'vatAmount', 'discount', 'total');
    }
}
