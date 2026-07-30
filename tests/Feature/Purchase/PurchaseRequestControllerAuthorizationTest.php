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

    public function test_user_without_create_permission_cannot_store_a_request(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('purchase.requests.store'), $this->validPayload())
            ->assertForbidden();
    }

    public function test_requester_can_store_a_request(): void
    {
        $requester = User::factory()->create();
        $requester->assignRole('Requester');

        $this->actingAs($requester)
            ->post(route('purchase.requests.store'), $this->validPayload())
            ->assertRedirect(route('purchase.requests.index'));

        $this->assertDatabaseHas('purchase_requests', ['requested_by_name' => 'Test Person']);
    }

    public function test_requester_cannot_update_someone_elses_draft_request(): void
    {
        $requester = User::factory()->create();
        $requester->assignRole('Requester');
        $othersRequest = PurchaseRequest::factory()->create(['stage' => 'draft']);

        $this->actingAs($requester)
            ->put(route('purchase.requests.update', $othersRequest), $this->validPayload())
            ->assertForbidden();
    }

    public function test_requester_cannot_update_own_request_past_draft_stage(): void
    {
        $requester = User::factory()->create();
        $requester->assignRole('Requester');
        $ownRequest = PurchaseRequest::factory()->create(['requested_by' => $requester->id, 'stage' => 'rfq']);

        $this->actingAs($requester)
            ->put(route('purchase.requests.update', $ownRequest), $this->validPayload())
            ->assertForbidden();
    }

    public function test_user_without_approve_permission_cannot_approve(): void
    {
        $user = User::factory()->create();
        $atStage = PurchaseRequest::factory()->create(['stage' => 'gm_approval']);

        $this->actingAs($user)
            ->patch(route('purchase.requests.approve', $atStage))
            ->assertForbidden();
    }

    public function test_purchase_manager_can_approve_at_gm_approval_stage(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('Purchase Manager');
        $atStage = PurchaseRequest::factory()->create(['stage' => 'gm_approval']);

        $this->actingAs($manager)
            ->patch(route('purchase.requests.approve', $atStage))
            ->assertRedirect();

        $this->assertSame('approved', $atStage->fresh()->status);
    }
}
