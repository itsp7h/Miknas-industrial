<?php

namespace App\Services;

use App\Mail\RfqInvitationMail;
use App\Models\MailAccount;
use App\Models\PurchaseRequest;
use App\Models\RfqInvitation;
use App\Models\Supplier;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class RfqInvitationService
{
    public function select(PurchaseRequest $purchaseRequest, Supplier $supplier, string $channel, array $itemIds = []): RfqInvitation
    {
        return RfqInvitation::create([
            'purchase_request_id' => $purchaseRequest->id,
            'supplier_id' => $supplier->id,
            'token' => bin2hex(random_bytes(32)),
            'channel' => $channel,
            'expires_at' => now()->addDays(14),
            'status' => 'pending',
            'item_ids' => empty($itemIds) ? null : $itemIds,
        ]);
    }

    public function sendInvitation(RfqInvitation $invitation): void
    {
        $invitation->update(['status' => 'sent', 'sent_at' => now()]);

        $supplier = $invitation->supplier;
        $hasWhatsapp = (bool) preg_replace('/\D/', '', $supplier->phone ?? '');

        // The "whatsapp" channel is a manual wa.me link, not an automatic send —
        // if the supplier has no phone number to receive it, fall back to email
        // rather than silently sending nothing.
        $wantsEmail = in_array($invitation->channel, ['email', 'both'])
            || ($invitation->channel === 'whatsapp' && ! $hasWhatsapp);

        if ($wantsEmail && $supplier->email) {
            $account = MailAccount::where('enabled', true)->first();

            try {
                if (! $account) {
                    throw new \RuntimeException('No enabled mail account is configured.');
                }

                Mail::mailer($account->name)->to($supplier->email)->send(new RfqInvitationMail($invitation));
            } catch (\Throwable $e) {
                Log::error('RFQ invitation email failed to send', [
                    'invitation_id' => $invitation->id,
                    'supplier_id' => $supplier->id,
                    'supplier_email' => $supplier->email,
                    'mail_account' => $account?->name,
                    'error' => $e->getMessage(),
                ]);
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
        $url = route('rfq.show', $invitation->token);
        $text = "Hello {$invitation->supplier->name},\n\n"
               ."You are invited to submit a quote for purchase request {$invitation->purchaseRequest->request_number}.\n\n"
               ."Please click the link below to submit your quote:\n{$url}\n\n"
               .'This link expires in 7 days and can only be used once.';
        $phone = preg_replace('/\D/', '', $invitation->supplier->phone ?? '');

        return 'https://wa.me/'.$phone.'?text='.rawurlencode($text);
    }
}
