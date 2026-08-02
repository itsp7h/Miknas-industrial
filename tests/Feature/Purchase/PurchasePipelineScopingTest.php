<?php

namespace Tests\Feature\Purchase;

use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchasePipelineScopingTest extends TestCase
{
    use RefreshDatabase;

    // The old Blade index() action has been removed — the Pipeline Board is now the
    // React page at /app/purchase/pipeline (see resources/js-app/pages/*/purchase/
    // PipelineBoardPage.jsx), and its permission-scoping is covered by
    // tests/Feature/Api/Purchase/PurchasePipelineControllerTest.php. This route is now
    // just a redirect safety net for anyone with the old URL bookmarked.
    public function test_pipeline_index_redirects_to_the_react_board(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('purchase.pipeline.index'));

        $response->assertRedirect('/app/purchase/pipeline');
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
