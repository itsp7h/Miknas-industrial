<?php

namespace App\Notifications\Purchase;

use App\Models\PurchaseOrder;
use App\Services\LpoDeliveryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use PromoSeven\UltraMessage\UltraMessageChannel;
use PromoSeven\UltraMessage\UltraMessageMessage;

class PurchaseOrderConfirmedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private PurchaseOrder $order) {}

    public function via(mixed $notifiable): array
    {
        return [UltraMessageChannel::class];
    }

    public function toUltraMessage(mixed $notifiable): UltraMessageMessage
    {
        // VAT included, as the LPO document prints it: total_amount is the
        // lines before VAT.
        $total = 'BD '.number_format(app(LpoDeliveryService::class)->totals($this->order)['total'], 3);

        return UltraMessageMessage::text(
            "Dear {$notifiable->name},\n\nPurchase Order *#{$this->order->po_number}* has been placed.\n\nTotal Amount: {$total}\nExpected Delivery: {$this->order->expected_delivery_date}\n\nThank you."
        );
    }
}
