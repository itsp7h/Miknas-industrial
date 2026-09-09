<?php

namespace App\Events;

use App\Http\Resources\DeliveryNoteResource;
use App\Models\DeliveryNote;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeliveryNoteSaved implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public DeliveryNote $deliveryNote) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('sales')];
    }

    public function broadcastAs(): string
    {
        return 'delivery-note.saved';
    }

    public function broadcastWith(): array
    {
        return (new DeliveryNoteResource(
            $this->deliveryNote->loadMissing(['salesOrder', 'customer', 'warehouse', 'items.item'])
        ))->resolve();
    }
}
