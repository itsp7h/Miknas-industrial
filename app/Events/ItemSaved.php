<?php

namespace App\Events;

use App\Http\Resources\ItemResource;
use App\Models\Item;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ItemSaved implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Item $item) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('inventory')];
    }

    public function broadcastAs(): string
    {
        return 'item.saved';
    }

    public function broadcastWith(): array
    {
        return (new ItemResource($this->item))->resolve();
    }
}
