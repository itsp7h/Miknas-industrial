<?php

namespace App\Mail;

use App\Models\PurchaseOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LpoIssuedMail extends Mailable
{
    use Queueable, SerializesModels;

    /** $total is the LPO's VAT-inclusive figure, the one the attached PDF prints. */
    public function __construct(
        public PurchaseOrder $order,
        public string $pdf,
        public float $total,
        public float $vatRate = 0,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Purchase Order — '.$this->order->po_number,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.lpo-issued');
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdf, $this->order->po_number.'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
