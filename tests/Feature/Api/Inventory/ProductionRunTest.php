<?php

namespace Tests\Feature\Api\Inventory;

use App\Events\ItemSaved;
use App\Events\ProductionRunRecorded;
use App\Events\RecipeSaved;
use App\Events\StockMovementRecorded;
use App\Models\BillOfMaterial;
use App\Models\Item;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Inventory → Production: a finished good made from raw materials, at cost.
 */
class ProductionRunTest extends TestCase
{
    use RefreshDatabase;

    private Item $bag;

    private Item $cement;

    private Item $sand;

    private Warehouse $main;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bag = Item::create(['item_code' => 'ITEM-00001', 'item_name' => 'SUPERBOND F5 White 25kg', 'category' => 'finished_good', 'unit_of_measure' => 'BAG', 'cost_price' => 9]);
        $this->cement = Item::create(['item_code' => 'RM-1', 'item_name' => 'Cement', 'category' => 'raw_material', 'unit_of_measure' => 'KG', 'cost_price' => 0.5]);
        $this->sand = Item::create(['item_code' => 'RM-2', 'item_name' => 'Sand', 'category' => 'raw_material', 'unit_of_measure' => 'KG', 'cost_price' => 0.1]);
        $this->main = Warehouse::create(['code' => 'WH-1', 'name' => 'Main']);

        StockLevel::create(['item_id' => $this->cement->id, 'warehouse_id' => $this->main->id, 'quantity' => 1000]);
        StockLevel::create(['item_id' => $this->sand->id, 'warehouse_id' => $this->main->id, 'quantity' => 500]);
    }

    private function userWith(array $permissions): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function maker(): User
    {
        return $this->userWith(['production.view', 'production.create']);
    }

    /** 100 bags from 1,000 kg of cement and 250 kg of sand. */
    private function runPayload(array $overrides = []): array
    {
        return array_replace([
            'item_id' => $this->bag->id,
            'warehouse_id' => $this->main->id,
            'quantity' => 100,
            'production_date' => '2026-10-05',
            'lines' => [
                ['item_id' => $this->cement->id, 'warehouse_id' => $this->main->id, 'quantity' => 1000],
                ['item_id' => $this->sand->id, 'warehouse_id' => $this->main->id, 'quantity' => 250],
            ],
        ], $overrides);
    }

    private function stock(Item $item): float
    {
        return (float) StockLevel::where('item_id', $item->id)->where('warehouse_id', $this->main->id)->value('quantity');
    }

    public function test_a_run_adds_the_product_and_takes_the_materials_out(): void
    {
        $this->actingAs($this->maker())->postJson('/api/v1/inventory/production', $this->runPayload())
            ->assertCreated()
            ->assertJsonPath('data.run_number', 'PRD-00001')
            ->assertJsonPath('message', 'PRD-00001 recorded — stock updated.');

        $this->assertSame(100.0, $this->stock($this->bag));
        $this->assertSame(0.0, $this->stock($this->cement));
        $this->assertSame(250.0, $this->stock($this->sand));
    }

    /** 1,000 kg × 0.50 + 250 kg × 0.10 = 525.00, so a bag cost 5.25. */
    public function test_it_costs_the_run_from_each_materials_cost_price(): void
    {
        $response = $this->actingAs($this->maker())->postJson('/api/v1/inventory/production', $this->runPayload())
            ->assertCreated();

        $this->assertEquals(525, $response->json('data.total_cost'));
        $this->assertEquals(5.25, $response->json('data.unit_cost'));
        $this->assertEquals([500, 25], array_map('floatval', array_column($response->json('data.lines'), 'line_cost')));
    }

    public function test_the_products_cost_price_becomes_what_one_unit_cost(): void
    {
        $this->actingAs($this->maker())->postJson('/api/v1/inventory/production', $this->runPayload())->assertCreated();

        $this->assertEquals(5.25, $this->bag->fresh()->cost_price);
    }

    /** A material's price changing later must not rewrite what the run cost. */
    public function test_a_line_keeps_the_cost_price_it_was_charged_at(): void
    {
        $this->actingAs($this->maker())->postJson('/api/v1/inventory/production', $this->runPayload())->assertCreated();
        $this->cement->update(['cost_price' => 2]);

        $line = $this->actingAs($this->maker())->getJson('/api/v1/inventory/production')
            ->json('data.0.lines.0');

        $this->assertEquals(0.5, $line['unit_cost']);
        $this->assertEquals(500, $line['line_cost']);
    }

    /** Materials with no cost price say nothing about cost: keep the old price. */
    public function test_a_run_with_no_material_cost_leaves_the_products_price_alone(): void
    {
        $this->cement->update(['cost_price' => 0]);
        $this->sand->update(['cost_price' => 0]);

        $this->actingAs($this->maker())->postJson('/api/v1/inventory/production', $this->runPayload())->assertCreated();

        $this->assertEquals(9, $this->bag->fresh()->cost_price);
    }

    public function test_every_change_is_a_stock_movement_pointing_at_the_run(): void
    {
        $id = $this->actingAs($this->maker())->postJson('/api/v1/inventory/production', $this->runPayload())->json('data.id');

        $movements = StockMovement::where('reference_type', 'ProductionRun')->where('reference_id', $id)->get();

        $this->assertCount(3, $movements);
        $this->assertSame(['out', 'out', 'in'], $movements->pluck('type')->all());
        $this->assertSame($this->bag->id, $movements->last()->item_id);
    }

    public function test_it_refuses_more_than_is_on_hand_and_moves_nothing(): void
    {
        $lines = $this->runPayload()['lines'];
        $lines[1]['quantity'] = 501;

        $this->actingAs($this->maker())->postJson('/api/v1/inventory/production', $this->runPayload(['lines' => $lines]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lines.1.quantity' => 'Only 500.00 KG of Sand on hand in Main.']);

        $this->assertSame(1000.0, $this->stock($this->cement));
        $this->assertSame(0.0, $this->stock($this->bag));
        $this->assertSame(0, StockMovement::count());
    }

    public function test_it_needs_a_finished_good_and_at_least_one_raw_material(): void
    {
        $this->actingAs($this->maker())
            ->postJson('/api/v1/inventory/production', $this->runPayload(['item_id' => $this->cement->id, 'lines' => []]))
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'item_id' => 'Choose a finished good to make.',
                'lines' => 'Add at least one raw material.',
            ]);
    }

    public function test_it_broadcasts_the_run_its_movements_and_the_items_it_touched(): void
    {
        Event::fake([ProductionRunRecorded::class, StockMovementRecorded::class, ItemSaved::class]);

        $this->actingAs($this->maker())->postJson('/api/v1/inventory/production', $this->runPayload())->assertCreated();

        Event::assertDispatched(ProductionRunRecorded::class);
        Event::assertDispatchedTimes(StockMovementRecorded::class, 3);
        Event::assertDispatchedTimes(ItemSaved::class, 3);
    }

    public function test_the_form_offers_each_product_with_its_recipe_and_what_is_on_hand(): void
    {
        BillOfMaterial::create(['product_id' => $this->bag->id, 'raw_material_id' => $this->cement->id, 'quantity_required' => 10]);

        $response = $this->actingAs($this->maker())->getJson('/api/v1/inventory/production/form-options')->assertOk();

        $this->assertSame([$this->bag->id], array_column($response->json('finished_goods'), 'id'));
        $this->assertEquals(10, $response->json('finished_goods.0.recipe.0.quantity_required'));
        $this->assertEqualsCanonicalizing([$this->cement->id, $this->sand->id], array_column($response->json('raw_materials'), 'id'));
        $this->assertCount(2, $response->json('stock'));
    }

    public function test_a_recipe_is_replaced_whole_and_broadcast(): void
    {
        Event::fake([RecipeSaved::class]);
        BillOfMaterial::create(['product_id' => $this->bag->id, 'raw_material_id' => $this->sand->id, 'quantity_required' => 1]);

        $this->actingAs($this->userWith(['production.manage-recipes']))
            ->putJson("/api/v1/inventory/production/recipes/{$this->bag->id}", ['lines' => [
                ['raw_material_id' => $this->cement->id, 'quantity_required' => 10],
            ]])
            ->assertOk()
            ->assertJsonPath('message', 'Recipe for SUPERBOND F5 White 25kg saved.');

        $recipe = BillOfMaterial::where('product_id', $this->bag->id)->get();
        $this->assertCount(1, $recipe);
        $this->assertSame($this->cement->id, $recipe->first()->raw_material_id);
        $this->assertSame('KG', $recipe->first()->unit_of_measure);
        Event::assertDispatched(RecipeSaved::class);
    }

    public function test_only_a_finished_good_has_a_recipe(): void
    {
        $this->actingAs($this->userWith(['production.manage-recipes']))
            ->putJson("/api/v1/inventory/production/recipes/{$this->cement->id}", ['lines' => []])
            ->assertStatus(422);
    }

    public function test_each_action_needs_its_own_square(): void
    {
        $viewer = $this->userWith(['production.view']);

        $this->actingAs($viewer)->getJson('/api/v1/inventory/production')->assertOk();
        $this->actingAs($viewer)->getJson('/api/v1/inventory/production/form-options')->assertForbidden();
        $this->actingAs($viewer)->postJson('/api/v1/inventory/production', $this->runPayload())->assertForbidden();
        $this->actingAs($viewer)->putJson("/api/v1/inventory/production/recipes/{$this->bag->id}", ['lines' => []])->assertForbidden();

        // Recording a run is not editing what a product is made of.
        $this->actingAs($this->maker())->putJson("/api/v1/inventory/production/recipes/{$this->bag->id}", ['lines' => []])->assertForbidden();

        $this->actingAs($this->userWith(['raw-materials.view']))->getJson('/api/v1/inventory/production')->assertForbidden();
    }

    public function test_a_product_made_in_a_run_cannot_be_deleted(): void
    {
        $this->actingAs($this->maker())->postJson('/api/v1/inventory/production', $this->runPayload())->assertCreated();

        $this->assertContains('production runs', $this->bag->blockingReferences());
        $this->assertContains('production runs', $this->cement->blockingReferences());
    }
}
