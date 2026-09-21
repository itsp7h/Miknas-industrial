<?php

namespace Tests\Feature\Api\Purchase;

use App\Events\PurchaseRequestCreated;
use App\Events\PurchaseRequestUpdated;
use App\Models\PurchaseRequest;
use App\Models\Settings\Company;
use App\Models\Settings\Department;
use App\Models\Settings\Location;
use App\Models\Settings\ProjectSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * The MPR create and edit forms, which were a pair of Blade modal components
 * until they became one React modal talking to this API.
 */
class RequestFormTest extends TestCase
{
    use RefreshDatabase;

    private function requester(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['pipeline.create', 'pipeline.edit', 'pipeline.view-own']);

        return $user;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'date' => '2026-09-01',
            'company_name' => 'Plant Expansion',
            'requested_by_name' => 'Aisha Rahman',
            'department' => 'Operations',
            'required_date_text' => '1 Week',
            'location' => 'Bay 4',
            'remarks' => 'Needed before the shutdown.',
            'items' => [
                ['description' => 'Steel Plate 10mm', 'unit' => 'KG', 'quantity_required' => 500, 'purpose_use' => 'Frame', 'required_date' => '2026-09-10'],
                ['description' => 'Galvanised Bolt M12', 'unit' => 'PCS', 'quantity_required' => 2000],
            ],
        ], $overrides);
    }

    // ── Form options ────────────────────────────────────────────────────────

    public function test_form_options_are_closed_to_users_who_can_neither_create_nor_edit(): void
    {
        $this->getJson('/api/v1/purchase/requests/form-options')->assertUnauthorized();

        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/purchase/requests/form-options')
            ->assertForbidden();
    }

    /**
     * Requested By was a free-text box, so the same person was recorded three
     * different ways. The form picks from the system's users instead.
     */
    public function test_form_options_carry_the_users_a_request_can_be_raised_for(): void
    {
        User::factory()->create(['name' => 'Zainab Ali']);
        User::factory()->create(['name' => 'Ahmed Khan']);

        $names = $this->actingAs($this->requester())
            ->getJson('/api/v1/purchase/requests/form-options')
            ->assertOk()
            ->json('requesters');

        $this->assertContains('Zainab Ali', $names);
        $this->assertContains('Ahmed Khan', $names);
        // Sorted, so the dropdown reads the way the settings list does.
        $sorted = $names;
        sort($sorted);
        $this->assertSame($sorted, $names);
    }

    public function test_form_options_carry_projects_with_their_company_and_locations(): void
    {
        $company = Company::create(['name' => 'Miknas Steel', 'is_active' => true]);
        $project = ProjectSetting::create(['name' => 'Plant Expansion', 'company_id' => $company->id, 'is_active' => true]);
        Location::create(['name' => 'Bay 4', 'project_id' => $project->id, 'is_active' => true]);
        Location::create(['name' => 'Retired Bay', 'project_id' => $project->id, 'is_active' => false]);
        Department::create(['name' => 'Operations', 'company_id' => $company->id, 'is_active' => true]);

        $response = $this->actingAs($this->requester())
            ->getJson('/api/v1/purchase/requests/form-options')
            ->assertOk();

        // The form names a company, and each one carries the locations that
        // sit under its own projects.
        $response->assertJsonPath('companies.0.name', 'Miknas Steel');
        // Only active locations are offered.
        $this->assertSame(['Bay 4'], $response->json('companies.0.locations'));

        $response->assertJsonPath('projects.0.name', 'Plant Expansion');
        $response->assertJsonPath('projects.0.company_name', 'Miknas Steel');
        // The option label Blade rendered: "Company — Project".
        $response->assertJsonPath('projects.0.label', 'Miknas Steel — Plant Expansion');
        $this->assertSame(['Bay 4'], $response->json('projects.0.locations'));
        $response->assertJsonPath('departments.0.name', 'Operations');
        $this->assertContains('KG', $response->json('units'));
    }

    /** An inactive company is not offered, the way an inactive project is not. */
    /** A request records both: the company, and the project within it. */
    public function test_it_stores_the_company_and_the_project(): void
    {
        $response = $this->actingAs($this->requester())
            ->postJson('/api/v1/purchase/requests', $this->payload([
                'company_name' => 'Miknas Industrial',
                'project_name' => 'Forkoll',
            ]))
            ->assertCreated();

        $pr = PurchaseRequest::latest('id')->first();
        $this->assertSame('Miknas Industrial', $pr->company_name);
        $this->assertSame('Forkoll', $pr->project_name);
        $response->assertJsonPath('data.company_name', 'Miknas Industrial');
        $response->assertJsonPath('data.project_name', 'Forkoll');
    }

    /** A request always has a company; it does not always have a project. */
    public function test_the_project_is_optional_but_the_company_is_not(): void
    {
        $this->actingAs($this->requester())
            ->postJson('/api/v1/purchase/requests', $this->payload(['project_name' => null]))
            ->assertCreated();

        $this->assertNull(PurchaseRequest::latest('id')->first()->project_name);

        $this->actingAs($this->requester())
            ->postJson('/api/v1/purchase/requests', $this->payload(['company_name' => '']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('company_name');
    }

    /** The MPR names a company, and the company record is found by that name. */
    public function test_a_request_resolves_its_company_by_name(): void
    {
        $company = Company::create(['name' => 'Miknas Steel', 'is_active' => true]);
        $pr = PurchaseRequest::factory()->create(['company_name' => 'Miknas Steel']);

        $this->assertSame($company->id, $pr->resolveCompany()?->id);
    }

    /**
     * Requests raised before the form named companies hold a project name.
     * Their LPO letterhead must not go blank because the field changed.
     */
    public function test_a_request_naming_an_old_project_still_finds_its_company(): void
    {
        $company = Company::create(['name' => 'Miknas Industrial', 'is_active' => true]);
        ProjectSetting::create(['name' => 'Forkoll', 'company_id' => $company->id, 'is_active' => true]);
        $pr = PurchaseRequest::factory()->create(['company_name' => 'Forkoll']);

        $this->assertSame('Miknas Industrial', $pr->resolveCompany()?->name);
    }

    /** A name matching neither is simply no company, not an error. */
    public function test_a_request_naming_nothing_known_resolves_to_no_company(): void
    {
        $pr = PurchaseRequest::factory()->create(['company_name' => 'Gone Ltd']);

        $this->assertNull($pr->resolveCompany());
    }

    public function test_only_active_companies_are_offered(): void
    {
        Company::create(['name' => 'Miknas Steel', 'is_active' => true]);
        Company::create(['name' => 'Wound Up Ltd', 'is_active' => false]);

        $response = $this->actingAs($this->requester())
            ->getJson('/api/v1/purchase/requests/form-options')
            ->assertOk();

        $this->assertSame(['Miknas Steel'], array_column($response->json('companies'), 'name'));
    }

    /** A company gathers the locations of every project beneath it, once each. */
    public function test_a_companys_locations_come_from_all_of_its_projects(): void
    {
        $company = Company::create(['name' => 'Miknas Steel', 'is_active' => true]);
        $one = ProjectSetting::create(['name' => 'Plant', 'company_id' => $company->id, 'is_active' => true]);
        $two = ProjectSetting::create(['name' => 'Depot', 'company_id' => $company->id, 'is_active' => true]);
        Location::create(['name' => 'Yard', 'project_id' => $one->id, 'is_active' => true]);
        Location::create(['name' => 'Bay 4', 'project_id' => $two->id, 'is_active' => true]);
        // The same site named under two projects is one option, not two.
        Location::create(['name' => 'Yard', 'project_id' => $two->id, 'is_active' => true]);

        $response = $this->actingAs($this->requester())
            ->getJson('/api/v1/purchase/requests/form-options')
            ->assertOk();

        $this->assertSame(['Bay 4', 'Yard'], $response->json('companies.0.locations'));
    }

    public function test_a_project_without_a_company_is_still_offered(): void
    {
        // The Blade create modal filtered these out while the edit modal kept
        // them, so a project could be editable but not selectable when new.
        ProjectSetting::create(['name' => 'Unassigned Yard', 'company_id' => null, 'is_active' => true]);

        $this->actingAs($this->requester())
            ->getJson('/api/v1/purchase/requests/form-options')
            ->assertOk()
            ->assertJsonPath('projects.0.name', 'Unassigned Yard')
            ->assertJsonPath('projects.0.label', 'Unassigned Yard');
    }

    public function test_inactive_projects_are_not_offered(): void
    {
        ProjectSetting::create(['name' => 'Closed Site', 'is_active' => false]);

        $this->actingAs($this->requester())
            ->getJson('/api/v1/purchase/requests/form-options')
            ->assertOk()
            ->assertJsonCount(0, 'projects');
    }

    // ── Create ──────────────────────────────────────────────────────────────

    public function test_creating_a_request_is_closed_to_users_without_the_permission(): void
    {
        $this->postJson('/api/v1/purchase/requests', $this->payload())->assertUnauthorized();

        $this->actingAs(User::factory()->create())
            ->postJson('/api/v1/purchase/requests', $this->payload())
            ->assertForbidden();
    }

    public function test_a_requester_creates_a_request_with_its_items(): void
    {
        $requester = $this->requester();

        $response = $this->actingAs($requester)
            ->postJson('/api/v1/purchase/requests', $this->payload())
            ->assertCreated();

        $pr = PurchaseRequest::firstOrFail();
        $this->assertSame('Plant Expansion', $pr->company_name);
        $this->assertSame('pending', $pr->status);
        $this->assertSame($requester->id, $pr->requested_by);
        $this->assertSame('1 Week', $pr->required_date_text);
        $this->assertSame('Bay 4', $pr->location);
        $this->assertCount(2, $pr->items);
        $this->assertSame('KG', $pr->items->first()->unit);

        // The board opens this form, so it answers with a board row.
        $response->assertJsonPath('data.request_number', $pr->request_number);
        $response->assertJsonPath('data.company_name', 'Plant Expansion');
        $this->assertStringContainsString('submitted successfully', $response->json('message'));
    }

    public function test_a_created_request_is_numbered_mp_r_year_sequence(): void
    {
        $this->actingAs($this->requester())
            ->postJson('/api/v1/purchase/requests', $this->payload())
            ->assertCreated();

        $this->assertMatchesRegularExpression(
            '/^MPR\d{2}-\d{4}$/',
            PurchaseRequest::firstOrFail()->request_number
        );
    }

    public function test_creating_a_request_broadcasts_it_to_the_boards(): void
    {
        Event::fake([PurchaseRequestCreated::class]);

        $this->actingAs($this->requester())
            ->postJson('/api/v1/purchase/requests', $this->payload())
            ->assertCreated();

        Event::assertDispatched(PurchaseRequestCreated::class);
    }

    public function test_a_request_needs_a_project_a_requester_and_at_least_one_item(): void
    {
        $requester = $this->requester();

        $this->actingAs($requester)
            ->postJson('/api/v1/purchase/requests', $this->payload(['company_name' => '']))
            ->assertJsonValidationErrors('company_name');

        $this->actingAs($requester)
            ->postJson('/api/v1/purchase/requests', $this->payload(['requested_by_name' => '']))
            ->assertJsonValidationErrors('requested_by_name');

        $this->actingAs($requester)
            ->postJson('/api/v1/purchase/requests', $this->payload(['items' => []]))
            ->assertJsonValidationErrors('items');
    }

    public function test_an_item_needs_a_description_and_a_positive_quantity(): void
    {
        $requester = $this->requester();

        $this->actingAs($requester)
            ->postJson('/api/v1/purchase/requests', $this->payload([
                'items' => [['description' => '', 'quantity_required' => 5]],
            ]))
            ->assertJsonValidationErrors('items.0.description');

        $this->actingAs($requester)
            ->postJson('/api/v1/purchase/requests', $this->payload([
                'items' => [['description' => 'Widget', 'quantity_required' => 0]],
            ]))
            ->assertJsonValidationErrors('items.0.quantity_required');
    }

    // ── Edit ────────────────────────────────────────────────────────────────

    public function test_the_edit_payload_is_closed_to_anyone_who_cannot_update_the_request(): void
    {
        $requester = $this->requester();
        $others = PurchaseRequest::factory()->create(['stage' => 'draft']);

        $this->getJson("/api/v1/purchase/requests/{$others->id}/edit")->assertUnauthorized();

        $this->actingAs($requester)
            ->getJson("/api/v1/purchase/requests/{$others->id}/edit")
            ->assertForbidden();
    }

    public function test_the_edit_payload_carries_the_editable_fields_and_item_rows(): void
    {
        $requester = $this->requester();
        $pr = PurchaseRequest::factory()->create([
            'requested_by' => $requester->id, 'stage' => 'draft',
            'company_name' => 'Plant Expansion', 'location' => 'Bay 4',
            'department' => 'Operations', 'required_date_text' => 'Urgent',
            'remarks' => 'Before the shutdown.',
        ]);
        $pr->items()->create([
            'description' => 'Steel Plate 10mm', 'unit' => 'KG',
            'quantity_required' => 500, 'purpose_use' => 'Frame', 'required_date' => '2026-09-10',
        ]);

        $response = $this->actingAs($requester)
            ->getJson("/api/v1/purchase/requests/{$pr->id}/edit")
            ->assertOk();

        $response->assertJsonPath('data.company_name', 'Plant Expansion');
        $response->assertJsonPath('data.location', 'Bay 4');
        $response->assertJsonPath('data.required_date_text', 'Urgent');
        $response->assertJsonPath('data.remarks', 'Before the shutdown.');
        // The pipeline detail payload shapes items for the timeline, so the
        // editable columns have to come from here.
        $response->assertJsonPath('data.items.0.description', 'Steel Plate 10mm');
        $response->assertJsonPath('data.items.0.unit', 'KG');
        $response->assertJsonPath('data.items.0.purpose_use', 'Frame');
        $response->assertJsonPath('data.items.0.required_date', '2026-09-10');
    }

    public function test_a_requester_cannot_update_someone_elses_request(): void
    {
        $others = PurchaseRequest::factory()->create(['stage' => 'draft']);

        $this->actingAs($this->requester())
            ->putJson("/api/v1/purchase/requests/{$others->id}", $this->payload())
            ->assertForbidden();
    }

    public function test_a_request_past_the_draft_stage_can_no_longer_be_edited(): void
    {
        $requester = $this->requester();
        $pr = PurchaseRequest::factory()->create(['requested_by' => $requester->id, 'stage' => 'rfq']);

        $this->actingAs($requester)
            ->putJson("/api/v1/purchase/requests/{$pr->id}", $this->payload())
            ->assertForbidden();
    }

    public function test_updating_a_request_replaces_its_fields_and_item_rows(): void
    {
        $requester = $this->requester();
        $pr = PurchaseRequest::factory()->create(['requested_by' => $requester->id, 'stage' => 'draft']);
        $stale = $pr->items()->create(['description' => 'Dropped line', 'quantity_required' => 1]);

        $response = $this->actingAs($requester)
            ->putJson("/api/v1/purchase/requests/{$pr->id}", $this->payload())
            ->assertOk();

        $pr->refresh();
        $this->assertSame('Plant Expansion', $pr->company_name);
        $this->assertSame('Aisha Rahman', $pr->requested_by_name);
        // Rows are replaced wholesale, as the Blade form did.
        $this->assertDatabaseMissing('purchase_request_items', ['id' => $stale->id]);
        $this->assertCount(2, $pr->items);

        // Editing is reached from the pipeline detail page, so it answers with
        // that page's whole payload.
        $response->assertJsonPath('data.request_number', $pr->request_number);
        $response->assertJsonPath('data.stage', 'draft');
        $this->assertIsArray($response->json('data.stages'));
        $this->assertStringContainsString('updated successfully', $response->json('message'));
    }

    public function test_updating_a_request_broadcasts_the_new_board_fields(): void
    {
        Event::fake([PurchaseRequestUpdated::class]);
        $requester = $this->requester();
        $pr = PurchaseRequest::factory()->create(['requested_by' => $requester->id, 'stage' => 'draft']);

        $this->actingAs($requester)
            ->putJson("/api/v1/purchase/requests/{$pr->id}", $this->payload())
            ->assertOk();

        Event::assertDispatched(function (PurchaseRequestUpdated $event) use ($pr) {
            return $event->purchaseRequestId === $pr->id
                && $event->broadcastWith()['company_name'] === 'Plant Expansion';
        });
    }
}
