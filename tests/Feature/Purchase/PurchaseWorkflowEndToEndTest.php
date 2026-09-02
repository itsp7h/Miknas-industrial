<?php

namespace Tests\Feature\Purchase;

use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseWorkflowEndToEndTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Walks a single purchase request through the real stage-gated flow as the
     * three profile users would actually hit it in production — never by
     * pre-positioning the `stage` column via factory. This is the test that
     * should have caught the draft/gm_approval/rfq stage-gate mismatch between
     * PurchaseRequestPolicy::approve()/manageRfq() and the actual order in
     * which the signature and select-suppliers endpoints advance the stage.
     */
    public function test_request_moves_through_the_full_pipeline_without_403s(): void
    {
        $requester = User::factory()->create();
        $requester->assignRole('Requester');

        $purchaseManager = User::factory()->create();
        $purchaseManager->assignRole('Purchase Manager');

        $procurementOfficer = User::factory()->create();
        $procurementOfficer->assignRole('Procurement Officer');

        $supplier = Supplier::factory()->create();

        // 1) Requester creates the MPR — lands at stage=draft (DB default).
        // The create form is a React modal, so this step is the API call it makes.
        $storeResponse = $this->actingAs($requester)->postJson('/api/v1/purchase/requests', [
            'date' => now()->format('Y-m-d'),
            'project_name' => 'Test Project',
            'requested_by_name' => 'Test Person',
            'items' => [
                ['description' => 'Widget', 'quantity_required' => 5],
            ],
        ]);
        $storeResponse->assertCreated();

        $purchaseRequest = PurchaseRequest::where('requested_by_name', 'Test Person')->firstOrFail();
        $this->assertSame('draft', $purchaseRequest->stage);

        // 2) Purchase Manager signs (GM signature) while the request is still
        // at draft — this is the exact precondition approve() must allow. The
        // signature pad is a React dialog, so this is the endpoint it posts to.
        $signResponse = $this->actingAs($purchaseManager)->postJson(
            "/api/v1/purchase/pipeline/{$purchaseRequest->id}/signature",
            ['signature_image' => 'data:image/png;base64,iVBORw0KGgo=']
        );
        $signResponse->assertOk();
        $this->assertFalse($signResponse->isForbidden());

        $purchaseRequest->refresh();
        $this->assertSame('gm_approval', $purchaseRequest->stage);

        // 3) Procurement Officer selects suppliers while the request sits at
        // gm_approval — the exact precondition manageRfq() must allow. This
        // action itself advances the stage to 'rfq'.
        $selectResponse = $this->actingAs($procurementOfficer)->postJson(
            "/api/v1/purchase/pipeline/{$purchaseRequest->id}/suppliers",
            ['mode' => 'global', 'supplier_ids' => [$supplier->id]]
        );
        $this->assertFalse($selectResponse->isForbidden());
        $selectResponse->assertOk();

        $purchaseRequest->refresh();
        $this->assertSame('rfq', $purchaseRequest->stage);

        // 4) Procurement Officer sends the RFQ to the selected supplier(s) —
        // manageRfq() must still allow this now that the stage is 'rfq'.
        $sendResponse = $this->actingAs($procurementOfficer)->postJson(
            "/api/v1/purchase/pipeline/{$purchaseRequest->id}/send-invitations"
        );
        $this->assertFalse($sendResponse->isForbidden());
        $sendResponse->assertOk();

        $purchaseRequest->refresh();
        $this->assertSame('quoting', $purchaseRequest->stage);
    }
}
