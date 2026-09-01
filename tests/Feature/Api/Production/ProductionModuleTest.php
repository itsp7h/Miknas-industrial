<?php

namespace Tests\Feature\Api\Production;

use App\Models\BillOfMaterial;
use App\Models\Item;
use App\Models\ProductionOrder;
use App\Models\StockLevel;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionModuleTest extends TestCase
{
    use RefreshDatabase;

    private Item $product;

    private Item $rawMaterial;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->product = Item::create(['item_code' => 'FG-1', 'item_name' => 'Frame', 'category' => 'finished_good', 'unit_of_measure' => 'PCS']);
        $this->rawMaterial = Item::create(['item_code' => 'RM-1', 'item_name' => 'Steel Bar', 'category' => 'raw_material', 'unit_of_measure' => 'KG']);
        $this->warehouse = Warehouse::create(['code' => 'WH-1', 'name' => 'Main']);
    }

    private function actingUser(): User
    {
        return User::factory()->create();
    }

    private function makeOrder(string $status = 'planned'): ProductionOrder
    {
        return ProductionOrder::create([
            'order_number' => 'PO-'.uniqid(),
            'product_id' => $this->product->id,
            'quantity_to_produce' => 10,
            'quantity_produced' => 0,
            'production_date' => now(),
            'status' => $status,
        ]);
    }

    // ---------------- production orders ----------------

    public function test_all_production_endpoints_require_authentication(): void
    {
        foreach (['orders', 'bom', 'material-issues', 'outputs'] as $path) {
            $this->getJson("/api/v1/production/{$path}")->assertUnauthorized();
        }
    }

    public function test_it_creates_a_planned_order_with_a_generated_number(): void
    {
        $response = $this->actingAs($this->actingUser())->postJson('/api/v1/production/orders', [
            'product_id' => $this->product->id,
            'quantity_to_produce' => 10,
            'production_date' => now()->toDateString(),
        ])->assertCreated();

        $this->assertSame('planned', $response->json('data.status'));
        $this->assertMatchesRegularExpression('/^PO-\d{5}$/', $response->json('data.order_number'));
        $this->assertEquals(10, $response->json('data.outstanding'));
    }

    public function test_form_options_offers_only_producible_items(): void
    {
        $response = $this->actingAs($this->actingUser())
            ->getJson('/api/v1/production/orders/form-options')->assertOk();

        // Raw material is not something you produce.
        $this->assertSame(['Frame'], array_column($response->json('products'), 'item_name'));
    }

    public function test_an_order_moves_planned_to_in_progress_to_completed(): void
    {
        $order = $this->makeOrder();
        $user = $this->actingUser();

        $this->actingAs($user)->patchJson("/api/v1/production/orders/{$order->id}/start")
            ->assertOk()->assertJsonPath('data.status', 'in_progress');
        $this->actingAs($user)->patchJson("/api/v1/production/orders/{$order->id}/complete")
            ->assertOk()->assertJsonPath('data.status', 'completed');
    }

    public function test_an_order_cannot_be_completed_before_it_starts(): void
    {
        $order = $this->makeOrder();

        $this->actingAs($this->actingUser())
            ->patchJson("/api/v1/production/orders/{$order->id}/complete")
            ->assertStatus(422);
    }

    /**
     * The Blade version had no guard, so completing twice re-sent the WhatsApp
     * alert to every production manager each time.
     */
    public function test_an_order_cannot_be_completed_twice(): void
    {
        $order = $this->makeOrder('in_progress');
        $user = $this->actingUser();

        $this->actingAs($user)->patchJson("/api/v1/production/orders/{$order->id}/complete")->assertOk();
        $this->actingAs($user)->patchJson("/api/v1/production/orders/{$order->id}/complete")->assertStatus(422);
    }

    public function test_an_order_in_progress_cannot_be_edited_or_deleted(): void
    {
        $order = $this->makeOrder('in_progress');
        $user = $this->actingUser();

        $this->actingAs($user)->putJson("/api/v1/production/orders/{$order->id}", [
            'product_id' => $this->product->id, 'quantity_to_produce' => 99, 'production_date' => now()->toDateString(),
        ])->assertStatus(422);
        $this->actingAs($user)->deleteJson("/api/v1/production/orders/{$order->id}")->assertStatus(422);
    }

    /**
     * The Blade show page laid out four sections — order details, the product's
     * BOM, material issues and output — but its controller passed only
     * compact('productionOrder'), so $bom, $materialIssues and $outputs were
     * never set and three of them never rendered. The detail endpoint the React
     * page reads has to actually carry them.
     */
    public function test_the_detail_endpoint_carries_bom_material_issues_and_output(): void
    {
        BillOfMaterial::create([
            'product_id' => $this->product->id, 'raw_material_id' => $this->rawMaterial->id,
            'quantity_required' => 2.5, 'unit_of_measure' => 'KG',
        ]);
        StockLevel::create(['item_id' => $this->rawMaterial->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => 50]);
        $order = $this->makeOrder('in_progress');
        $user = $this->actingUser();

        $this->actingAs($user)->postJson('/api/v1/production/material-issues', [
            'production_order_id' => $order->id, 'item_id' => $this->rawMaterial->id,
            'warehouse_id' => $this->warehouse->id, 'quantity' => 20, 'issue_date' => now()->toDateString(),
        ])->assertCreated();
        $this->actingAs($user)->postJson('/api/v1/production/outputs', [
            'production_order_id' => $order->id, 'item_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id, 'quantity' => 4, 'output_date' => now()->toDateString(),
        ])->assertCreated();

        $response = $this->actingAs($user)->getJson("/api/v1/production/orders/{$order->id}")->assertOk();

        $this->assertSame('Frame', $response->json('data.product_name'));
        $this->assertSame('Steel Bar', $response->json('data.bom.0.material_name'));
        $this->assertEquals(2.5, $response->json('data.bom.0.quantity_required'));
        $this->assertSame('Steel Bar', $response->json('data.material_issues.0.item_name'));
        $this->assertSame('Main', $response->json('data.material_issues.0.warehouse_name'));
        $this->assertSame('Frame', $response->json('data.outputs.0.item_name'));
        $this->assertEquals(6, $response->json('data.outstanding'));
    }

    public function test_the_detail_endpoint_returns_empty_sections_rather_than_omitting_them(): void
    {
        $order = $this->makeOrder();

        $this->actingAs($this->actingUser())->getJson("/api/v1/production/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.bom', [])
            ->assertJsonPath('data.material_issues', [])
            ->assertJsonPath('data.outputs', []);
    }

    // ---------------- bill of materials ----------------

    public function test_it_creates_a_bom_line(): void
    {
        $this->actingAs($this->actingUser())->postJson('/api/v1/production/bom', [
            'product_id' => $this->product->id,
            'raw_material_id' => $this->rawMaterial->id,
            'quantity_required' => 2.5,
            'unit_of_measure' => 'KG',
        ])->assertCreated()->assertJsonPath('data.raw_material_name', 'Steel Bar');
    }

    /**
     * The page groups lines into one card per product as it walks the list, so an
     * unordered response would open a second card for a product already seen.
     * It also needs each product's code for the card header.
     */
    public function test_bom_lines_arrive_grouped_by_product_with_item_codes(): void
    {
        $panel = Item::create(['item_code' => 'FG-2', 'item_name' => 'Panel', 'category' => 'finished_good', 'unit_of_measure' => 'PCS']);
        $bolt = Item::create(['item_code' => 'RM-2', 'item_name' => 'Bolt', 'category' => 'raw_material', 'unit_of_measure' => 'PCS']);

        // Inserted interleaved: Frame, Panel, Frame.
        foreach ([[$this->product, $this->rawMaterial], [$panel, $bolt], [$this->product, $bolt]] as [$product, $material]) {
            BillOfMaterial::create([
                'product_id' => $product->id, 'raw_material_id' => $material->id,
                'quantity_required' => 1, 'unit_of_measure' => 'KG',
            ]);
        }

        $response = $this->actingAs($this->actingUser())->getJson('/api/v1/production/bom')->assertOk();

        $this->assertSame(
            ['Frame', 'Frame', 'Panel'],
            array_column($response->json('data'), 'product_name')
        );
        $this->assertSame('FG-1', $response->json('data.0.product_code'));
        $this->assertSame('RM-1', $response->json('data.0.raw_material_code'));
    }

    /** A product built from itself would recurse forever. */
    public function test_a_product_cannot_be_its_own_raw_material(): void
    {
        $this->actingAs($this->actingUser())->postJson('/api/v1/production/bom', [
            'product_id' => $this->product->id,
            'raw_material_id' => $this->product->id,
            'quantity_required' => 1,
            'unit_of_measure' => 'PCS',
        ])->assertStatus(422)->assertJsonValidationErrors(['raw_material_id']);
    }

    public function test_the_same_material_cannot_be_listed_twice_for_one_product(): void
    {
        BillOfMaterial::create([
            'product_id' => $this->product->id, 'raw_material_id' => $this->rawMaterial->id,
            'quantity_required' => 1, 'unit_of_measure' => 'KG',
        ]);

        $this->actingAs($this->actingUser())->postJson('/api/v1/production/bom', [
            'product_id' => $this->product->id,
            'raw_material_id' => $this->rawMaterial->id,
            'quantity_required' => 3,
            'unit_of_measure' => 'KG',
        ])->assertStatus(422)->assertJsonValidationErrors(['product_id']);
    }

    // ---------------- material issues ----------------

    public function test_issuing_material_decrements_stock_and_records_a_movement(): void
    {
        StockLevel::create(['item_id' => $this->rawMaterial->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => 50]);
        $order = $this->makeOrder('in_progress');

        $response = $this->actingAs($this->actingUser())->postJson('/api/v1/production/material-issues', [
            'production_order_id' => $order->id,
            'item_id' => $this->rawMaterial->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 20,
            'issue_date' => now()->toDateString(),
        ])->assertCreated();

        $this->assertEquals(30, StockLevel::first()->quantity);
        $this->assertDatabaseHas('stock_movements', [
            'type' => 'out', 'quantity' => 20,
            'reference_type' => 'MaterialIssue', 'reference_id' => $response->json('data.id'),
        ]);
    }

    /** The order select is labelled "order number - product", so both are needed. */
    public function test_material_issue_form_options_name_the_product_of_each_open_order(): void
    {
        $this->makeOrder('in_progress');
        $this->makeOrder('completed');

        $response = $this->actingAs($this->actingUser())
            ->getJson('/api/v1/production/material-issues/form-options')->assertOk();

        // A completed order has nothing left to issue against.
        $this->assertCount(1, $response->json('production_orders'));
        $this->assertSame('Frame', $response->json('production_orders.0.product_name'));
    }

    /**
     * The Blade version clamped the decrement at zero and issued anyway, so the
     * ledger recorded material that was never in the warehouse.
     */
    public function test_it_refuses_to_issue_more_material_than_is_on_hand(): void
    {
        StockLevel::create(['item_id' => $this->rawMaterial->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => 5]);
        $order = $this->makeOrder('in_progress');

        $this->actingAs($this->actingUser())->postJson('/api/v1/production/material-issues', [
            'production_order_id' => $order->id,
            'item_id' => $this->rawMaterial->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 40,
            'issue_date' => now()->toDateString(),
        ])->assertStatus(422)->assertJsonValidationErrors(['quantity']);

        $this->assertEquals(5, StockLevel::first()->quantity);
        $this->assertDatabaseCount('material_issues', 0);
    }

    // ---------------- production output ----------------

    public function test_recording_output_increases_stock_and_the_orders_produced_quantity(): void
    {
        $order = $this->makeOrder('in_progress');

        $this->actingAs($this->actingUser())->postJson('/api/v1/production/outputs', [
            'production_order_id' => $order->id,
            'item_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 4,
            'output_date' => now()->toDateString(),
        ])->assertCreated();

        $this->assertEquals(4, StockLevel::first()->quantity);
        $this->assertEquals(4, $order->fresh()->quantity_produced);
        $this->assertDatabaseHas('stock_movements', ['type' => 'in', 'quantity' => 4, 'reference_type' => 'ProductionOutput']);
    }

    public function test_output_cannot_be_recorded_against_a_completed_order(): void
    {
        $order = $this->makeOrder('completed');

        $this->actingAs($this->actingUser())->postJson('/api/v1/production/outputs', [
            'production_order_id' => $order->id,
            'item_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 1,
            'output_date' => now()->toDateString(),
        ])->assertStatus(422);
    }
}
