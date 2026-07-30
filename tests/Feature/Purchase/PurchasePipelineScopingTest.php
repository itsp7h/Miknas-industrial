<?php

namespace Tests\Feature\Purchase;

use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchasePipelineScopingTest extends TestCase
{
    use RefreshDatabase;

    public function test_requester_sees_only_their_own_requests(): void
    {
        $requester = User::factory()->create();
        $requester->assignRole('Requester');
        $own   = PurchaseRequest::factory()->create(['requested_by' => $requester->id]);
        $other = PurchaseRequest::factory()->create();

        $response = $this->actingAs($requester)->get(route('purchase.pipeline.index'));

        $response->assertOk();
        $response->assertSee($own->request_number);
        $response->assertDontSee($other->request_number);
    }

    public function test_procurement_officer_sees_only_rfq_stage_or_later(): void
    {
        $procurement = User::factory()->create();
        $procurement->assignRole('Procurement Officer');
        $draft = PurchaseRequest::factory()->create(['stage' => 'draft']);
        $atRfq = PurchaseRequest::factory()->create(['stage' => 'rfq']);

        $response = $this->actingAs($procurement)->get(route('purchase.pipeline.index'));

        $response->assertOk();
        $response->assertDontSee($draft->request_number);
        $response->assertSee($atRfq->request_number);
    }

    public function test_purchase_manager_sees_all_requests(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('Purchase Manager');
        $a = PurchaseRequest::factory()->create(['stage' => 'draft']);
        $b = PurchaseRequest::factory()->create(['stage' => 'lpo']);

        $response = $this->actingAs($manager)->get(route('purchase.pipeline.index'));

        $response->assertOk();
        $response->assertSee($a->request_number);
        $response->assertSee($b->request_number);
    }

    public function test_user_without_view_permission_cannot_open_a_single_request(): void
    {
        $user = User::factory()->create();
        $pr = PurchaseRequest::factory()->create();

        $this->actingAs($user)->get(route('purchase.pipeline.show', $pr))->assertForbidden();
    }

    public function test_requester_can_open_their_own_request(): void
    {
        $requester = User::factory()->create();
        $requester->assignRole('Requester');
        $pr = PurchaseRequest::factory()->create(['requested_by' => $requester->id]);

        $this->actingAs($requester)->get(route('purchase.pipeline.show', $pr))->assertOk();
    }
}
