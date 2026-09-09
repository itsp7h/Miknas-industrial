<?php

namespace Tests\Feature\Api\Purchase;

use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchasePipelineControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/purchase/pipeline');

        $response->assertUnauthorized();
    }

    public function test_index_returns_requests_the_user_can_view(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('purchase-requests.view-all');
        $this->actingAs($user);
        PurchaseRequest::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/purchase/pipeline');

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    public function test_index_filters_to_own_requests_for_view_own_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('purchase-requests.view-own');
        $this->actingAs($user);
        PurchaseRequest::factory()->create(['requested_by' => $user->id]);
        PurchaseRequest::factory()->create(['requested_by' => User::factory()->create()->id]);

        $response = $this->getJson('/api/v1/purchase/pipeline');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_index_filters_to_active_pipeline_stages_for_view_active_pipeline_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('purchase-requests.view-active-pipeline');
        $this->actingAs($user);
        PurchaseRequest::factory()->create(['stage' => 'draft']);
        PurchaseRequest::factory()->create(['stage' => 'rfq']);
        PurchaseRequest::factory()->create(['stage' => 'complete']);

        $response = $this->getJson('/api/v1/purchase/pipeline');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_index_returns_nothing_without_any_purchase_request_permission(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        PurchaseRequest::factory()->create();

        $response = $this->getJson('/api/v1/purchase/pipeline');

        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
    }

    /**
     * Ported from the deleted Blade detail page: opening one request is gated by
     * the view policy, not just the board's own scoping.
     */
    public function test_show_is_refused_without_permission_to_view_that_request(): void
    {
        $pr = PurchaseRequest::factory()->create();

        $this->getJson("/api/v1/purchase/pipeline/{$pr->id}")->assertUnauthorized();

        $this->actingAs(User::factory()->create())
            ->getJson("/api/v1/purchase/pipeline/{$pr->id}")->assertForbidden();
    }

    public function test_a_requester_can_open_their_own_request(): void
    {
        $requester = User::factory()->create();
        $requester->assignRole('Requester');
        $pr = PurchaseRequest::factory()->create(['requested_by' => $requester->id]);

        $this->actingAs($requester)
            ->getJson("/api/v1/purchase/pipeline/{$pr->id}")->assertOk()
            ->assertJsonPath('data.id', $pr->id);
    }

    public function test_index_serializes_date_as_a_plain_y_m_d_string(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('purchase-requests.view-all');
        $this->actingAs($user);
        PurchaseRequest::factory()->create(['date' => '2026-08-02']);

        $response = $this->getJson('/api/v1/purchase/pipeline');

        $response->assertOk();
        $this->assertSame('2026-08-02', $response->json('data.0.date'));
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $response->json('data.0.date'));
    }
}
