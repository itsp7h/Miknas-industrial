<?php

namespace App\Mail;

use App\Models\RfqInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RfqInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public RfqInvitation $invitation) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Quote Request — ' . $this->invitation->purchaseRequest->request_number,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.rfq-invitation');
    }
}
