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
            'expires_at' => now()->addDays(RfqInvitation::EXPIRY_DAYS),
            'status' => 'pending',
            'item_ids' => empty($itemIds) ? null : $itemIds,
        ]);
    }

    /**
     * Delivers the invitation and, only if that worked, records it as sent.
     *
     * The order matters: this used to stamp 'sent' first and swallow whatever
     * went wrong, so a request whose email never left reported success to the
     * screen, kept a sent_at timestamp, and left the failure in the log where
     * nobody would look until the supplier failed to quote.
     *
     * @throws \RuntimeException when nothing could be delivered
     */
    public function sendInvitation(RfqInvitation $invitation): void
    {
        $supplier = $invitation->supplier;
        $hasWhatsapp = (bool) preg_replace('/\D/', '', $supplier->phone ?? '');

        // The "whatsapp" channel is a manual wa.me link, not an automatic send —
        // if the supplier has no phone number to receive it, fall back to email
        // rather than silently sending nothing.
        $wantsEmail = in_array($invitation->channel, ['email', 'both'])
            || ($invitation->channel === 'whatsapp' && ! $hasWhatsapp);

        if ($wantsEmail) {
            // An email channel with no address to send to delivers nothing. That
            // was previously recorded as sent too.
            if (! $supplier->email) {
                throw new \RuntimeException("{$supplier->name} has no email address.");
            }

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

                // The log line stays — it carries the detail — but the caller now
                // hears about it too, so the UI can say so.
                throw new \RuntimeException("Could not email {$supplier->name}: {$e->getMessage()}", 0, $e);
            }
        }

        $invitation->update(['status' => 'sent', 'sent_at' => now()]);
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
               ."This link expires in {$invitation->expiresInDays()} days and can only be used once.";
        $phone = preg_replace('/\D/', '', $invitation->supplier->phone ?? '');

        return 'https://wa.me/'.$phone.'?text='.rawurlencode($text);
    }
}
