<?php

namespace App\Events;

use App\Http\Resources\ItemCategoryResource;
use App\Models\ItemCategory;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ItemCategorySaved implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public ItemCategory $category) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('inventory')];
    }

    public function broadcastAs(): string
    {
        return 'item-category.saved';
    }

    public function broadcastWith(): array
    {
        return (new ItemCategoryResource($this->category->loadCount('items')))->resolve();
    }
}
