<?php

namespace Tests\Feature\Api\Purchase;

use App\Mail\LpoIssuedMail;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Supplier;
use App\Models\SupplierQuote;
use App\Models\SupplierQuoteItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Issuing an LPO has to reach the supplier.
 *
 * Everything needed to email one was written — LpoIssuedMail, the Blade body,
 * the WhatsApp notification — and then wired to nothing: the only caller was a
 * private method on the Blade controller that no route and no other method ever
 * invoked. So the pipeline created purchase orders with status 'sent' and the
 * supplier was never told. These pin the send to the issuance, and pin that a
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
        $user = User::factory()->create();
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

    public function test_issuing_an_lpo_emails_it_to_the_supplier(): void
    {
        $this->workingMailAccount();

        $this->issue()->assertOk();

        Mail::assertSent(LpoIssuedMail::class, fn ($mail) => $mail->hasTo('sales@gulfsteel.test'));
    }

    /** The PDF the buyer prints is the one the supplier is sent. */
    public function test_the_email_carries_the_lpo_as_a_pdf_attachment(): void
    {
        $this->workingMailAccount();

        $this->issue()->assertOk();

        Mail::assertSent(LpoIssuedMail::class, function ($mail) {
            $order = PurchaseOrder::first();

            return $mail->order->is($order)
                && str_starts_with($mail->pdf, '%PDF')
                && collect($mail->attachments())->contains(fn ($a) => $a->as === $order->po_number.'.pdf');
        });
    }

    public function test_it_records_when_and_where_the_lpo_was_sent(): void
    {
        $this->workingMailAccount();

        $this->issue()->assertOk();

        $order = PurchaseOrder::first();
        $this->assertNotNull($order->sent_at);
        $this->assertSame('sales@gulfsteel.test', $order->sent_to);
    }

    public function test_the_message_says_the_supplier_was_emailed(): void
    {
        $this->workingMailAccount();

        $this->assertStringContainsString('emailed to the supplier', $this->issue()->json('message'));
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

        $response = $this->issue()->assertOk();

        $this->assertStringContainsString('Could not email Gulf Steel', $response->json('message'));
        Mail::assertNothingSent();

        $order = PurchaseOrder::first();
        $this->assertNull($order->sent_at);
        // The LPO itself is still validly issued, and the request still moves on.
        $this->assertSame('sent', $order->status);
        $this->assertSame('receiving', $this->pr->fresh()->stage);
    }

    public function test_a_supplier_with_no_email_address_is_named_in_the_message(): void
    {
        $this->workingMailAccount();
        $this->supplier->update(['email' => null]);

        $response = $this->issue()->assertOk();

        $this->assertStringContainsString('Gulf Steel has no email address', $response->json('message'));
        Mail::assertNothingSent();
        $this->assertNull(PurchaseOrder::first()->sent_at);
    }

    public function test_an_lpo_can_be_emailed_again_from_the_order(): void
    {
        Mail::fake();
        $this->issue()->assertOk();
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
        $this->issue()->assertOk();

        $order = PurchaseOrder::first();
        $sentAt = $order->fresh()->sent_at;

        $this->supplier->update(['email' => null]);

        $this->actingAs($this->officer())
            ->postJson("/api/v1/purchase/orders/{$order->id}/send")
            ->assertStatus(422);

        // The earlier successful send is not overwritten by a failed retry.
        $this->assertEquals($sentAt, $order->fresh()->sent_at);
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
