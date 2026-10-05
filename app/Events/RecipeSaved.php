<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** A finished good's recipe changed, so an open production form re-reads it. */
class RecipeSaved implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public int $productId, public array $recipe) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('inventory')];
    }

    public function broadcastAs(): string
    {
        return 'recipe.saved';
    }

    public function broadcastWith(): array
    {
        return ['product_id' => $this->productId, 'recipe' => $this->recipe];
    }
}
