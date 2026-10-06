<?php

namespace Tests\Feature\Api\Settings;

use App\Models\GoodsReceiptNote;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\Settings\Company;
use App\Models\Settings\Location;
use App\Models\Settings\ProjectSetting;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectControllerTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::create(['name' => 'Miknas Industrial', 'is_active' => true]);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        return $admin;
    }

    private function project(string $name = 'New Warehouse', bool $active = true): ProjectSetting
    {
        return ProjectSetting::create([
            'name' => $name, 'company_id' => $this->company->id, 'is_active' => $active,
        ]);
    }

    public function test_the_endpoints_require_an_admin(): void
    {
        $this->getJson('/api/v1/settings/projects')->assertUnauthorized();

        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/settings/projects')->assertForbidden();
        $this->actingAs(User::factory()->create())
            ->postJson('/api/v1/settings/projects', ['name' => 'Sneaky'])->assertForbidden();
    }

    public function test_it_lists_projects_with_locations_companies_and_the_four_stats(): void
    {
        $warehouse = $this->project('New Warehouse');
        $this->project('Factory Extension', false);
        $warehouse->locations()->create(['name' => 'Yard', 'is_active' => true, 'latitude' => 25.2048, 'longitude' => 55.2708]);
        $warehouse->locations()->create(['name' => 'Gate 3', 'is_active' => true]);

        $response = $this->actingAs($this->admin())
            ->getJson('/api/v1/settings/projects')->assertOk();

        $this->assertSame(['Factory Extension', 'New Warehouse'], array_column($response->json('data'), 'name'));
        $this->assertSame('Miknas Industrial', $response->json('data.1.company_name'));
        // Locations arrive alphabetically, with numeric coordinates.
        $this->assertSame(['Gate 3', 'Yard'], array_column($response->json('data.1.locations'), 'name'));
        $this->assertSame(25.2048, $response->json('data.1.locations.1.latitude'));
        $this->assertNull($response->json('data.1.locations.0.latitude'));
        // The company picker travels with the list.
        $this->assertSame(['Miknas Industrial'], array_column($response->json('companies'), 'name'));

        $this->assertSame(2, $response->json('meta.total_projects'));
        $this->assertSame(1, $response->json('meta.active_projects'));
        $this->assertSame(2, $response->json('meta.total_locations'));
        $this->assertSame(1, $response->json('meta.total_companies'));
    }

    public function test_it_creates_a_project_with_or_without_a_company(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->postJson('/api/v1/settings/projects', [
            'name' => 'New Warehouse', 'company_id' => $this->company->id,
        ])->assertCreated()->assertJsonPath('data.company_name', 'Miknas Industrial');

        $this->actingAs($admin)->postJson('/api/v1/settings/projects', ['name' => 'Unassigned Job'])
            ->assertCreated()->assertJsonPath('data.company_id', null);
    }

    public function test_project_names_are_unique(): void
    {
        $this->project();

        $this->actingAs($this->admin())
            ->postJson('/api/v1/settings/projects', ['name' => 'New Warehouse'])
            ->assertStatus(422)->assertJsonValidationErrors(['name']);
    }

    /**
     * The Blade controller fell back to the project's current company whenever
     * company_id was absent or null, so a project could never be moved back to
     * "no company" once it had one.
     */
    public function test_a_project_can_be_moved_back_to_no_company(): void
    {
        $project = $this->project();

        $this->actingAs($this->admin())
            ->putJson("/api/v1/settings/projects/{$project->id}", [
                'name' => 'New Warehouse', 'company_id' => null, 'is_active' => true,
            ])->assertOk()->assertJsonPath('data.company_id', null);

        $this->assertNull($project->fresh()->company_id);
    }

    /**
     * settings_locations.project_id is `on delete set null`, so the Blade page's
     * delete left the locations behind with no project — unreachable from
     * anywhere. They belong to the project, so they go with it.
     */
    public function test_deleting_a_project_takes_its_locations_with_it(): void
    {
        $project = $this->project();
        $project->locations()->create(['name' => 'Yard', 'is_active' => true]);

        $this->actingAs($this->admin())
            ->deleteJson("/api/v1/settings/projects/{$project->id}")->assertOk();

        $this->assertDatabaseCount('settings_projects', 0);
        $this->assertDatabaseCount('settings_locations', 0);
    }

    public function test_location_writes_return_the_whole_project(): void
    {
        $project = $this->project();
        $admin = $this->admin();

        $created = $this->actingAs($admin)
            ->postJson("/api/v1/settings/projects/{$project->id}/locations", [
                'name' => 'Yard', 'address' => 'Industrial Area 4', 'latitude' => 25.2048, 'longitude' => 55.2708,
            ])->assertCreated();

        $this->assertSame('New Warehouse', $created->json('data.name'));
        $this->assertSame('Industrial Area 4', $created->json('data.locations.0.address'));

        $locationId = $created->json('data.locations.0.id');

        $this->actingAs($admin)
            ->putJson("/api/v1/settings/projects/{$project->id}/locations/{$locationId}", [
                'name' => 'Main Yard', 'is_active' => false,
            ])->assertOk()
            ->assertJsonPath('data.locations.0.name', 'Main Yard')
            ->assertJsonPath('data.locations.0.is_active', false)
            // Clearing the address and coordinates has to actually clear them.
            ->assertJsonPath('data.locations.0.address', null)
            ->assertJsonPath('data.locations.0.latitude', null);

        $this->actingAs($admin)
            ->deleteJson("/api/v1/settings/projects/{$project->id}/locations/{$locationId}")
            ->assertOk()->assertJsonPath('data.locations', []);
    }

    public function test_a_location_keeps_its_road_block_city_and_country(): void
    {
        $project = $this->project();
        $admin = $this->admin();

        $created = $this->actingAs($admin)
            ->postJson("/api/v1/settings/projects/{$project->id}/locations", [
                'name' => 'Askar Yard', 'latitude' => 26.0667, 'longitude' => 50.5577,
                'road' => 'Road 3803', 'block' => '945', 'city' => 'Sitra', 'country' => 'Bahrain',
            ])->assertCreated()
            ->assertJsonPath('data.locations.0.road', 'Road 3803')
            ->assertJsonPath('data.locations.0.block', '945')
            ->assertJsonPath('data.locations.0.city', 'Sitra')
            ->assertJsonPath('data.locations.0.country', 'Bahrain');

        // An edit that leaves a part out clears it, as it does the address.
        $this->actingAs($admin)
            ->putJson("/api/v1/settings/projects/{$project->id}/locations/{$created->json('data.locations.0.id')}", [
                'name' => 'Askar Yard', 'city' => 'Askar', 'country' => 'Bahrain',
            ])->assertOk()
            ->assertJsonPath('data.locations.0.road', null)
            ->assertJsonPath('data.locations.0.city', 'Askar');
    }

    public function test_an_address_part_longer_than_a_column_is_rejected(): void
    {
        $project = $this->project();

        $this->actingAs($this->admin())
            ->postJson("/api/v1/settings/projects/{$project->id}/locations", [
                'name' => 'Yard', 'road' => str_repeat('x', 256),
            ])->assertStatus(422)->assertJsonValidationErrors(['road']);
    }

    public function test_coordinates_outside_the_globe_are_rejected(): void
    {
        $project = $this->project();

        $this->actingAs($this->admin())
            ->postJson("/api/v1/settings/projects/{$project->id}/locations", [
                'name' => 'Nowhere', 'latitude' => 120, 'longitude' => 400,
            ])->assertStatus(422)->assertJsonValidationErrors(['latitude', 'longitude']);
    }

    /** Both ids come from the URL, so a mismatched pair must not be writable. */
    public function test_a_location_cannot_be_edited_through_another_project(): void
    {
        $owner = $this->project('New Warehouse');
        $other = $this->project('Factory Extension');
        $location = $owner->locations()->create(['name' => 'Yard', 'is_active' => true]);

        $this->actingAs($this->admin())
            ->putJson("/api/v1/settings/projects/{$other->id}/locations/{$location->id}", ['name' => 'Hijacked'])
            ->assertNotFound();

        $this->assertSame('Yard', Location::find($location->id)->name);
    }

    public function test_the_template_downloads_as_a_spreadsheet(): void
    {
        $response = $this->actingAs($this->admin())
            ->get('/api/v1/settings/projects/template')->assertOk();

        $this->assertStringContainsString('projects_template.xlsx', $response->headers->get('content-disposition'));
    }

    public function test_the_import_endpoint_rejects_anything_that_is_not_a_spreadsheet(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/api/v1/settings/projects/import', [])
            ->assertStatus(422)->assertJsonValidationErrors(['file']);
    }

    /** A confirmed GRN with one consumable line for $project at $rate on the LPO. */
    private function chargedGrn(ProjectSetting $project, string $status = 'confirmed', float $rate = 0.006, float $unitCost = 0.01): GoodsReceiptNote
    {
        $supplier = Supplier::factory()->create(['name' => 'Gulf Supplies']);
        $item = Item::create(['item_code' => 'CN-'.uniqid(), 'item_name' => 'Silica Sand', 'category' => 'raw_material', 'unit_of_measure' => 'KG', 'cost_price' => 1]);
        $warehouse = Warehouse::create(['name' => 'Main Store', 'code' => 'WH-'.uniqid()]);
        $order = PurchaseOrder::create(['po_number' => 'LPO-'.uniqid(), 'supplier_id' => $supplier->id, 'po_date' => now(), 'total_amount' => 1, 'status' => 'sent']);
        $line = $order->items()->create(['item_id' => $item->id, 'quantity' => 1000, 'rate' => $rate, 'total_amount' => 6, 'quantity_received' => 0]);
        $grn = GoodsReceiptNote::create([
            'grn_number' => 'GRN-'.uniqid(), 'purchase_order_id' => $order->id,
            'supplier_id' => $supplier->id, 'warehouse_id' => $warehouse->id,
            'received_date' => '2026-10-06', 'status' => $status,
        ]);
        $grn->items()->create([
            'purchase_order_item_id' => $line->id, 'item_id' => $item->id, 'quantity_received' => 1000,
            'unit_cost' => $unitCost, 'type' => 'consumable', 'project_id' => $project->id,
        ]);

        return $grn;
    }

    public function test_costs_lists_the_consumables_charged_to_a_project_at_the_lpo_rate(): void
    {
        $project = $this->project('Hidd Yard');
        $grn = $this->chargedGrn($project);

        $response = $this->actingAs($this->admin())
            ->getJson("/api/v1/settings/projects/{$project->id}/costs")
            ->assertOk()
            ->assertJsonPath('data.name', 'Hidd Yard')
            ->assertJsonPath('data.company_name', 'Miknas Industrial')
            ->assertJsonCount(1, 'lines')
            ->assertJsonPath('lines.0.grn_number', $grn->grn_number)
            ->assertJsonPath('lines.0.supplier_name', 'Gulf Supplies')
            ->assertJsonPath('lines.0.item_name', 'Silica Sand')
            ->assertJsonPath('lines.0.received_date', '2026-10-06')
            ->assertJsonPath('meta.line_count', 1)
            ->assertJsonPath('meta.grn_count', 1);

        // 1000 × 0.006 from the LPO, not 1000 × 0.01 from the two-decimal GRN copy.
        $this->assertEqualsWithDelta(0.006, $response->json('lines.0.rate'), 0.0000001);
        $this->assertEqualsWithDelta(6.0, $response->json('lines.0.amount'), 0.0000001);
        $this->assertEqualsWithDelta(6.0, $response->json('meta.total'), 0.0000001);
    }

    public function test_costs_leave_out_draft_grns_and_other_projects(): void
    {
        $project = $this->project('Hidd Yard');
        $this->chargedGrn($project, 'draft');
        $this->chargedGrn($this->project('Sitra Depot'));

        $this->actingAs($this->admin())
            ->getJson("/api/v1/settings/projects/{$project->id}/costs")
            ->assertOk()
            ->assertJsonCount(0, 'lines')
            ->assertJsonPath('meta.total', 0);
    }

    public function test_costs_need_the_costs_permission_not_just_view(): void
    {
        $project = $this->project('Hidd Yard');
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('projects.view');

        $this->actingAs($viewer)->getJson("/api/v1/settings/projects/{$project->id}/costs")->assertForbidden();

        $viewer->givePermissionTo('projects.costs');
        $this->actingAs($viewer->fresh())->getJson("/api/v1/settings/projects/{$project->id}/costs")->assertOk();
    }
}
