<?php

namespace App\Notifications\Sales;

use App\Models\DeliveryNote;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use PromoSeven\UltraMessage\UltraMessageChannel;
use PromoSeven\UltraMessage\UltraMessageMessage;

class DeliveryDispatchedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private DeliveryNote $delivery) {}

    public function via(mixed $notifiable): array
    {
        return [UltraMessageChannel::class];
    }

    public function toUltraMessage(mixed $notifiable): UltraMessageMessage
    {
        // `??` is not valid inside "{...}" string interpolation — it is a parse
        // error, not a runtime one, so the whole class fails to load. Resolve
        // the fallbacks before building the message.
        $orderReference = $this->delivery->salesOrder?->order_number ?? 'N/A';
        $dispatchDate = $this->delivery->delivery_date->format('d M Y');

        return UltraMessageMessage::text(
            "Dear {$notifiable->name},\n\nYour delivery *#{$this->delivery->delivery_number}* has been dispatched and is on its way.\n\nOrder Reference: {$orderReference}\nDispatch Date: {$dispatchDate}\n\nThank you for your business."
        );
    }
}
