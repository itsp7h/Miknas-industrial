<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class SalesInvoiceDeleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public int $salesInvoiceId) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('sales')];
    }

    public function broadcastAs(): string
    {
        return 'sales-invoice.deleted';
    }

    public function broadcastWith(): array
    {
        return ['id' => $this->salesInvoiceId];
    }
}
