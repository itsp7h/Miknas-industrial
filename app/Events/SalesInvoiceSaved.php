<?php

namespace App\Events;

use App\Http\Resources\SalesInvoiceResource;
use App\Models\SalesInvoice;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SalesInvoiceSaved implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public SalesInvoice $invoice) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('sales')];
    }

    public function broadcastAs(): string
    {
        return 'sales-invoice.saved';
    }

    public function broadcastWith(): array
    {
        return (new SalesInvoiceResource($this->invoice->loadMissing(['salesOrder', 'customer'])))->resolve();
    }
}
