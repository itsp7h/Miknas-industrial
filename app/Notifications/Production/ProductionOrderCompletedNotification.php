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
        // `??` is not valid inside "{...}" string interpolation — it is a parse
        // error, not a runtime one, so the whole class fails to load. Resolve
        // the fallbacks before building the message.
        $product = $this->order->product?->item_name ?? 'N/A';
        $completedOn = $this->order->completion_date?->format('d M Y') ?? now()->format('d M Y');

        return UltraMessageMessage::text(
            "✅ *Production Complete*\n\nProduction Order *#{$this->order->order_number}* has been completed.\n\nProduct: {$product}\nQuantity: {$this->order->quantity_to_produce}\nCompleted: {$completedOn}"
        );
    }
}
