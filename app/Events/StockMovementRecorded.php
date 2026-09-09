<?php

namespace App\Events;

use App\Http\Resources\StockMovementResource;
use App\Models\StockMovement;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StockMovementRecorded implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public StockMovement $movement) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('inventory')];
    }

    public function broadcastAs(): string
    {
        return 'stock-movement.recorded';
    }

    public function broadcastWith(): array
    {
        return (new StockMovementResource($this->movement->loadMissing(['item', 'warehouse'])))->resolve();
    }
}
