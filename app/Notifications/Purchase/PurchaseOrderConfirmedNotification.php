<?php

namespace App\Notifications\Purchase;

use App\Models\PurchaseOrder;
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
        return UltraMessageMessage::text(
            "Dear {$notifiable->name},\n\nPurchase Order *#{$this->order->po_number}* has been placed.\n\nTotal Amount: {$this->order->total_amount}\nExpected Delivery: {$this->order->expected_delivery_date}\n\nThank you."
        );
    }
}
