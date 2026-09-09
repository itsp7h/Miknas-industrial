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
        $user->assignRole('Requester');

        return $user;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'date' => '2026-09-01',
            'project_name' => 'Plant Expansion',
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

        $response->assertJsonPath('projects.0.name', 'Plant Expansion');
        $response->assertJsonPath('projects.0.company_name', 'Miknas Steel');
        // The option label Blade rendered: "Company — Project".
        $response->assertJsonPath('projects.0.label', 'Miknas Steel — Plant Expansion');
        // Only active locations are offered.
        $this->assertSame(['Bay 4'], $response->json('projects.0.locations'));
        $response->assertJsonPath('departments.0.name', 'Operations');
        $this->assertContains('KG', $response->json('units'));
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
        $this->assertSame('Plant Expansion', $pr->project_name);
        $this->assertSame('pending', $pr->status);
        $this->assertSame($requester->id, $pr->requested_by);
        $this->assertSame('1 Week', $pr->required_date_text);
        $this->assertSame('Bay 4', $pr->location);
        $this->assertCount(2, $pr->items);
        $this->assertSame('KG', $pr->items->first()->unit);

        // The board opens this form, so it answers with a board row.
        $response->assertJsonPath('data.request_number', $pr->request_number);
        $response->assertJsonPath('data.project_name', 'Plant Expansion');
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
            ->postJson('/api/v1/purchase/requests', $this->payload(['project_name' => '']))
            ->assertJsonValidationErrors('project_name');

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
            'project_name' => 'Plant Expansion', 'location' => 'Bay 4',
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

        $response->assertJsonPath('data.project_name', 'Plant Expansion');
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
        $this->assertSame('Plant Expansion', $pr->project_name);
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
                && $event->broadcastWith()['project_name'] === 'Plant Expansion';
        });
    }
}
