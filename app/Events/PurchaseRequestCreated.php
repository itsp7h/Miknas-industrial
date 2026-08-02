<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class PurchaseRequestCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public int $purchaseRequestId,
        public string $requestNumber,
        public string $date,
        public ?string $projectName,
        public ?string $requestedByName,
        public ?string $department,
        public string $stage,
        public ?int $requestedById = null,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('purchase')];
    }

    public function broadcastAs(): string
    {
        return 'purchase-request.created';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->purchaseRequestId,
            'request_number' => $this->requestNumber,
            'date' => $this->date,
            'project_name' => $this->projectName,
            'requested_by_name' => $this->requestedByName,
            'department' => $this->department,
            'stage' => $this->stage,
            'requested_by_id' => $this->requestedById,
        ];
    }
}
