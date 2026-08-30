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
        // `??` is not valid inside "{...}" string interpolation — it is a parse
        // error, not a runtime one, so the whole class fails to load. Resolve
        // the fallbacks before building the message.
        $poNumber = $this->grn->purchaseOrder?->po_number ?? 'N/A';
        $receivedDate = $this->grn->received_date->format('d M Y');

        return UltraMessageMessage::text(
            "GRN *#{$this->grn->grn_number}* has been confirmed and goods received.\n\nPO Reference: {$poNumber}\nDate: {$receivedDate}"
        );
    }
}
