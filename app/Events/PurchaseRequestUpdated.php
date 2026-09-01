<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Editing a request rewrites the very fields the pipeline board shows in its
 * columns — project, department, requester, date — so the boards other people
 * are looking at have to hear about it. The payload is deliberately the same
 * shape PurchaseRequestCreated broadcasts, so a board can upsert either one
 * through the same handler.
 */
class PurchaseRequestUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public int $purchaseRequestId,
        public string $requestNumber,
        public ?string $date,
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
        return 'purchase-request.updated';
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
