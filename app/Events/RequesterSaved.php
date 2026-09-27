<?php

namespace App\Events;

use App\Http\Resources\RequesterResource;
use App\Models\Settings\Requester;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** On `purchase`, since what it changes is who the MPR form offers. */
class RequesterSaved implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Requester $requester) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('purchase')];
    }

    public function broadcastAs(): string
    {
        return 'requester.saved';
    }

    public function broadcastWith(): array
    {
        return (new RequesterResource($this->requester->load('companies')))->resolve();
    }
}
