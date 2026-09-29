<?php

namespace Tests\Feature\Api\Purchase;

use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Supplier;
use App\Models\SupplierQuote;
use App\Models\SupplierQuoteItem;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * An LPO is signed by whoever issues it: their signature, drawn or uploaded
 * once on their profile, is frozen onto each LPO at issue and printed in the
 * Prepared By block. No signature, no LPO.
 */
class LpoSignatureTest extends TestCase
{
    use RefreshDatabase;

    private PurchaseRequest $pr;

    /** A different real image, for "the signature changed later". */
    private const OTHER = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAIAAAD91JpzAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAC0lEQVQImWNgQAYAAA4AAbGa6gYAAAAASUVORK5CYII=';

    protected function setUp(): void
    {
        parent::setUp();

        $this->pr = PurchaseRequest::factory()->create(['stage' => 'lpo']);
        $supplier = Supplier::factory()->create(['email' => null, 'whatsapp_number' => null]);
        $item = PurchaseRequestItem::create([
            'purchase_request_id' => $this->pr->id, 'description' => 'Steel plate',
            'quantity_required' => 2, 'unit' => 'PCS',
        ]);
        $quote = SupplierQuote::factory()->create([
            'purchase_request_id' => $this->pr->id, 'supplier_id' => $supplier->id,
        ]);
        SupplierQuoteItem::factory()->create([
            'supplier_quote_id' => $quote->id, 'purchase_request_item_id' => $item->id,
            'unit_price' => 10, 'total_price' => 20, 'is_awarded' => true,
        ]);
    }

    private function item(): Item
    {
        return Item::create([
            'item_code' => 'RM-1', 'item_name' => 'Steel Plate', 'category' => 'raw_material',
            'unit_of_measure' => 'KG', 'cost_price' => 10, 'is_active' => true,
        ]);
    }

    private function issuer(bool $signed = true): User
    {
        $factory = User::factory();
        $user = ($signed ? $factory->withSignature() : $factory)->create(['name' => 'Nelson Issuer']);
        $user->givePermissionTo([
            'pipeline.view', 'pipeline.generate-lpo', 'pipeline.view-active-pipeline',
            'purchase-orders.view', 'purchase-orders.create',
        ]);

        return $user;
    }

    public function test_issuing_without_a_saved_signature_is_refused_with_a_code_the_spa_acts_on(): void
    {
        $this->actingAs($this->issuer(signed: false))
            ->postJson("/api/v1/purchase/pipeline/{$this->pr->id}/lpo")
            ->assertStatus(422)
            ->assertJsonPath('code', 'signature_required');

        $this->assertSame(0, PurchaseOrder::count());
        $this->assertSame('lpo', $this->pr->fresh()->stage);
    }

    public function test_a_hand_made_order_needs_the_signature_too(): void
    {
        $supplier = Supplier::factory()->create();
        $item = $this->item();

        $this->actingAs($this->issuer(signed: false))
            ->postJson('/api/v1/purchase/orders', [
                'supplier_id' => $supplier->id, 'po_date' => '2026-09-27',
                'items' => [['item_id' => $item->id, 'quantity' => 1, 'rate' => 5]],
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'signature_required');

        $this->assertSame(0, PurchaseOrder::count());
    }

    public function test_the_lpo_keeps_the_signature_it_was_issued_with(): void
    {
        $issuer = $this->issuer();

        $this->actingAs($issuer)->postJson("/api/v1/purchase/pipeline/{$this->pr->id}/lpo")->assertOk();

        $order = PurchaseOrder::firstOrFail();
        $this->assertSame(UserFactory::SIGNATURE, $order->prepared_signature);

        // Replacing the profile signature later must not rewrite a document
        // already sent to a supplier.
        $this->actingAs($issuer)->putJson('/api/v1/profile/signature', ['signature_image' => self::OTHER])->assertOk();
        $this->assertSame(UserFactory::SIGNATURE, $order->fresh()->prepared_signature);
    }

    public function test_a_hand_made_order_is_stamped_with_the_issuers_signature(): void
    {
        $supplier = Supplier::factory()->create(['whatsapp_number' => null]);
        $item = $this->item();

        $this->actingAs($this->issuer())
            ->postJson('/api/v1/purchase/orders', [
                'supplier_id' => $supplier->id, 'po_date' => '2026-09-27',
                'items' => [['item_id' => $item->id, 'quantity' => 1, 'rate' => 5]],
            ])
            ->assertCreated()
            ->assertJsonPath('data.prepared_signed', true);

        $this->assertSame(UserFactory::SIGNATURE, PurchaseOrder::firstOrFail()->prepared_signature);
    }

    /**
     * The order's page in the app frames the print view with ?embed=1: the
     * same document, without its own Print button or grey backdrop.
     */
    public function test_the_embedded_lpo_is_the_print_document_without_its_chrome(): void
    {
        $issuer = $this->issuer();
        $this->actingAs($issuer)->postJson("/api/v1/purchase/pipeline/{$this->pr->id}/lpo")->assertOk();
        $order = PurchaseOrder::firstOrFail();

        $embedded = $this->actingAs($issuer)->get("/purchase/orders/{$order->id}/print?embed=1")->assertOk()->getContent();
        $printed = $this->actingAs($issuer)->get("/purchase/orders/{$order->id}/print")->assertOk()->getContent();

        $this->assertStringContainsString('<body class="embed">', $embedded);
        $this->assertStringNotContainsString('<body class="embed">', $printed);
        $sheet = fn ($html) => preg_replace('#^.*<div class="sheet">(.*)</div>\s*</body>.*$#s', '$1', $html);
        $this->assertSame($sheet($printed), $sheet($embedded));
        $this->assertStringContainsString('Nelson Issuer', $sheet($embedded));
    }

    public function test_the_printed_and_pdf_lpo_carry_the_signature_over_prepared_by(): void
    {
        $issuer = $this->issuer();
        $this->actingAs($issuer)->postJson("/api/v1/purchase/pipeline/{$this->pr->id}/lpo")->assertOk();
        $order = PurchaseOrder::firstOrFail();

        $this->actingAs($issuer)->get("/purchase/orders/{$order->id}/print")
            ->assertOk()
            ->assertSee('<img class="sig-img" src="'.UserFactory::SIGNATURE.'"', false)
            ->assertSee('Nelson Issuer');

        $pdf = $this->actingAs($issuer)->get("/purchase/orders/{$order->id}/pdf")->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        // DomPDF embeds the image as an XObject; an unsigned LPO has none.
        $this->assertStringContainsString('/Subtype /Image', $pdf->getContent());
    }

    public function test_an_lpo_issued_before_signatures_prints_without_one(): void
    {
        $issuer = $this->issuer();
        $order = PurchaseOrder::create([
            'po_number' => 'LPO-OLD-1', 'supplier_id' => Supplier::factory()->create()->id,
            'po_date' => '2026-09-01', 'total_amount' => 5, 'status' => 'sent', 'created_by' => $issuer->id,
        ]);

        $this->actingAs($issuer)->get("/purchase/orders/{$order->id}/print")
            ->assertOk()
            ->assertDontSee('class="sig-img"', false);
    }

    /** The image is tens of kilobytes; only the documents and the profile carry it. */
    public function test_the_signature_stays_out_of_ordinary_payloads(): void
    {
        $issuer = $this->issuer();
        $this->actingAs($issuer)->postJson("/api/v1/purchase/pipeline/{$this->pr->id}/lpo")->assertOk();

        $this->assertArrayNotHasKey('signature_image', $issuer->fresh()->toArray());
        $this->assertArrayNotHasKey('prepared_signature', PurchaseOrder::firstOrFail()->toArray());
        $this->actingAs($issuer)->getJson('/api/v1/purchase/orders')
            ->assertOk()
            ->assertDontSee('base64', false);
    }
}
