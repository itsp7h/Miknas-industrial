<?php

namespace App\Events;

use App\Http\Resources\SupplierPaymentResource;
use App\Models\SupplierPayment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupplierPaymentRecorded implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public SupplierPayment $payment) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('purchase')];
    }

    public function broadcastAs(): string
    {
        return 'supplier-payment.recorded';
    }

    public function broadcastWith(): array
    {
        return (new SupplierPaymentResource(
            $this->payment->loadMissing(['supplierInvoice.supplier'])
        ))->resolve();
    }
}
