<?php

namespace Tests\Feature\Mail;

use App\Mail\RfqInvitationMail;
use App\Models\PurchaseRequest;
use App\Models\RfqInvitation;
use App\Models\Supplier;
use App\Services\RfqInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The invitation lasts RfqInvitation::EXPIRY_DAYS, but the email body and the
 * WhatsApp text used to state "7 days" as prose while the footer rendered
 * expires_at — so a 14-day invitation told the supplier it had a week, and the
 * same email contradicted itself two paragraphs later. Both now read the figure
 * off the record; these pin that they agree.
 */
class RfqInvitationMailTest extends TestCase
{
    use RefreshDatabase;

    private function invitation(int $days): RfqInvitation
    {
        return RfqInvitation::factory()->create([
            'purchase_request_id' => PurchaseRequest::factory()->create(['stage' => 'quoting'])->id,
            'supplier_id' => Supplier::factory()->create(['email' => 'supplier@example.test'])->id,
            'status' => 'sent',
            'created_at' => now(),
            'expires_at' => now()->addDays($days),
        ]);
    }

    public function test_the_email_states_the_same_lifetime_its_footer_renders(): void
    {
        $invitation = $this->invitation(RfqInvitation::EXPIRY_DAYS);

        $html = (new RfqInvitationMail($invitation))->render();

        $this->assertStringContainsString(
            'expires in '.RfqInvitation::EXPIRY_DAYS.' days',
            $html
        );
        $this->assertStringContainsString(
            'expires on <strong>'.$invitation->expires_at->format('d M Y').'</strong>',
            $html
        );
    }

    /** Change the expiry and the prose follows, rather than being left behind. */
    public function test_the_wording_tracks_a_different_expiry(): void
    {
        $html = (new RfqInvitationMail($this->invitation(3)))->render();

        $this->assertStringContainsString('expires in 3 days', $html);
        $this->assertStringNotContainsString('expires in 7 days', $html);
    }

    public function test_the_whatsapp_text_quotes_the_same_figure(): void
    {
        $invitation = $this->invitation(RfqInvitation::EXPIRY_DAYS);
        $invitation->supplier->update(['phone' => '+973 3600 0000']);

        $link = urldecode(app(RfqInvitationService::class)->whatsappLink($invitation));

        $this->assertStringContainsString(
            'This link expires in '.RfqInvitation::EXPIRY_DAYS.' days',
            $link
        );
    }

    /** The service's own expiry is the constant the messages quote. */
    public function test_a_new_invitation_expires_after_the_constant(): void
    {
        $purchaseRequest = PurchaseRequest::factory()->create(['stage' => 'rfq']);
        $supplier = Supplier::factory()->create();

        $invitation = app(RfqInvitationService::class)
            ->select($purchaseRequest, $supplier, 'email');

        $this->assertSame(RfqInvitation::EXPIRY_DAYS, $invitation->expiresInDays());
    }
}
