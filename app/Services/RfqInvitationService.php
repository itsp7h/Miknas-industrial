<?php

namespace App\Services;

use App\Mail\RfqInvitationMail;
use App\Models\PurchaseRequest;
use App\Models\RfqInvitation;
use App\Models\Supplier;
use Illuminate\Support\Facades\Mail;

class RfqInvitationService
{
    public function select(PurchaseRequest $purchaseRequest, Supplier $supplier, string $channel, array $itemIds = []): RfqInvitation
    {
        return RfqInvitation::create([
            'purchase_request_id' => $purchaseRequest->id,
            'supplier_id'         => $supplier->id,
            'token'               => bin2hex(random_bytes(32)),
            'channel'             => $channel,
            'expires_at'          => now()->addDays(14),
            'status'              => 'pending',
            'item_ids'            => empty($itemIds) ? null : $itemIds,
        ]);
    }

    public function sendInvitation(RfqInvitation $invitation): void
    {
        $invitation->update(['status' => 'sent', 'sent_at' => now()]);

        $supplier = $invitation->supplier;
        if (in_array($invitation->channel, ['email', 'both']) && $supplier->email) {
            try {
                Mail::to($supplier->email)->send(new RfqInvitationMail($invitation));
            } catch (\Exception $e) {
                // Mail failure should not block flow
            }
        }
    }

    public function invite(PurchaseRequest $purchaseRequest, Supplier $supplier, string $channel): RfqInvitation
    {
        $invitation = $this->select($purchaseRequest, $supplier, $channel);
        $this->sendInvitation($invitation);
        return $invitation;
    }

    public function whatsappLink(RfqInvitation $invitation): string
    {
        $url   = route('rfq.show', $invitation->token);
        $text  = "Hello {$invitation->supplier->name},\n\n"
               . "You are invited to submit a quote for purchase request {$invitation->purchaseRequest->request_number}.\n\n"
               . "Please click the link below to submit your quote:\n{$url}\n\n"
               . "This link expires in 7 days and can only be used once.";
        $phone = preg_replace('/\D/', '', $invitation->supplier->phone ?? '');
        return 'https://wa.me/' . $phone . '?text=' . rawurlencode($text);
    }
}
