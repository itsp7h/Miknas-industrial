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
     * which PurchaseSignatureController and RfqController advance the stage.
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
        $storeResponse = $this->actingAs($requester)->post(route('purchase.requests.store'), [
            'date' => now()->format('Y-m-d'),
            'project_name' => 'Test Project',
            'requested_by_name' => 'Test Person',
            'items' => [
                ['description' => 'Widget', 'quantity_required' => 5],
            ],
        ]);
        $storeResponse->assertRedirect(route('purchase.requests.index'));

        $purchaseRequest = PurchaseRequest::where('requested_by_name', 'Test Person')->firstOrFail();
        $this->assertSame('draft', $purchaseRequest->stage);

        // 2) Purchase Manager signs (GM signature) while the request is still
        // at draft — this is the exact precondition approve() must allow.
        $signResponse = $this->actingAs($purchaseManager)->post(
            route('purchase.requests.sign.store', $purchaseRequest),
            ['signature_image' => 'data:image/png;base64,iVBORw0KGgo=']
        );
        $signResponse->assertRedirect(route('purchase.pipeline.show', $purchaseRequest));
        $this->assertFalse($signResponse->isForbidden());

        $purchaseRequest->refresh();
        $this->assertSame('gm_approval', $purchaseRequest->stage);

        // 3) Procurement Officer selects suppliers while the request sits at
        // gm_approval — the exact precondition manageRfq() must allow. This
        // action itself advances the stage to 'rfq'.
        $selectResponse = $this->actingAs($procurementOfficer)->post(
            route('purchase.requests.rfq.select', $purchaseRequest),
            ['supplier_ids' => [$supplier->id]]
        );
        $this->assertFalse($selectResponse->isForbidden());
        $selectResponse->assertRedirect(route('purchase.pipeline.show', $purchaseRequest));

        $purchaseRequest->refresh();
        $this->assertSame('rfq', $purchaseRequest->stage);

        // 4) Procurement Officer sends the RFQ to the selected supplier(s) —
        // manageRfq() must still allow this now that the stage is 'rfq'.
        $sendResponse = $this->actingAs($procurementOfficer)->post(
            route('purchase.requests.rfq.send-all', $purchaseRequest)
        );
        $this->assertFalse($sendResponse->isForbidden());
        $sendResponse->assertRedirect(route('purchase.pipeline.show', $purchaseRequest));

        $purchaseRequest->refresh();
        $this->assertSame('quoting', $purchaseRequest->stage);
    }
}
