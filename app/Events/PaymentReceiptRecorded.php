<?php

namespace App\Events;

use App\Http\Resources\PaymentReceiptResource;
use App\Models\PaymentReceipt;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentReceiptRecorded implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public PaymentReceipt $receipt) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('sales')];
    }

    public function broadcastAs(): string
    {
        return 'payment-receipt.recorded';
    }

    public function broadcastWith(): array
    {
        return (new PaymentReceiptResource($this->receipt->loadMissing(['salesInvoice', 'customer'])))->resolve();
    }
}
