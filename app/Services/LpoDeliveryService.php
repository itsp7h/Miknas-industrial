<?php

namespace App\Services;

use App\Mail\LpoIssuedMail;
use App\Models\MailAccount;
use App\Models\PurchaseOrder;
use App\Models\Setting;
use App\Notifications\Purchase\PurchaseOrderConfirmedNotification;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

/**
 * Gets an issued LPO to the supplier it was issued to.
 *
 * This existed as a private method on the Blade PurchaseOrderController and was
 * never called from anywhere — so every LPO the pipeline generated was created
 * with status 'sent' and then sat there, while the supplier was told nothing.
 * The mail class, the Blade email and the WhatsApp notification were all
 * written and all dead.
 *
 * It follows the rule the RFQ invitations had to learn: the record of a send is
 * written only once the mailer has returned, and a failure is raised to the
 * caller rather than logged and swallowed. A screen that reports success for an
 * email that never left is worse than one that reports the failure.
 */
class LpoDeliveryService
{
    /**
     * @throws RuntimeException when the LPO could not be emailed
     */
    public function deliver(PurchaseOrder $order): void
    {
        $order->loadMissing('supplier');
        $supplier = $order->supplier;

        if (! $supplier) {
            throw new RuntimeException("{$order->po_number} has no supplier to send to.");
        }

        if (! $supplier->email) {
            throw new RuntimeException("{$supplier->name} has no email address.");
        }

        $account = MailAccount::where('enabled', true)->first();

        try {
            if (! $account) {
                throw new RuntimeException('No enabled mail account is configured.');
            }

            $pdf = Pdf::loadView('purchase.orders.pdf', $this->documentData($order))
                ->setPaper('a4', 'portrait')
                ->output();

            Mail::mailer($account->name)
                ->to($supplier->email)
                ->send(new LpoIssuedMail($order, $pdf));
        } catch (\Throwable $e) {
            Log::error('LPO email failed to send', [
                'purchase_order_id' => $order->id,
                'po_number' => $order->po_number,
                'supplier_id' => $supplier->id,
                'supplier_email' => $supplier->email,
                'mail_account' => $account?->name,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException("Could not email {$supplier->name}: {$e->getMessage()}", 0, $e);
        }

        $order->update(['sent_at' => now(), 'sent_to' => $supplier->email]);

        // A WhatsApp heads-up on top of the email, where there is a number for
        // it. Queued, and deliberately after the record is written: the email
        // carries the document, and a missing WhatsApp must not make a
        // delivered LPO look undelivered.
        if ($supplier->whatsapp_number) {
            try {
                $supplier->notify(new PurchaseOrderConfirmedNotification($order));
            } catch (\Throwable $e) {
                Log::warning('LPO WhatsApp notification failed', [
                    'purchase_order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Delivers each order, returning the failures as
     * ['PO-00001' => 'why it failed'] so a caller can report exactly which
     * suppliers were left uninformed.
     */
    public function deliverAll(iterable $orders): array
    {
        $failed = [];

        foreach ($orders as $order) {
            try {
                $this->deliver($order);
            } catch (RuntimeException $e) {
                $failed[$order->po_number] = $e->getMessage();
            }
        }

        return $failed;
    }

    /**
     * Everything the printed LPO needs. Shared with the print and PDF routes so
     * the document a supplier is emailed is the one the buyer sees on screen —
     * they were two copies of this block before.
     */
    public function documentData(PurchaseOrder $order): array
    {
        $order->load(['supplier', 'items.item', 'createdBy', 'purchaseRequest']);

        $company = $order->purchaseRequest?->resolveCompany();

        $subtotal = (float) $order->items->sum('total_amount');
        $vatRate = (float) Setting::get('vat_rate', 0);
        $vatAmount = $vatRate > 0 ? round($subtotal * $vatRate / 100, 3) : 0;
        $discount = 0;
        $total = $subtotal + $vatAmount - $discount;

        return compact('order', 'company', 'subtotal', 'vatRate', 'vatAmount', 'discount', 'total');
    }
}
