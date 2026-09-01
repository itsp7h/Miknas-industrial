<?php

namespace Tests\Feature\Api\Purchase;

use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\SupplierQuote;
use App\Models\SupplierQuoteItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The quotes workspace, ported from the Blade page: one payload carrying every
 * item beside every supplier's offer, the award decision per item, and the
 * grand total that takes whichever supplier wins each item.
 */
class QuoteWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private PurchaseRequest $pr;

    private PurchaseRequestItem $itemA;

    private PurchaseRequestItem $itemB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pr = PurchaseRequest::factory()->create(['stage' => 'comparison']);
        $this->itemA = PurchaseRequestItem::create([
            'purchase_request_id' => $this->pr->id, 'description' => 'Steel plate',
            'quantity_required' => 2, 'unit' => 'PCS',
        ]);
        $this->itemB = PurchaseRequestItem::create([
            'purchase_request_id' => $this->pr->id, 'description' => 'Angle bar',
            'quantity_required' => 3, 'unit' => 'PCS',
        ]);
    }

    private function officer(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Procurement Officer');

        return $user;
    }

    private function quote(string $supplierName, array $lines): SupplierQuote
    {
        $supplier = Supplier::factory()->create(['name' => $supplierName]);
        $quote = SupplierQuote::factory()->create([
            'purchase_request_id' => $this->pr->id, 'supplier_id' => $supplier->id,
        ]);

        foreach ($lines as $line) {
            SupplierQuoteItem::factory()->create($line + ['supplier_quote_id' => $quote->id]);
        }

        return $quote;
    }

    public function test_the_workspace_requires_permission_to_manage_quotes(): void
    {
        $this->getJson("/api/v1/purchase/requests/{$this->pr->id}/quotes")->assertUnauthorized();

        $this->actingAs(User::factory()->create())
            ->getJson("/api/v1/purchase/requests/{$this->pr->id}/quotes")->assertForbidden();
    }

    public function test_it_lists_every_item_with_every_suppliers_offer(): void
    {
        $this->quote('Gulf Steel', [
            ['purchase_request_item_id' => $this->itemA->id, 'unit_price' => 10, 'total_price' => 20],
            ['purchase_request_item_id' => $this->itemB->id, 'unit_price' => 5, 'total_price' => 15],
        ]);
        // Zenith did not quote item B at all.
        $this->quote('Zenith', [
            ['purchase_request_item_id' => $this->itemA->id, 'unit_price' => 9, 'total_price' => 18],
        ]);

        $response = $this->actingAs($this->officer())
            ->getJson("/api/v1/purchase/requests/{$this->pr->id}/quotes")->assertOk();

        $this->assertSame(2, $response->json('data.quote_count'));
        $this->assertSame(['Steel plate', 'Angle bar'], array_column($response->json('data.items'), 'description'));

        // Every supplier gets a row on every item, quoted or not — that is what
        // makes the comparison item-for-item.
        $this->assertCount(2, $response->json('data.items.0.rows'));
        $this->assertCount(2, $response->json('data.items.1.rows'));
        $this->assertNull($response->json('data.items.1.rows.1.line'));

        // Cheapest valid offer on an item is flagged, and only when there is a
        // contest.
        $this->assertFalse($response->json('data.items.0.rows.0.is_min'));
        $this->assertTrue($response->json('data.items.0.rows.1.is_min'));
        $this->assertFalse($response->json('data.items.1.rows.0.is_min'));
    }

    public function test_the_badge_describes_the_state_of_each_item(): void
    {
        $this->quote('Gulf Steel', [
            ['purchase_request_item_id' => $this->itemA->id, 'unit_price' => 10, 'total_price' => 20],
        ]);
        $this->quote('Zenith', [
            ['purchase_request_item_id' => $this->itemA->id, 'unit_price' => 9, 'total_price' => 18],
        ]);

        $response = $this->actingAs($this->officer())
            ->getJson("/api/v1/purchase/requests/{$this->pr->id}/quotes")->assertOk();

        $this->assertSame('2 suppliers competing', $response->json('data.items.0.badge.label'));
        $this->assertSame('No quotes yet', $response->json('data.items.1.badge.label'));
    }

    public function test_a_single_offer_reads_as_sole_sourced(): void
    {
        $this->quote('Gulf Steel', [
            ['purchase_request_item_id' => $this->itemA->id, 'unit_price' => 10, 'total_price' => 20],
        ]);

        $response = $this->actingAs($this->officer())
            ->getJson("/api/v1/purchase/requests/{$this->pr->id}/quotes")->assertOk();

        $this->assertSame('Sole-sourced', $response->json('data.items.0.badge.label'));
    }

    /**
     * The subtotal is not any one supplier's total: it sums, per item, whichever
     * price wins — awarded if decided, lowest otherwise — with VAT on the
     * winning vatable lines.
     */
    public function test_the_grand_total_takes_the_winning_price_per_item(): void
    {
        Setting::set('vat_rate', '10');
        $this->quote('Gulf Steel', [
            ['purchase_request_item_id' => $this->itemA->id, 'unit_price' => 10, 'total_price' => 20, 'is_vatable' => true],
            ['purchase_request_item_id' => $this->itemB->id, 'unit_price' => 5, 'total_price' => 15],
        ]);
        $this->quote('Zenith', [
            ['purchase_request_item_id' => $this->itemA->id, 'unit_price' => 9, 'total_price' => 18, 'is_vatable' => false],
        ]);

        $response = $this->actingAs($this->officer())
            ->getJson("/api/v1/purchase/requests/{$this->pr->id}/quotes")->assertOk();

        // Item A takes Zenith's 18 (lowest, not vatable), item B Gulf's 15.
        $this->assertEquals(33, $response->json('data.subtotal'));
        $this->assertEquals(0, $response->json('data.vat_amount'));
        $this->assertEquals(33, $response->json('data.grand_total'));
        $this->assertSame(0, $response->json('data.unresolved_items'));
    }

    public function test_items_nobody_quoted_are_counted_as_unresolved(): void
    {
        $this->quote('Gulf Steel', [
            ['purchase_request_item_id' => $this->itemA->id, 'unit_price' => 10, 'total_price' => 20],
        ]);

        $response = $this->actingAs($this->officer())
            ->getJson("/api/v1/purchase/requests/{$this->pr->id}/quotes")->assertOk();

        $this->assertSame(1, $response->json('data.unresolved_items'));
        $this->assertEquals(20, $response->json('data.subtotal'));
    }

    public function test_awarding_records_the_reason_and_who_decided(): void
    {
        $quote = $this->quote('Gulf Steel', [
            ['purchase_request_item_id' => $this->itemA->id, 'unit_price' => 10, 'total_price' => 20],
        ]);
        $line = $quote->items->first();
        $officer = $this->officer();

        $response = $this->actingAs($officer)
            ->postJson("/api/v1/purchase/requests/{$this->pr->id}/quotes/items/{$line->id}/award", [
                'award_reason' => 'Best lead time and price',
            ])->assertOk();

        $this->assertStringContainsString('awarded to Gulf Steel', $response->json('message'));
        $this->assertTrue($response->json('data.items.0.has_award'));
        $this->assertSame('✓ Awarded to Gulf Steel', $response->json('data.items.0.badge.label'));
        // The awards list is what the Awarded Suppliers tab groups.
        $this->assertSame('Best lead time and price', $response->json('data.awards.0.reason'));
        $this->assertSame($officer->name, $response->json('data.awards.0.awarded_by'));
    }

    /** The reason is the audit record, so it is required and not a token effort. */
    public function test_awarding_requires_a_reason_of_substance(): void
    {
        $quote = $this->quote('Gulf Steel', [
            ['purchase_request_item_id' => $this->itemA->id, 'unit_price' => 10, 'total_price' => 20],
        ]);
        $line = $quote->items->first();

        $this->actingAs($this->officer())
            ->postJson("/api/v1/purchase/requests/{$this->pr->id}/quotes/items/{$line->id}/award", [])
            ->assertStatus(422)->assertJsonValidationErrors('award_reason');

        $this->actingAs($this->officer())
            ->postJson("/api/v1/purchase/requests/{$this->pr->id}/quotes/items/{$line->id}/award", ['award_reason' => 'ok'])
            ->assertStatus(422)->assertJsonValidationErrors('award_reason');

        $this->assertFalse((bool) $line->fresh()->is_awarded);
    }

    /** One item, one supplier — awarding twice would double-order it. */
    public function test_an_item_cannot_be_awarded_to_two_suppliers(): void
    {
        $first = $this->quote('Gulf Steel', [
            ['purchase_request_item_id' => $this->itemA->id, 'unit_price' => 10, 'total_price' => 20],
        ]);
        $second = $this->quote('Zenith', [
            ['purchase_request_item_id' => $this->itemA->id, 'unit_price' => 9, 'total_price' => 18],
        ]);
        $officer = $this->officer();

        $this->actingAs($officer)
            ->postJson("/api/v1/purchase/requests/{$this->pr->id}/quotes/items/{$first->items->first()->id}/award", [
                'award_reason' => 'Preferred supplier',
            ])->assertOk();

        $this->actingAs($officer)
            ->postJson("/api/v1/purchase/requests/{$this->pr->id}/quotes/items/{$second->items->first()->id}/award", [
                'award_reason' => 'Actually cheaper',
            ])->assertStatus(422);

        $this->assertFalse((bool) $second->items->first()->fresh()->is_awarded);
    }

    public function test_a_line_the_supplier_marked_unavailable_cannot_be_awarded(): void
    {
        $quote = $this->quote('Gulf Steel', [
            ['purchase_request_item_id' => $this->itemA->id, 'unit_price' => 0, 'total_price' => 0, 'not_available' => true],
        ]);

        $this->actingAs($this->officer())
            ->postJson("/api/v1/purchase/requests/{$this->pr->id}/quotes/items/{$quote->items->first()->id}/award", [
                'award_reason' => 'Trying anyway',
            ])->assertStatus(422);
    }

    /** A quote line from another request must not be awardable through this one. */
    public function test_a_line_from_another_request_is_not_found(): void
    {
        $other = PurchaseRequest::factory()->create(['stage' => 'comparison']);
        $quote = SupplierQuote::factory()->create(['purchase_request_id' => $other->id]);
        $line = SupplierQuoteItem::factory()->create(['supplier_quote_id' => $quote->id]);

        $this->actingAs($this->officer())
            ->postJson("/api/v1/purchase/requests/{$this->pr->id}/quotes/items/{$line->id}/award", [
                'award_reason' => 'Wrong request entirely',
            ])->assertNotFound();
    }

    public function test_awarding_every_item_advances_the_request_to_the_lpo_stage(): void
    {
        $quote = $this->quote('Gulf Steel', [
            ['purchase_request_item_id' => $this->itemA->id, 'unit_price' => 10, 'total_price' => 20],
            ['purchase_request_item_id' => $this->itemB->id, 'unit_price' => 5, 'total_price' => 15],
        ]);
        $officer = $this->officer();

        foreach ($quote->items as $line) {
            $this->actingAs($officer)
                ->postJson("/api/v1/purchase/requests/{$this->pr->id}/quotes/items/{$line->id}/award", [
                    'award_reason' => 'Single supplier for both',
                ])->assertOk();
        }

        $this->assertSame('lpo', $this->pr->fresh()->stage);
    }

    /** Taking an award back means the request is no longer ready for an LPO. */
    public function test_unawarding_drops_the_request_back_out_of_the_lpo_stage(): void
    {
        $quote = $this->quote('Gulf Steel', [
            ['purchase_request_item_id' => $this->itemA->id, 'unit_price' => 10, 'total_price' => 20],
            ['purchase_request_item_id' => $this->itemB->id, 'unit_price' => 5, 'total_price' => 15],
        ]);
        $officer = $this->officer();

        foreach ($quote->items as $line) {
            $this->actingAs($officer)
                ->postJson("/api/v1/purchase/requests/{$this->pr->id}/quotes/items/{$line->id}/award", [
                    'award_reason' => 'Single supplier for both',
                ])->assertOk();
        }

        $response = $this->actingAs($officer)
            ->postJson("/api/v1/purchase/requests/{$this->pr->id}/quotes/items/{$quote->items->first()->id}/unaward")
            ->assertOk();

        $this->assertStringContainsString('Pick a new supplier', $response->json('message'));
        $this->assertSame('comparison', $this->pr->fresh()->stage);
        $this->assertFalse($response->json('data.fully_awarded'));
        $this->assertCount(1, $response->json('data.awards'));
    }

    public function test_unawarding_something_that_was_never_awarded_is_refused(): void
    {
        $quote = $this->quote('Gulf Steel', [
            ['purchase_request_item_id' => $this->itemA->id, 'unit_price' => 10, 'total_price' => 20],
        ]);

        $this->actingAs($this->officer())
            ->postJson("/api/v1/purchase/requests/{$this->pr->id}/quotes/items/{$quote->items->first()->id}/unaward")
            ->assertStatus(422);
    }
}
