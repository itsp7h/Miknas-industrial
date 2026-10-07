<?php

namespace Tests\Feature\Api\Purchase;

use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\RfqInvitation;
use App\Models\Setting;
use App\Models\SupplierQuoteItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 10 PCS asked for. A supplier may offer another quantity in our unit — 8
 * because that is all they have, 12 because it comes by the dozen — and
 * the quote, the comparison and the LPO follow what they offered.
 */
class QuoteQuantityTest extends TestCase
{
    use RefreshDatabase;

    private PurchaseRequest $pr;

    private PurchaseRequestItem $item;

    private RfqInvitation $invitation;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set('vat_rate', 0);
        $this->pr = PurchaseRequest::factory()->create(['stage' => 'quoting']);
        $this->item = PurchaseRequestItem::create([
            'purchase_request_id' => $this->pr->id, 'description' => 'Rebar 25mm',
            'quantity_required' => 10, 'unit' => 'PCS',
        ]);
        $this->invitation = RfqInvitation::factory()->create([
            'purchase_request_id' => $this->pr->id, 'status' => 'sent', 'expires_at' => now()->addDays(7),
        ]);
    }

    private function submit(array $line = [])
    {
        $code = $this->getJson("/api/v1/rfq/{$this->invitation->token}")->json('confirm_code');

        return $this->postJson("/api/v1/rfq/{$this->invitation->token}", [
            'terms' => true, 'confirm_code' => $code, 'reference' => 'Q-1',
            'items' => [array_merge([
                'id' => $this->item->id, 'unit_price' => 3, 'is_vatable' => false, 'not_available' => false,
            ], $line)],
        ]);
    }

    public function test_left_out_it_is_what_we_asked_for(): void
    {
        $this->submit()->assertCreated();

        $line = SupplierQuoteItem::firstOrFail();
        $this->assertEquals(10, $line->quantity);
        $this->assertEquals(30, $line->total_price);
    }

    public function test_a_supplier_may_offer_fewer(): void
    {
        $this->submit(['quantity' => 8])->assertCreated();

        $line = SupplierQuoteItem::firstOrFail();
        $this->assertEquals(8, $line->quantity);
        $this->assertEquals(3, $line->unit_price);
        $this->assertEquals(24, $line->total_price);
        $this->assertEquals(24, $line->quote->total_amount);
    }

    public function test_a_supplier_may_offer_more(): void
    {
        $this->submit(['quantity' => 12])->assertCreated();

        $this->assertEquals(36, SupplierQuoteItem::firstOrFail()->total_price);
    }

    public function test_a_quantity_must_be_more_than_zero(): void
    {
        $this->submit(['quantity' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.quantity']);
    }

    /** In their own unit their count is in supplier_quantity; ours stays as asked. */
    public function test_it_is_ignored_on_a_line_in_their_unit(): void
    {
        $this->submit(['quantity' => 8, 'supplier_unit' => 'BOX', 'supplier_quantity' => 1, 'unit_price' => 10])->assertCreated();

        $line = SupplierQuoteItem::firstOrFail();
        $this->assertEquals(10, $line->quantity);
        $this->assertEquals(10, $line->total_price);
    }

    public function test_the_comparison_shows_it_and_the_lpo_orders_it(): void
    {
        $this->submit(['quantity' => 8])->assertCreated();
        $officer = User::factory()->withSignature()->create();
        $officer->givePermissionTo([
            'pipeline.manage-quotes', 'pipeline.award', 'pipeline.generate-lpo',
            'pipeline.view', 'pipeline.view-active-pipeline', 'purchase-orders.view', 'purchase-orders.create',
        ]);

        $this->actingAs($officer)
            ->getJson("/api/v1/purchase/requests/{$this->pr->id}/quotes")
            ->assertJsonPath('data.items.0.quantity', '10.00')
            ->assertJsonPath('data.items.0.rows.0.line.quantity', 8);

        SupplierQuoteItem::firstOrFail()->update(['is_awarded' => true]);
        $this->pr->update(['stage' => 'lpo']);
        $this->actingAs($officer)->postJson("/api/v1/purchase/pipeline/{$this->pr->id}/lpo")->assertOk();

        $order = PurchaseOrder::with('items')->firstOrFail();
        $this->assertEquals(8, $order->items->first()->quantity);
        $this->assertEquals(24, $order->items->first()->total_amount);
    }
}
