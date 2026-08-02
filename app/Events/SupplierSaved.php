<?php
// app/Events/SupplierSaved.php

namespace App\Events;

use App\Models\Supplier;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupplierSaved implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Supplier $supplier) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('purchase')];
    }

    public function broadcastAs(): string
    {
        return 'supplier.saved';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->supplier->id,
            'supplier_code' => $this->supplier->supplier_code,
            'name' => $this->supplier->name,
            'category' => $this->supplier->category,
            'is_active' => (bool) $this->supplier->is_active,
        ];
    }
}
