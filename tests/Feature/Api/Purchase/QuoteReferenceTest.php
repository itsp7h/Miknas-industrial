<?php

namespace Tests\Feature\Api\Purchase;

use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Supplier;
use App\Models\SupplierQuote;
use App\Models\SupplierQuoteItem;
use App\Models\User;
use App\Services\LpoDeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The supplier's quotation number ("Ref:"): given on the quote portal, copied
 * onto the LPO when it is issued, and printed under the vendor's name.
 */
class QuoteReferenceTest extends TestCase
{
    use RefreshDatabase;

    private PurchaseRequest $pr;

    private SupplierQuote $quote;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pr = PurchaseRequest::factory()->create(['stage' => 'lpo']);
        $supplier = Supplier::factory()->create(['name' => 'Gulf Steel Co.', 'email' => null, 'whatsapp_number' => null]);
        $item = PurchaseRequestItem::create([
            'purchase_request_id' => $this->pr->id, 'description' => 'Steel plate',
            'quantity_required' => 2, 'unit' => 'PCS',
        ]);
        $this->quote = SupplierQuote::factory()->create([
            'purchase_request_id' => $this->pr->id, 'supplier_id' => $supplier->id,
            'reference' => 'GS/Q/2026/118',
        ]);
        SupplierQuoteItem::factory()->create([
            'supplier_quote_id' => $this->quote->id, 'purchase_request_item_id' => $item->id,
            'unit_price' => 10, 'total_price' => 20, 'is_awarded' => true,
        ]);
    }

    private function issue(): PurchaseOrder
    {
        $issuer = User::factory()->withSignature()->create();
        $issuer->givePermissionTo([
            'pipeline.view', 'pipeline.generate-lpo', 'pipeline.view-active-pipeline',
            'purchase-orders.view', 'purchase-orders.create',
        ]);

        $this->actingAs($issuer)->postJson("/api/v1/purchase/pipeline/{$this->pr->id}/lpo")->assertOk();

        return PurchaseOrder::firstOrFail();
    }

    private function document(PurchaseOrder $order): string
    {
        return view('purchase.orders.pdf', app(LpoDeliveryService::class)->documentData($order->fresh()))->render();
    }

    public function test_issuing_the_lpo_copies_the_quotes_ref_onto_it(): void
    {
        $this->assertSame('GS/Q/2026/118', $this->issue()->quote_reference);
    }

    public function test_the_lpo_prints_the_ref_under_the_vendors_name(): void
    {
        $html = $this->document($this->issue());

        $this->assertStringContainsString('<strong>Ref:</strong> GS/Q/2026/118', $html);
        // Under the name, before the rest of the vendor block and Ship To.
        $name = strpos($html, 'Gulf Steel Co.');
        $ref = strpos($html, 'GS/Q/2026/118');
        $this->assertGreaterThan($name, $ref);
        $this->assertLessThan(strpos($html, 'Ship To'), $ref);
    }

    /** Like its signature, an LPO that has gone out keeps what it went out with. */
    public function test_a_later_change_to_the_quote_does_not_reach_an_issued_lpo(): void
    {
        $order = $this->issue();
        $this->quote->update(['reference' => 'CHANGED']);

        $this->assertStringContainsString('GS/Q/2026/118', $this->document($order));
        $this->assertStringNotContainsString('CHANGED', $this->document($order));
    }

    /** Quotes from before the field existed have none; their LPOs print no Ref line. */
    public function test_an_lpo_without_a_ref_prints_no_ref_line(): void
    {
        $this->quote->update(['reference' => null]);

        $order = $this->issue();

        $this->assertNull($order->quote_reference);
        $this->assertStringNotContainsString('Ref:', $this->document($order));
    }
}
