<?php

namespace App\Notifications\Sales;

use App\Models\SalesInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use PromoSeven\UltraMessage\UltraMessageChannel;
use PromoSeven\UltraMessage\UltraMessageMessage;

class InvoiceCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private SalesInvoice $invoice) {}

    public function via(mixed $notifiable): array
    {
        return [UltraMessageChannel::class];
    }

    public function toUltraMessage(mixed $notifiable): UltraMessageMessage
    {
        return UltraMessageMessage::text(
            "Dear {$notifiable->name},\n\nInvoice *#{$this->invoice->invoice_number}* is ready.\n\nAmount Due: {$this->invoice->total_amount}\nDue Date: {$this->invoice->due_date}\n\nPlease arrange payment at your earliest convenience. Thank you."
        );
    }
}
