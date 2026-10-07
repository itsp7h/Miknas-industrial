<?php

namespace Tests\Feature\Api\Purchase;

use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\RfqInvitation;
use App\Models\Setting;
use App\Models\SupplierQuoteItem;
use App\Models\User;
use App\Services\LpoDeliveryService;
use App\Support\SupplierUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A supplier quoting in their own unit: 100 PCS asked for, 4 BAG offered at
 * 12.000 a bag, 1 BAG = 25 PCS. The quote, the comparison, the LPO and the
 * GRN all have to agree on what that is in our unit.
 */
class SupplierQuoteUnitTest extends TestCase
{
    use RefreshDatabase;

    private PurchaseRequest $pr;

    private PurchaseRequestItem $item;

    private RfqInvitation $invitation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pr = PurchaseRequest::factory()->create(['stage' => 'quoting']);
        $this->item = PurchaseRequestItem::create([
            'purchase_request_id' => $this->pr->id, 'description' => 'Cement',
            'quantity_required' => 100, 'unit' => 'PCS',
        ]);
        $this->invitation = RfqInvitation::factory()->create([
            'purchase_request_id' => $this->pr->id, 'status' => 'sent', 'expires_at' => now()->addDays(7),
        ]);
    }

    /** The supplier's path: GET for the code, then POST the quote. */
    private function submit(array $line = [])
    {
        $code = $this->getJson("/api/v1/rfq/{$this->invitation->token}")->json('confirm_code');

        return $this->postJson("/api/v1/rfq/{$this->invitation->token}", [
            'terms' => true,
            'confirm_code' => $code,
            'reference' => 'Q-1',
            'items' => [array_merge([
                'id' => $this->item->id, 'unit_price' => 12, 'is_vatable' => false, 'not_available' => false,
                'supplier_unit' => 'BAG', 'unit_factor' => 25, 'supplier_quantity' => 4,
            ], $line)],
        ]);
    }

    private function officer(): User
    {
        $user = User::factory()->withSignature()->create();
        $user->givePermissionTo([
            'pipeline.manage-rfq', 'pipeline.manage-quotes', 'pipeline.award', 'pipeline.generate-lpo',
            'pipeline.view', 'pipeline.view-active-pipeline', 'purchase-orders.view', 'purchase-orders.create',
        ]);

        return $user;
    }

    public function test_the_portal_offers_our_units(): void
    {
        $this->getJson("/api/v1/rfq/{$this->invitation->token}")
            ->assertOk()
            ->assertJsonFragment(['units' => SupplierUnit::units()]);
    }

    public function test_a_line_in_the_suppliers_unit_is_kept_in_both(): void
    {
        $this->submit()->assertCreated();

        $line = SupplierQuoteItem::firstOrFail();
        $this->assertSame('PCS', $line->unit);
        $this->assertSame('BAG', $line->supplier_unit);
        $this->assertEquals(25, $line->unit_factor);
        $this->assertEquals(4, $line->supplier_quantity);
        $this->assertEquals(12, $line->supplier_unit_price);
        // In ours: 4 × 25 = 100 PCS at 12 / 25 = 0.480.
        $this->assertEquals(100, $line->quantity);
        $this->assertEquals(0.48, $line->unit_price);
        $this->assertEquals(48, $line->total_price);
        $this->assertEquals(48, $line->quote->total_amount);
    }

    public function test_quoting_in_our_own_unit_stores_no_conversion(): void
    {
        $this->submit(['supplier_unit' => 'PCS', 'unit_factor' => null, 'supplier_quantity' => null])->assertCreated();

        $line = SupplierQuoteItem::firstOrFail();
        $this->assertNull($line->supplier_unit);
        $this->assertEquals(100, $line->quantity);
        $this->assertEquals(1200, $line->total_price);
    }

    /**
     * The supplier no longer says what their unit holds in ours: the GRN
     * does. The line keeps their figures, and ours is their total over what
     * we asked for, so it still ranks against other suppliers.
     */
    public function test_another_unit_is_accepted_without_its_conversion(): void
    {
        $this->submit(['unit_factor' => null])->assertCreated();

        $line = SupplierQuoteItem::firstOrFail();
        $this->assertSame('BAG', $line->supplier_unit);
        $this->assertNull($line->unit_factor);
        $this->assertTrue($line->conversionPending());
        $this->assertEquals(4, $line->supplier_quantity);
        $this->assertEquals(12, $line->supplier_unit_price);
        // 4 BAG × 12 = 48, over the 100 PCS asked for.
        $this->assertEquals(100, $line->quantity);
        $this->assertEquals(48, $line->total_price);
        $this->assertEquals(0.48, $line->unit_price);
    }

    public function test_another_unit_still_needs_how_many(): void
    {
        $this->submit(['unit_factor' => null, 'supplier_quantity' => null])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Please say how many BAG you are quoting for Cement.');

        $this->assertDatabaseCount('supplier_quote_items', 0);
    }

    public function test_an_lpo_with_no_conversion_states_none(): void
    {
        $this->submit(['unit_factor' => null])->assertCreated();
        SupplierQuoteItem::firstOrFail()->update(['is_awarded' => true]);
        $this->pr->update(['stage' => 'lpo']);

        $this->actingAs($this->officer())->postJson("/api/v1/purchase/pipeline/{$this->pr->id}/lpo")->assertOk();

        $order = PurchaseOrder::with('items')->firstOrFail();
        $line = $order->items->first();
        $this->assertSame('BAG', $line->supplier_unit);
        $this->assertNull($line->unit_factor);
        $this->assertTrue($line->conversionPending());

        $html = view('purchase.orders.pdf', app(LpoDeliveryService::class)->documentData($order->fresh()))->render();
        $this->assertStringNotContainsString("Supplier's unit:", $html);
        $this->assertStringContainsString('ours: PCS', $html);
        $this->assertStringContainsString('12.000', $html);
    }

    public function test_a_unit_we_do_not_keep_is_refused(): void
    {
        $this->submit(['supplier_unit' => 'DRUM'])
            ->assertUnprocessable()
            ->assertJsonPath('message', '"DRUM" is not a unit we can accept for Cement.');
    }

    public function test_the_workspace_shows_both_and_compares_per_our_unit(): void
    {
        $this->submit()->assertCreated();

        $this->actingAs($this->officer())
            ->getJson("/api/v1/purchase/requests/{$this->pr->id}/quotes")
            ->assertOk()
            ->assertJsonPath('data.items.0.rows.0.line.supplier_unit', 'BAG')
            ->assertJsonPath('data.items.0.rows.0.line.unit_factor', 25)
            ->assertJsonPath('data.items.0.rows.0.line.supplier_quantity', 4)
            ->assertJsonPath('data.items.0.rows.0.line.supplier_unit_price', 12)
            ->assertJsonPath('data.items.0.rows.0.line.unit_price', 0.48)
            ->assertJsonPath('data.items.0.rows.0.line.quantity', 100);
    }

    public function test_staff_can_correct_the_conversion_before_awarding(): void
    {
        $this->submit()->assertCreated();
        $line = SupplierQuoteItem::firstOrFail();

        $this->actingAs($this->officer())
            ->putJson("/api/v1/purchase/requests/{$this->pr->id}/quotes/items/{$line->id}/unit", [
                'unit_factor' => 20, 'supplier_quantity' => 5,
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Cement: 1 BAG = 20 PCS.');

        $line->refresh();
        // Their price per bag stands; ours follows from it.
        $this->assertEquals(12, $line->supplier_unit_price);
        $this->assertEquals(100, $line->quantity);
        $this->assertEquals(0.6, $line->unit_price);
        $this->assertEquals(60, $line->total_price);
        $this->assertEquals(60, $line->quote->total_amount);
    }

    public function test_an_awarded_line_cannot_have_its_conversion_changed(): void
    {
        $this->submit()->assertCreated();
        $line = SupplierQuoteItem::firstOrFail();
        $line->update(['is_awarded' => true]);

        $this->actingAs($this->officer())
            ->putJson("/api/v1/purchase/requests/{$this->pr->id}/quotes/items/{$line->id}/unit", [
                'unit_factor' => 20, 'supplier_quantity' => 5,
            ])
            ->assertUnprocessable();
    }

    public function test_correcting_needs_award_permission(): void
    {
        $this->submit()->assertCreated();
        $line = SupplierQuoteItem::firstOrFail();
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(['pipeline.manage-quotes', 'pipeline.view-active-pipeline']);

        $this->actingAs($viewer)
            ->putJson("/api/v1/purchase/requests/{$this->pr->id}/quotes/items/{$line->id}/unit", [
                'unit_factor' => 20, 'supplier_quantity' => 5,
            ])
            ->assertForbidden();
    }

    public function test_the_lpo_orders_in_their_unit_and_shows_ours(): void
    {
        $this->submit()->assertCreated();
        SupplierQuoteItem::firstOrFail()->update(['is_awarded' => true]);
        $this->pr->update(['stage' => 'lpo']);

        $this->actingAs($this->officer())->postJson("/api/v1/purchase/pipeline/{$this->pr->id}/lpo")->assertOk();

        $order = PurchaseOrder::with('items')->firstOrFail();
        $line = $order->items->first();
        // Ours, for the GRN and the stock.
        $this->assertEquals(100, $line->quantity);
        $this->assertEquals(48, $line->total_amount);
        // Theirs, for the document.
        $this->assertSame('BAG', $line->supplier_unit);
        $this->assertSame('PCS', $line->system_unit);
        $this->assertEquals(4, $line->supplier_quantity);
        $this->assertEquals(12, $line->supplier_rate);

        $html = view('purchase.orders.pdf', app(LpoDeliveryService::class)->documentData($order->fresh()))->render();
        $this->assertStringContainsString("Supplier's unit: 1 BAG = 25 PCS", $html);
        $this->assertStringContainsString('4 BAG', $html);
        $this->assertStringContainsString('= 100 PCS in our system', $html);
        $this->assertStringContainsString('ours: PCS', $html);
        $this->assertStringContainsString('12.000', $html);
    }

    public function test_a_line_in_our_unit_prints_as_before(): void
    {
        Setting::set('vat_rate', 0);
        $this->submit(['supplier_unit' => null, 'unit_factor' => null, 'supplier_quantity' => null])->assertCreated();
        SupplierQuoteItem::firstOrFail()->update(['is_awarded' => true]);
        $this->pr->update(['stage' => 'lpo']);

        $this->actingAs($this->officer())->postJson("/api/v1/purchase/pipeline/{$this->pr->id}/lpo")->assertOk();

        $order = PurchaseOrder::firstOrFail();
        $html = view('purchase.orders.pdf', app(LpoDeliveryService::class)->documentData($order->fresh()))->render();
        $this->assertStringNotContainsString("Supplier's unit", $html);
        $this->assertNull($order->items->first()->supplier_unit);
    }
}
