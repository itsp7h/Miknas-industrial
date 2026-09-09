<?php

namespace App\Events;

use App\Http\Resources\WarehouseResource;
use App\Models\Warehouse;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WarehouseSaved implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Warehouse $warehouse) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('inventory')];
    }

    public function broadcastAs(): string
    {
        return 'warehouse.saved';
    }

    public function broadcastWith(): array
    {
        return (new WarehouseResource($this->warehouse))->resolve();
    }
}
