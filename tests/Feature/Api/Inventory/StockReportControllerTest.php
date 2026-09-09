<?php

namespace Tests\Feature\Api\Inventory;

use App\Models\Item;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockReportControllerTest extends TestCase
{
    use RefreshDatabase;

    private Item $rod;

    private Item $widget;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rod = Item::create([
            'item_code' => 'ITEM-1', 'item_name' => 'Rod', 'category' => 'raw_material',
            'unit_of_measure' => 'PCS', 'minimum_stock_level' => 10, 'cost_price' => 2.50,
        ]);
        $this->widget = Item::create([
            'item_code' => 'ITEM-2', 'item_name' => 'Widget', 'category' => 'finished_good',
            'unit_of_measure' => 'BOX', 'minimum_stock_level' => 1, 'cost_price' => 100,
        ]);
        $this->warehouse = Warehouse::create(['code' => 'WH-1', 'name' => 'Main']);

        // Rod is below its minimum of 10; Widget is comfortably above 1.
        StockLevel::create(['item_id' => $this->rod->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => 4]);
        StockLevel::create(['item_id' => $this->widget->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => 3]);
    }

    private function actingUser(): User
    {
        return User::factory()->create();
    }

    public function test_all_report_endpoints_require_authentication(): void
    {
        foreach (['summary', 'movement', 'low-stock', 'valuation'] as $report) {
            $this->getJson("/api/v1/inventory/reports/{$report}")->assertUnauthorized();
        }
    }

    public function test_summary_lists_every_stock_line_with_item_and_warehouse_names(): void
    {
        $response = $this->actingAs($this->actingUser())
            ->getJson('/api/v1/inventory/reports/summary')->assertOk();

        $this->assertSame(2, $response->json('meta.total_lines'));
        $this->assertEqualsCanonicalizing(['Rod', 'Widget'], array_column($response->json('data'), 'item_name'));
        $this->assertSame('Main', $response->json('data.0.warehouse_name'));
    }

    /**
     * The Blade summary report painted a below-minimum line red with a LOW
     * badge. The endpoint sent neither the minimum nor a flag, so the React
     * page could not reproduce either.
     */
    public function test_summary_reports_the_minimum_and_flags_lines_below_it(): void
    {
        $response = $this->actingAs($this->actingUser())
            ->getJson('/api/v1/inventory/reports/summary')->assertOk();

        $rows = collect($response->json('data'))->keyBy('item_name');

        $this->assertNotNull($rows['Rod']['minimum_stock_level']);
        $this->assertTrue($rows['Rod']['is_low']);
        $this->assertFalse($rows['Widget']['is_low']);
        $this->assertSame(1, $response->json('meta.below_minimum'));
    }

    public function test_low_stock_returns_only_lines_below_the_items_minimum(): void
    {
        $response = $this->actingAs($this->actingUser())
            ->getJson('/api/v1/inventory/reports/low-stock')->assertOk();

        $this->assertSame(1, $response->json('meta.below_minimum'));
        $this->assertSame('Rod', $response->json('data.0.item_name'));
    }

    public function test_low_stock_reports_how_far_below_minimum_each_line_is(): void
    {
        $response = $this->actingAs($this->actingUser())
            ->getJson('/api/v1/inventory/reports/low-stock')->assertOk();

        // minimum 10, on hand 4
        $this->assertEquals(6, $response->json('data.0.shortfall'));
    }

    /** The Blade low-stock report showed the item's category; it was not sent. */
    public function test_low_stock_includes_the_items_category(): void
    {
        $response = $this->actingAs($this->actingUser())
            ->getJson('/api/v1/inventory/reports/low-stock')->assertOk();

        $this->assertSame('raw_material', $response->json('data.0.category'));
    }

    public function test_valuation_multiplies_quantity_by_cost_and_totals_it(): void
    {
        $response = $this->actingAs($this->actingUser())
            ->getJson('/api/v1/inventory/reports/valuation')->assertOk();

        // Rod 4 x 2.50 = 10, Widget 3 x 100 = 300
        $this->assertEquals(310, $response->json('meta.total_valuation'));
    }

    /**
     * Grouped per item, and carrying the category the Blade view tried to badge.
     * Its controller selected neither `total_qty` nor `category`, so that report
     * showed 0.00 with a blank badge on every row.
     */
    public function test_valuation_groups_per_item_with_its_category(): void
    {
        // A second warehouse holding the same item must fold into one row.
        $other = Warehouse::create(['name' => 'Annex', 'code' => 'WH-2']);
        StockLevel::create(['item_id' => $this->rod->id, 'warehouse_id' => $other->id, 'quantity' => 6]);

        $response = $this->actingAs($this->actingUser())
            ->getJson('/api/v1/inventory/reports/valuation')->assertOk();

        $rows = collect($response->json('data'))->keyBy('item_name');

        $this->assertCount(2, $rows);
        // Rod: 4 in Main + 6 in Annex = 10, at 2.50 = 25.
        $this->assertEquals(10, $rows['Rod']['total_qty']);
        $this->assertEquals(25, $rows['Rod']['total_value']);
        $this->assertSame('raw_material', $rows['Rod']['category']);
        $this->assertEquals(325, $response->json('meta.total_valuation'));
    }

    public function test_movement_report_returns_all_movements_and_the_item_filter_list(): void
    {
        $this->makeMovement($this->rod, 'in', 5);
        $this->makeMovement($this->widget, 'out', 1);

        $response = $this->actingAs($this->actingUser())
            ->getJson('/api/v1/inventory/reports/movement')->assertOk();

        $this->assertCount(2, $response->json('data'));
        $this->assertCount(2, $response->json('meta.items'));
    }

    public function test_movement_report_filters_by_item(): void
    {
        $this->makeMovement($this->rod, 'in', 5);
        $this->makeMovement($this->widget, 'out', 1);

        $response = $this->actingAs($this->actingUser())
            ->getJson('/api/v1/inventory/reports/movement?item_id='.$this->rod->id)->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame('Rod', $response->json('data.0.item_name'));
    }

    public function test_movement_report_filters_by_date_range(): void
    {
        $old = $this->makeMovement($this->rod, 'in', 5);
        $old->forceFill(['created_at' => now()->subMonth()])->save();
        $this->makeMovement($this->widget, 'out', 1);

        $response = $this->actingAs($this->actingUser())
            ->getJson('/api/v1/inventory/reports/movement?from_date='.now()->subDay()->toDateString())
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame('Widget', $response->json('data.0.item_name'));
    }

    public function test_movement_report_rejects_a_malformed_date(): void
    {
        $this->actingAs($this->actingUser())
            ->getJson('/api/v1/inventory/reports/movement?from_date=not-a-date')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['from_date']);
    }

    private function makeMovement(Item $item, string $type, float $quantity): StockMovement
    {
        return StockMovement::create([
            'item_id' => $item->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => $type,
            'quantity' => $quantity,
        ]);
    }
}
