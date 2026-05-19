<?php

namespace App\Notifications\Production;

use App\Models\ProductionOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use PromoSeven\UltraMessage\UltraMessageChannel;
use PromoSeven\UltraMessage\UltraMessageMessage;

class ProductionOrderCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private ProductionOrder $order) {}

    public function via(mixed $notifiable): array
    {
        return [UltraMessageChannel::class];
    }

    public function toUltraMessage(mixed $notifiable): UltraMessageMessage
    {
        return UltraMessageMessage::text(
            "✅ *Production Complete*\n\nProduction Order *#{$this->order->order_number}* has been completed.\n\nProduct: {$this->order->product->item_name}\nQuantity: {$this->order->quantity_to_produce}\nCompleted: " . now()->format('d M Y')
        );
    }
}
