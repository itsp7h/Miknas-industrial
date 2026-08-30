<?php

namespace App\Notifications;

use App\Models\RfqInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class QuoteReceived extends Notification
{
    use Queueable;

    public function __construct(public RfqInvitation $invitation) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $pr = $this->invitation->purchaseRequest;

        return [
            'type' => 'quote_received',
            'message' => $this->invitation->supplier->name.' submitted a quote for '.$pr->request_number,
            'supplier_name' => $this->invitation->supplier->name,
            'request_number' => $pr->request_number,
            'purchase_request_id' => $pr->id,
            'url' => route('purchase.pipeline.show', $pr),
        ];
    }
}
