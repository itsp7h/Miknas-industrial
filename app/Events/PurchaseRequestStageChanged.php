<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class PurchaseRequestStageChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public int $purchaseRequestId,
        public string $requestNumber,
        public string $stage,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('purchase')];
    }

    public function broadcastAs(): string
    {
        return 'purchase-request.stage-changed';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->purchaseRequestId,
            'request_number' => $this->requestNumber,
            'stage' => $this->stage,
        ];
    }
}
