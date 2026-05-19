<?php

namespace App\Notifications\Sales;

use App\Models\SalesOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use PromoSeven\UltraMessage\UltraMessageChannel;
use PromoSeven\UltraMessage\UltraMessageMessage;

class SalesOrderConfirmedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private SalesOrder $order) {}

    public function via(mixed $notifiable): array
    {
        return [UltraMessageChannel::class];
    }

    public function toUltraMessage(mixed $notifiable): UltraMessageMessage
    {
        return UltraMessageMessage::text(
            "Dear {$notifiable->name},\n\nYour order *#{$this->order->order_number}* has been confirmed.\n\nTotal Amount: {$this->order->total_amount}\n\nWe will keep you updated on the delivery status. Thank you for your business."
        );
    }
}
