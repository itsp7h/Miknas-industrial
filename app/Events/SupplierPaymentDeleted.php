<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupplierPaymentDeleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public int $paymentId) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('purchase')];
    }

    public function broadcastAs(): string
    {
        return 'supplier-payment.deleted';
    }

    public function broadcastWith(): array
    {
        return ['id' => $this->paymentId];
    }
}
