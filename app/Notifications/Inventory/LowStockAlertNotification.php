<?php

namespace App\Notifications\Inventory;

use App\Models\Item;
use App\Models\StockLevel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use PromoSeven\UltraMessage\UltraMessageChannel;
use PromoSeven\UltraMessage\UltraMessageMessage;

class LowStockAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private Item $item, private StockLevel $stockLevel) {}

    public function via(mixed $notifiable): array
    {
        return [UltraMessageChannel::class];
    }

    public function toUltraMessage(mixed $notifiable): UltraMessageMessage
    {
        return UltraMessageMessage::text(
            "⚠️ *Low Stock Alert*\n\nItem: *{$this->item->item_name}* ({$this->item->item_code})\nCurrent Stock: {$this->stockLevel->quantity} {$this->item->unit_of_measure}\nReorder Level: {$this->item->minimum_stock_level} {$this->item->unit_of_measure}\n\nPlease raise a purchase request."
        );
    }
}
