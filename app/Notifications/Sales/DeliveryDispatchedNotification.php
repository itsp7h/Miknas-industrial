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
        return UltraMessageMessage::text(
            "Dear {$notifiable->name},\n\nYour delivery *#{$this->delivery->delivery_number}* has been dispatched and is on its way.\n\nOrder Reference: {$this->delivery->salesOrder?->order_number ?? 'N/A'}\nDispatch Date: {$this->delivery->delivery_date->format('d M Y')}\n\nThank you for your business."
        );
    }
}
