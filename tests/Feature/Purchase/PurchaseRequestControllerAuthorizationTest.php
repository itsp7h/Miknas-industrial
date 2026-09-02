<?php

namespace Tests\Feature\Purchase;

use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseRequestControllerAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(): array
    {
        return [
            'date' => now()->format('Y-m-d'),
            'project_name' => 'Test Project',
            'requested_by_name' => 'Test Person',
            'items' => [
                ['description' => 'Widget', 'quantity_required' => 5],
            ],
        ];
    }

    // The create and edit forms are a React modal now, so their writes are
    // asserted against the API rather than the deleted Blade routes.

    public function test_user_without_create_permission_cannot_store_a_request(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/purchase/requests', $this->validPayload())
            ->assertForbidden();
    }

    public function test_requester_can_store_a_request(): void
    {
        $requester = User::factory()->create();
        $requester->assignRole('Requester');

        $this->actingAs($requester)
            ->postJson('/api/v1/purchase/requests', $this->validPayload())
            ->assertCreated();

        $this->assertDatabaseHas('purchase_requests', ['requested_by_name' => 'Test Person']);
    }

    public function test_requester_cannot_update_someone_elses_draft_request(): void
    {
        $requester = User::factory()->create();
        $requester->assignRole('Requester');
        $othersRequest = PurchaseRequest::factory()->create(['stage' => 'draft']);

        $this->actingAs($requester)
            ->putJson("/api/v1/purchase/requests/{$othersRequest->id}", $this->validPayload())
            ->assertForbidden();
    }

    public function test_requester_cannot_update_own_request_past_draft_stage(): void
    {
        $requester = User::factory()->create();
        $requester->assignRole('Requester');
        $ownRequest = PurchaseRequest::factory()->create(['requested_by' => $requester->id, 'stage' => 'rfq']);

        $this->actingAs($requester)
            ->putJson("/api/v1/purchase/requests/{$ownRequest->id}", $this->validPayload())
            ->assertForbidden();
    }

    /**
     * Approving is the signature action now — signing records the approval as
     * it saves the signature, which is what the dialog always claimed to do.
     * The standalone approve/reject routes had lost their UI in the cutover.
     */
    public function test_user_without_approve_permission_cannot_approve(): void
    {
        $user = User::factory()->create();
        $atStage = PurchaseRequest::factory()->create(['stage' => 'gm_approval']);

        $this->actingAs($user)
            ->postJson("/api/v1/purchase/pipeline/{$atStage->id}/signature", [
                'signature_image' => 'data:image/png;base64,iVBORw0KGgo=',
            ])
            ->assertForbidden();
    }

    public function test_purchase_manager_can_approve_at_gm_approval_stage(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('Purchase Manager');
        $atStage = PurchaseRequest::factory()->create(['stage' => 'gm_approval']);

        $this->actingAs($manager)
            ->postJson("/api/v1/purchase/pipeline/{$atStage->id}/signature", [
                'signature_image' => 'data:image/png;base64,iVBORw0KGgo=',
            ])
            ->assertOk();

        $this->assertSame('approved', $atStage->fresh()->status);
    }

    public function test_purchase_manager_can_reject_at_gm_approval_stage(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('Purchase Manager');
        $atStage = PurchaseRequest::factory()->create(['stage' => 'gm_approval']);

        $this->actingAs($manager)
            ->postJson("/api/v1/purchase/pipeline/{$atStage->id}/reject")
            ->assertOk();

        $this->assertSame('rejected', $atStage->fresh()->status);
    }

    public function test_unauthorized_user_cannot_destroy_a_request(): void
    {
        $user = User::factory()->create();
        $draft = PurchaseRequest::factory()->create(['stage' => 'draft']);

        $this->actingAs($user)
            ->deleteJson("/api/v1/purchase/requests/{$draft->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('purchase_requests', ['id' => $draft->id]);
    }

    public function test_requester_cannot_destroy_someone_elses_draft_request(): void
    {
        $requester = User::factory()->create();
        $requester->assignRole('Requester');
        $othersDraft = PurchaseRequest::factory()->create(['stage' => 'draft']);

        $this->actingAs($requester)
            ->deleteJson("/api/v1/purchase/requests/{$othersDraft->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('purchase_requests', ['id' => $othersDraft->id]);
    }

    public function test_requester_can_destroy_own_draft_request(): void
    {
        $requester = User::factory()->create();
        $requester->assignRole('Requester');
        $ownDraft = PurchaseRequest::factory()->create(['requested_by' => $requester->id, 'stage' => 'draft']);

        $this->actingAs($requester)
            ->deleteJson("/api/v1/purchase/requests/{$ownDraft->id}")
            ->assertOk();

        $this->assertDatabaseMissing('purchase_requests', ['id' => $ownDraft->id]);
    }

    /**
     * The sheet and the edit form are React now, so the `view` gate is asserted
     * on the API. Print stays Blade — it is a DomPDF document.
     */
    public function test_unauthorized_user_cannot_view_show_edit_or_print(): void
    {
        $user = User::factory()->create();
        $pr = PurchaseRequest::factory()->create();

        $this->actingAs($user)->getJson("/api/v1/purchase/requests/{$pr->id}")->assertForbidden();
        $this->actingAs($user)->getJson("/api/v1/purchase/requests/{$pr->id}/edit")->assertForbidden();
        $this->actingAs($user)->get(route('purchase.requests.print', $pr))->assertForbidden();
    }

    public function test_requester_can_view_show_edit_and_print_their_own_request(): void
    {
        $requester = User::factory()->create();
        $requester->assignRole('Requester');
        $pr = PurchaseRequest::factory()->create(['requested_by' => $requester->id, 'stage' => 'draft']);

        $this->actingAs($requester)->getJson("/api/v1/purchase/requests/{$pr->id}")->assertOk();
        $this->actingAs($requester)->getJson("/api/v1/purchase/requests/{$pr->id}/edit")->assertOk();
        $this->actingAs($requester)->get(route('purchase.requests.print', $pr))->assertOk();
    }

    /**
     * All three deleted pages redirect into the shell rather than 404ing: each
     * URL was live long enough to be bookmarked.
     */
    public function test_the_deleted_request_pages_redirect_into_the_react_shell(): void
    {
        $requester = User::factory()->create();
        $requester->assignRole('Requester');
        $pr = PurchaseRequest::factory()->create(['requested_by' => $requester->id, 'stage' => 'draft']);

        $this->actingAs($requester)->get('/purchase/requests')->assertRedirect('/app/purchase/pipeline');
        $this->actingAs($requester)->get('/purchase/requests/create')
            ->assertRedirect('/app/purchase/pipeline?new=1');
        $this->actingAs($requester)->get("/purchase/requests/{$pr->id}")
            ->assertRedirect("/app/purchase/requests/{$pr->id}");
        $this->actingAs($requester)->get("/purchase/requests/{$pr->id}/edit")
            ->assertRedirect("/app/purchase/pipeline/{$pr->id}");
    }
}
