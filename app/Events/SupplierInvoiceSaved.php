<?php

namespace App\Events;

use App\Http\Resources\SupplierInvoiceResource;
use App\Models\SupplierInvoice;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupplierInvoiceSaved implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public SupplierInvoice $invoice) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('purchase')];
    }

    public function broadcastAs(): string
    {
        return 'supplier-invoice.saved';
    }

    public function broadcastWith(): array
    {
        return (new SupplierInvoiceResource(
            $this->invoice->loadMissing(['supplier', 'purchaseOrder', 'goodsReceiptNote'])
        ))->resolve();
    }
}
