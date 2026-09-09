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

    /**
     * The request detail page is React now too, so this URL is a redirect and
     * the per-request authorization it used to enforce lives on the API's show
     * endpoint — see PurchasePipelineControllerTest, where both cases moved.
     */
    public function test_pipeline_show_redirects_to_the_react_detail_page(): void
    {
        $user = User::factory()->create();
        $pr = PurchaseRequest::factory()->create();

        $this->actingAs($user)->get(route('purchase.pipeline.show', $pr))
            ->assertRedirect('/app/purchase/pipeline/'.$pr->id);
    }
}
