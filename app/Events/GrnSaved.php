<?php

namespace App\Events;

use App\Http\Resources\GrnResource;
use App\Models\GoodsReceiptNote;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GrnSaved implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public GoodsReceiptNote $grn) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('purchase')];
    }

    public function broadcastAs(): string
    {
        return 'grn.saved';
    }

    public function broadcastWith(): array
    {
        return (new GrnResource(
            $this->grn->loadMissing(['purchaseOrder.supplier', 'warehouse', 'items.item'])
        ))->resolve();
    }
}
