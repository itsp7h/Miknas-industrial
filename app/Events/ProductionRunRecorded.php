<?php

namespace App\Events;

use App\Http\Resources\ProductionRunResource;
use App\Models\ProductionRun;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProductionRunRecorded implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public ProductionRun $run) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('inventory')];
    }

    public function broadcastAs(): string
    {
        return 'production-run.recorded';
    }

    public function broadcastWith(): array
    {
        return (new ProductionRunResource($this->run->loadMissing(['item', 'warehouse', 'creator'])))->resolve();
    }
}
