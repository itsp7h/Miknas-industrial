<?php

namespace Tests\Feature\Api\Purchase;

use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Supplier;
use App\Models\SupplierQuote;
use App\Models\SupplierQuoteItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Award all": every line one supplier quoted, awarded in one go with one
 * reason — the same decision as awarding each by hand.
 */
class AwardAllTest extends TestCase
{
    use RefreshDatabase;

    private PurchaseRequest $pr;

    private PurchaseRequestItem $plate;

    private PurchaseRequestItem $bar;

    private PurchaseRequestItem $bolt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pr = PurchaseRequest::factory()->create(['stage' => 'comparison']);
        foreach (['plate' => 'Steel plate', 'bar' => 'Angle bar', 'bolt' => 'Bolt'] as $key => $description) {
            $this->{$key} = PurchaseRequestItem::create([
                'purchase_request_id' => $this->pr->id, 'description' => $description,
                'quantity_required' => 2, 'unit' => 'PCS',
            ]);
        }
    }

    private function buyer(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['pipeline.manage-quotes', 'pipeline.award', 'pipeline.view-active-pipeline']);

        return $user;
    }

    private function quote(string $supplier, array $lines): SupplierQuote
    {
        $quote = SupplierQuote::factory()->create([
            'purchase_request_id' => $this->pr->id,
            'supplier_id' => Supplier::factory()->create(['name' => $supplier])->id,
        ]);
        foreach ($lines as $line) {
            SupplierQuoteItem::factory()->create($line + ['supplier_quote_id' => $quote->id]);
        }

        return $quote;
    }

    private function line(PurchaseRequestItem $item, array $extra = []): array
    {
        return array_merge(['purchase_request_item_id' => $item->id, 'description' => $item->description,
            'unit_price' => 10, 'total_price' => 20, 'not_available' => false, 'is_awarded' => false], $extra);
    }

    private function awardAll(SupplierQuote $quote, string $reason = 'Only supplier quoted')
    {
        return $this->actingAs($this->buyer())
            ->postJson("/api/v1/purchase/requests/{$this->pr->id}/quotes/{$quote->id}/award-all", ['award_reason' => $reason]);
    }

    public function test_it_awards_every_line_the_supplier_quoted_with_one_reason(): void
    {
        $quote = $this->quote('Yousif Dhneem', [$this->line($this->plate), $this->line($this->bar), $this->line($this->bolt)]);

        $this->awardAll($quote)
            ->assertOk()
            ->assertJsonPath('message', '3 items awarded to Yousif Dhneem.')
            ->assertJsonPath('data.fully_awarded', true);

        $this->assertSame(3, $quote->items()->where('is_awarded', true)->where('award_reason', 'Only supplier quoted')->count());
        $this->assertSame('lpo', $this->pr->fresh()->stage);
    }

    public function test_it_skips_a_line_the_supplier_could_not_supply(): void
    {
        $quote = $this->quote('Yousif Dhneem', [$this->line($this->plate), $this->line($this->bar, ['not_available' => true])]);

        $this->awardAll($quote)->assertOk()->assertJsonPath('message', '1 item awarded to Yousif Dhneem.');

        $this->assertFalse((bool) $quote->items()->where('purchase_request_item_id', $this->bar->id)->value('is_awarded'));
    }

    /** As a single award does, it takes an item over rather than double-ordering it. */
    public function test_an_item_awarded_elsewhere_moves_to_this_supplier_and_says_so(): void
    {
        $gulf = $this->quote('Gulf Steel', [$this->line($this->plate, ['is_awarded' => true, 'award_reason' => 'Cheaper'])]);
        $yousif = $this->quote('Yousif Dhneem', [$this->line($this->plate), $this->line($this->bar)]);

        $this->awardAll($yousif)
            ->assertOk()
            ->assertJsonPath('message', '2 items awarded to Yousif Dhneem (1 moved from Gulf Steel).');

        $this->assertSame(0, $gulf->items()->where('is_awarded', true)->count());
        $this->assertSame(1, SupplierQuoteItem::where('purchase_request_item_id', $this->plate->id)->where('is_awarded', true)->count());
    }

    /** Not fully awarded while another item is quoted by someone else and still open. */
    public function test_the_request_stays_in_comparison_while_something_else_is_undecided(): void
    {
        $this->quote('Gulf Steel', [$this->line($this->bolt)]);
        $yousif = $this->quote('Yousif Dhneem', [$this->line($this->plate), $this->line($this->bar)]);

        $this->awardAll($yousif)->assertOk()->assertJsonPath('data.fully_awarded', false);

        $this->assertSame('comparison', $this->pr->fresh()->stage);
    }

    public function test_it_needs_a_real_reason(): void
    {
        $quote = $this->quote('Yousif Dhneem', [$this->line($this->plate)]);

        $this->awardAll($quote, 'ok')->assertStatus(422)->assertJsonValidationErrors('award_reason');
        $this->assertSame(0, $quote->items()->where('is_awarded', true)->count());
    }

    public function test_it_says_so_when_there_is_nothing_left_to_award(): void
    {
        $quote = $this->quote('Yousif Dhneem', [$this->line($this->plate, ['is_awarded' => true])]);

        $this->awardAll($quote)->assertStatus(422)
            ->assertJsonPath('message', 'Everything Yousif Dhneem quoted is already awarded to them.');
    }

    public function test_a_quote_from_another_request_is_not_found(): void
    {
        $other = SupplierQuote::factory()->create([
            'purchase_request_id' => PurchaseRequest::factory()->create(['stage' => 'comparison'])->id,
        ]);

        $this->awardAll($other)->assertNotFound();
    }

    public function test_it_needs_the_award_permission_and_the_right_stage(): void
    {
        $quote = $this->quote('Yousif Dhneem', [$this->line($this->plate)]);
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(['pipeline.manage-quotes', 'pipeline.view-active-pipeline']);

        $this->actingAs($viewer)
            ->postJson("/api/v1/purchase/requests/{$this->pr->id}/quotes/{$quote->id}/award-all", ['award_reason' => 'Only supplier quoted'])
            ->assertForbidden();

        $this->pr->update(['stage' => 'receiving']);
        $this->awardAll($quote)->assertForbidden();
    }

    public function test_the_workspace_summarises_each_supplier_for_award_all(): void
    {
        $this->quote('Gulf Steel', [$this->line($this->plate, ['is_awarded' => true])]);
        $this->quote('Yousif Dhneem', [
            $this->line($this->plate), $this->line($this->bar, ['is_awarded' => true]),
            $this->line($this->bolt, ['not_available' => true]),
        ]);

        $suppliers = collect($this->actingAs($this->buyer())
            ->getJson("/api/v1/purchase/requests/{$this->pr->id}/quotes")
            ->assertOk()
            ->json('data.suppliers'))->keyBy('supplier');

        $this->assertSame(2, $suppliers['Yousif Dhneem']['quoted']);
        $this->assertSame(1, $suppliers['Yousif Dhneem']['awarded']);
        $this->assertSame(1, $suppliers['Yousif Dhneem']['held_elsewhere']);
        $this->assertEquals(40, $suppliers['Yousif Dhneem']['total']);
        $this->assertSame(0, $suppliers['Gulf Steel']['held_elsewhere']);
    }
}
