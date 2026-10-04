<?php

namespace Tests\Feature\Api\Purchase;

use App\Mail\LpoIssuedMail;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\SupplierQuote;
use App\Models\SupplierQuoteItem;
use App\Models\User;
use App\Notifications\Purchase\PurchaseOrderConfirmedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * An approved LPO has to reach the supplier — and only an approved one.
 *
 * Issuing signs an LPO under Prepared By; approving signs it under Approved By
 * and is what sends it (LpoApprovalService). Until then nothing goes out.
 *
 * Everything needed to email one was written — LpoIssuedMail, the Blade body,
 * the WhatsApp notification — and then wired to nothing: the only caller was a
 * private method on the Blade controller that no route and no other method ever
 * invoked. So the pipeline created purchase orders with status 'sent' and the
 * supplier was never told. These pin the send to the approval, and pin that a
 * failed send is reported rather than logged and forgotten.
 */
class LpoDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private PurchaseRequest $pr;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pr = PurchaseRequest::factory()->create(['stage' => 'lpo']);
        $this->supplier = Supplier::factory()->create([
            'name' => 'Gulf Steel',
            'email' => 'sales@gulfsteel.test',
            'whatsapp_number' => null,
        ]);

        $item = PurchaseRequestItem::create([
            'purchase_request_id' => $this->pr->id, 'description' => 'Steel plate',
            'quantity_required' => 2, 'unit' => 'PCS',
        ]);

        $quote = SupplierQuote::factory()->create([
            'purchase_request_id' => $this->pr->id, 'supplier_id' => $this->supplier->id,
        ]);

        SupplierQuoteItem::factory()->create([
            'supplier_quote_id' => $quote->id,
            'purchase_request_item_id' => $item->id,
            'unit_price' => 10, 'total_price' => 20,
            'is_awarded' => true,
        ]);
    }

    private function officer(): User
    {
        // LPOs are issued under the issuer's saved signature.
        $user = User::factory()->withSignature()->create();
        $user->givePermissionTo([
            'pipeline.view', 'pipeline.manage-rfq', 'pipeline.manage-quotes',
            'pipeline.award', 'pipeline.generate-lpo', 'pipeline.view-active-pipeline',
            'purchase-orders.view', 'purchase-orders.create',
            'purchase-orders.edit', 'purchase-orders.delete',
            'goods-receipts.view', 'goods-receipts.create',
            'goods-receipts.edit', 'goods-receipts.delete',
        ]);

        return $user;
    }

    private function issue()
    {
        return $this->actingAs($this->officer())
            ->postJson("/api/v1/purchase/pipeline/{$this->pr->id}/lpo");
    }

    private function approver(): User
    {
        $user = User::factory()->withSignature()->create();
        $user->givePermissionTo(['pipeline.view', 'pipeline.approve-lpo', 'purchase-orders.view']);

        return $user;
    }

    /** Issues the LPO, then approves it as someone else — which is what sends it. */
    private function issueAndApprove()
    {
        $this->issue()->assertOk();

        return $this->approve(PurchaseOrder::first());
    }

    private function approve(PurchaseOrder $order, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->approver())
            ->postJson("/api/v1/purchase/orders/{$order->id}/approve");
    }

    public function test_approving_an_lpo_emails_it_to_the_supplier(): void
    {
        $this->workingMailAccount();

        $this->issueAndApprove()->assertOk();

        Mail::assertSent(LpoIssuedMail::class, fn ($mail) => $mail->hasTo('sales@gulfsteel.test'));
    }

    /** The PDF the buyer prints is the one the supplier is sent. */
    public function test_the_email_carries_the_lpo_as_a_pdf_attachment(): void
    {
        $this->workingMailAccount();

        $this->issueAndApprove()->assertOk();

        Mail::assertSent(LpoIssuedMail::class, function ($mail) {
            $order = PurchaseOrder::first();

            return $mail->order->is($order)
                && str_starts_with($mail->pdf, '%PDF')
                && collect($mail->attachments())->contains(fn ($a) => $a->as === $order->po_number.'.pdf');
        });
    }

    /**
     * The summary box in the email quoted the order's total_amount, which is
     * the lines before VAT, while the attached PDF adds VAT — so the supplier
     * was told two different prices for one order.
     */
    public function test_the_emails_total_includes_vat_as_the_pdf_does(): void
    {
        $this->workingMailAccount();
        Setting::set('vat_rate', 10);

        $this->issueAndApprove()->assertOk();

        Mail::assertSent(LpoIssuedMail::class, function ($mail) {
            $html = $mail->render();

            return $mail->total === 22.0
                && str_contains($html, 'BD 22.000')
                && str_contains($html, 'incl. 10% VAT')
                && ! str_contains($html, 'BD 20.000');
        });
    }

    public function test_without_vat_the_emails_total_is_the_lines_and_claims_no_vat(): void
    {
        $this->workingMailAccount();
        Setting::set('vat_rate', 0);

        $this->issueAndApprove()->assertOk();

        Mail::assertSent(LpoIssuedMail::class, function ($mail) {
            $html = $mail->render();

            return str_contains($html, 'BD 20.000') && ! str_contains($html, 'VAT)');
        });
    }

    public function test_the_whatsapp_heads_up_quotes_the_total_with_vat(): void
    {
        $this->workingMailAccount();
        Setting::set('vat_rate', 10);
        $this->issueAndApprove()->assertOk();

        $text = (new PurchaseOrderConfirmedNotification(PurchaseOrder::first()))
            ->toUltraMessage($this->supplier);

        $this->assertStringContainsString('Total Amount: BD 22.000', json_encode($text->payload, JSON_UNESCAPED_UNICODE));
    }

    public function test_it_records_when_and_where_the_lpo_was_sent(): void
    {
        $this->workingMailAccount();

        $this->issueAndApprove()->assertOk();

        $order = PurchaseOrder::first();
        $this->assertNotNull($order->sent_at);
        $this->assertSame('sales@gulfsteel.test', $order->sent_to);
    }

    public function test_the_message_says_the_supplier_was_emailed(): void
    {
        $this->workingMailAccount();

        $this->assertStringContainsString('approved and emailed to sales@gulfsteel.test', $this->issueAndApprove()->json('message'));
    }

    /**
     * The lesson the RFQ invitations had to learn, applied here before it could
     * bite: `status` reads 'sent' from the moment an order is generated, so it
     * is no evidence at all. Only sent_at is.
     */
    public function test_a_failed_send_is_reported_and_leaves_no_sent_record(): void
    {
        // No mail account configured at all — the commonest way this fails on a
        // fresh installation.
        Mail::fake();

        $response = $this->issueAndApprove()->assertOk();

        $this->assertStringContainsString('approved, but not sent. Could not email Gulf Steel', $response->json('message'));
        Mail::assertNothingSent();

        $order = PurchaseOrder::first();
        $this->assertNull($order->sent_at);
        // The LPO itself is still validly approved, and the request still moves on.
        $this->assertNotNull($order->approved_at);
        $this->assertSame('sent', $order->status);
        $this->assertSame('receiving', $this->pr->fresh()->stage);
    }

    public function test_a_supplier_with_no_email_address_is_named_in_the_message(): void
    {
        $this->workingMailAccount();
        $this->supplier->update(['email' => null]);

        $response = $this->issueAndApprove()->assertOk();

        $this->assertStringContainsString('Gulf Steel has no email address', $response->json('message'));
        Mail::assertNothingSent();
        $this->assertNull(PurchaseOrder::first()->sent_at);
    }

    public function test_an_lpo_can_be_emailed_again_from_the_order(): void
    {
        Mail::fake();
        $this->issueAndApprove()->assertOk();
        $order = PurchaseOrder::first();
        $this->assertNull($order->sent_at);

        // Whatever was wrong is fixed; the buyer retries from the order itself.
        $this->workingMailAccount();

        $response = $this->actingAs($this->officer())
            ->postJson("/api/v1/purchase/orders/{$order->id}/send")
            ->assertOk();

        Mail::assertSent(LpoIssuedMail::class);
        $this->assertNotNull($order->fresh()->sent_at);
        $this->assertStringContainsString('sales@gulfsteel.test', $response->json('message'));
    }

    public function test_a_resend_that_fails_says_so_rather_than_claiming_success(): void
    {
        $this->workingMailAccount();
        $this->issueAndApprove()->assertOk();

        $order = PurchaseOrder::first();
        $sentAt = $order->fresh()->sent_at;

        $this->supplier->update(['email' => null]);

        $this->actingAs($this->officer())
            ->postJson("/api/v1/purchase/orders/{$order->id}/send")
            ->assertStatus(422);

        // The earlier successful send is not overwritten by a failed retry.
        $this->assertEquals($sentAt, $order->fresh()->sent_at);
    }

    public function test_issuing_signs_under_prepared_by_and_sends_nothing(): void
    {
        $this->workingMailAccount();

        $response = $this->issue()->assertOk();

        Mail::assertNothingSent();
        $order = PurchaseOrder::first();
        $this->assertNotNull($order->prepared_signature);
        $this->assertNull($order->approved_at);
        $this->assertSame('draft', $order->status);
        $this->assertTrue($order->awaitingApproval());
        // The request waits at the LPO stage for the approval.
        $this->assertSame('lpo', $this->pr->fresh()->stage);
        $this->assertStringContainsString('once it is approved', $response->json('message'));
        $this->assertTrue($response->json('data.purchase_orders.0.awaiting_approval'));
    }

    public function test_approving_records_the_approver_and_their_signature(): void
    {
        $this->workingMailAccount();
        $this->issue()->assertOk();
        $approver = $this->approver();

        $response = $this->approve(PurchaseOrder::first(), $approver)->assertOk();

        $order = PurchaseOrder::first();
        $this->assertSame($approver->id, $order->approved_by);
        $this->assertNotNull($order->approved_at);
        $this->assertSame($approver->signature_image, $order->approved_signature);
        $this->assertSame('sent', $order->status);
        $this->assertSame('receiving', $this->pr->fresh()->stage);
        $this->assertFalse($response->json('data.awaiting_approval'));
        $this->assertSame($approver->name, $response->json('data.approved_by_name'));
    }

    /** The printed LPO carries the approver under Approved By. */
    public function test_the_lpo_document_names_the_approver(): void
    {
        $this->workingMailAccount();
        $this->issue()->assertOk();
        $approver = $this->approver();
        $this->approve(PurchaseOrder::first(), $approver)->assertOk();

        $html = $this->actingAs($this->officer())
            ->get('/purchase/orders/'.PurchaseOrder::first()->id.'/print')
            ->assertOk()->getContent();

        $this->assertStringContainsString(e($approver->name), $html);
        $this->assertStringContainsString($approver->signature_image, $html);
    }

    /** Two signatures from one hand are one signature. */
    public function test_the_preparer_cannot_approve_their_own_lpo(): void
    {
        $officer = $this->officer();
        $officer->givePermissionTo('pipeline.approve-lpo');

        $this->actingAs($officer)->postJson("/api/v1/purchase/pipeline/{$this->pr->id}/lpo")->assertOk();

        $this->approve(PurchaseOrder::first(), $officer)
            ->assertForbidden()
            ->assertJsonPath('message', 'You prepared this LPO, so someone else must approve it');
        $this->assertNull(PurchaseOrder::first()->approved_at);
    }

    public function test_approving_requires_the_approve_lpo_permission(): void
    {
        $this->issue()->assertOk();
        $order = PurchaseOrder::first();

        $this->actingAs($this->officer())
            ->postJson("/api/v1/purchase/orders/{$order->id}/approve")->assertForbidden();
        $this->assertNull($order->fresh()->approved_at);
    }

    /** No saved signature: asked for, with the code the SPA answers by asking. */
    public function test_an_approver_without_a_signature_is_asked_for_one(): void
    {
        $this->issue()->assertOk();
        $approver = User::factory()->create();
        $approver->givePermissionTo('pipeline.approve-lpo');

        $this->approve(PurchaseOrder::first(), $approver)
            ->assertStatus(422)
            ->assertJsonPath('code', 'signature_required');
        $this->assertNull(PurchaseOrder::first()->approved_at);
    }

    public function test_an_lpo_is_approved_once(): void
    {
        $this->workingMailAccount();
        $this->issueAndApprove()->assertOk();

        $this->approve(PurchaseOrder::first())->assertStatus(422);
    }

    public function test_an_unapproved_lpo_cannot_be_sent(): void
    {
        $this->workingMailAccount();
        $this->issue()->assertOk();

        $this->actingAs($this->officer())
            ->postJson('/api/v1/purchase/orders/'.PurchaseOrder::first()->id.'/send')
            ->assertStatus(422);

        Mail::assertNothingSent();
    }

    /** One LPO per supplier; the request moves on once every one is approved. */
    public function test_the_request_moves_to_receiving_only_once_every_lpo_is_approved(): void
    {
        $this->workingMailAccount();
        $other = Supplier::factory()->create(['email' => 'sales@other.test', 'whatsapp_number' => null]);
        $item = PurchaseRequestItem::create([
            'purchase_request_id' => $this->pr->id, 'description' => 'Angle bar',
            'quantity_required' => 1, 'unit' => 'PCS',
        ]);
        $quote = SupplierQuote::factory()->create(['purchase_request_id' => $this->pr->id, 'supplier_id' => $other->id]);
        SupplierQuoteItem::factory()->create([
            'supplier_quote_id' => $quote->id, 'purchase_request_item_id' => $item->id,
            'unit_price' => 5, 'total_price' => 5, 'is_awarded' => true,
        ]);

        $this->issue()->assertOk();
        [$first, $second] = PurchaseOrder::orderBy('id')->get()->all();

        $this->approve($first)->assertOk();
        $this->assertSame('lpo', $this->pr->fresh()->stage);

        $this->approve($second)->assertOk();
        $this->assertSame('receiving', $this->pr->fresh()->stage);
    }

    public function test_resending_requires_the_lpo_permission(): void
    {
        // Built directly rather than issued: actingAs() persists for the rest
        // of the test, so issuing first would leave no guest to check.
        $order = PurchaseOrder::create([
            'po_number' => 'PO-00001', 'supplier_id' => $this->supplier->id,
            'purchase_request_id' => $this->pr->id, 'po_date' => now(),
            'total_amount' => 20, 'status' => 'sent',
        ]);

        $this->postJson("/api/v1/purchase/orders/{$order->id}/send")->assertUnauthorized();

        $this->actingAs(User::factory()->create())
            ->postJson("/api/v1/purchase/orders/{$order->id}/send")->assertForbidden();
    }
}
