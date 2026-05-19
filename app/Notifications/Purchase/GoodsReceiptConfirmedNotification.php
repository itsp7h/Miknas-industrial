<?php

namespace App\Notifications\Purchase;

use App\Models\GoodsReceiptNote;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use PromoSeven\UltraMessage\UltraMessageChannel;
use PromoSeven\UltraMessage\UltraMessageMessage;

class GoodsReceiptConfirmedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private GoodsReceiptNote $grn) {}

    public function via(mixed $notifiable): array
    {
        return [UltraMessageChannel::class];
    }

    public function toUltraMessage(mixed $notifiable): UltraMessageMessage
    {
        return UltraMessageMessage::text(
            "GRN *#{$this->grn->grn_number}* has been confirmed and goods received.\n\nPO Reference: {$this->grn->purchaseOrder->po_number}\nDate: {$this->grn->received_date->format('d M Y')}"
        );
    }
}
