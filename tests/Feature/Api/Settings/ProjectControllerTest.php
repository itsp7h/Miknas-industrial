<?php

namespace Tests\Feature\Api\Settings;

use App\Models\Settings\Company;
use App\Models\Settings\Location;
use App\Models\Settings\ProjectSetting;
use App\Models\User;
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
}
