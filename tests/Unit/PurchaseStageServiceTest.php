<?php

namespace Tests\Unit;

use App\Events\PurchaseRequestStageChanged;
use App\Models\PurchaseRequest;
use App\Services\PurchaseStageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PurchaseStageServiceTest extends TestCase
{
    use RefreshDatabase;

    private PurchaseStageService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PurchaseStageService;
    }

    public function test_advance_moves_the_request_to_the_next_stage(): void
    {
        $request = PurchaseRequest::factory()->create(['stage' => 'rfq']);

        $this->service->advance($request);

        $this->assertSame('quoting', $request->fresh()->stage);
    }

    public function test_advance_is_a_no_op_on_the_final_stage(): void
    {
        $request = PurchaseRequest::factory()->create(['stage' => 'complete']);

        $this->service->advance($request);

        $this->assertSame('complete', $request->fresh()->stage);
    }

    public function test_advance_is_a_no_op_for_a_stage_outside_the_pipeline(): void
    {
        $request = PurchaseRequest::factory()->create(['stage' => 'cancelled']);

        $this->service->advance($request);

        $this->assertSame('cancelled', $request->fresh()->stage);
    }

    public function test_set_stage_rejects_a_stage_that_is_not_in_the_pipeline(): void
    {
        $request = PurchaseRequest::factory()->create(['stage' => 'draft']);

        $this->expectException(HttpException::class);

        try {
            $this->service->setStage($request, 'not_a_stage');
        } finally {
            $this->assertSame('draft', $request->fresh()->stage);
        }
    }

    public function test_set_stage_broadcasts_the_change(): void
    {
        Event::fake([PurchaseRequestStageChanged::class]);
        $request = PurchaseRequest::factory()->create(['stage' => 'draft']);

        $this->service->setStage($request, 'gm_approval');

        Event::assertDispatched(
            PurchaseRequestStageChanged::class,
            fn ($event) => $event->purchaseRequestId === $request->id
                && $event->stage === 'gm_approval'
                && $event->requestNumber === $request->request_number
        );
    }

    /**
     * The regression this guards: re-awarding an item after LPOs are issued
     * must not roll a request back from 'receiving' to 'lpo'.
     */
    public function test_set_stage_if_not_past_does_not_roll_a_request_backwards(): void
    {
        Event::fake([PurchaseRequestStageChanged::class]);
        $request = PurchaseRequest::factory()->create(['stage' => 'receiving']);

        $this->service->setStageIfNotPast($request, 'lpo');

        $this->assertSame('receiving', $request->fresh()->stage);
        Event::assertNotDispatched(PurchaseRequestStageChanged::class);
    }

    public function test_set_stage_if_not_past_moves_forward_when_the_target_is_ahead(): void
    {
        $request = PurchaseRequest::factory()->create(['stage' => 'quoting']);

        $this->service->setStageIfNotPast($request, 'lpo');

        $this->assertSame('lpo', $request->fresh()->stage);
    }

    public function test_set_stage_if_not_past_ignores_a_target_equal_to_the_current_stage(): void
    {
        Event::fake([PurchaseRequestStageChanged::class]);
        $request = PurchaseRequest::factory()->create(['stage' => 'lpo']);

        $this->service->setStageIfNotPast($request, 'lpo');

        $this->assertSame('lpo', $request->fresh()->stage);
        Event::assertNotDispatched(PurchaseRequestStageChanged::class);
    }

    public function test_stage_index_reflects_pipeline_order(): void
    {
        $this->assertSame(0, $this->service->stageIndex('draft'));
        $this->assertSame(8, $this->service->stageIndex('complete'));
        $this->assertLessThan(
            $this->service->stageIndex('payment'),
            $this->service->stageIndex('lpo')
        );
    }

    /**
     * An unknown stage indexes as 0 (draft), which is what lets
     * setStageIfNotPast() move an off-pipeline request forward rather than
     * stranding it.
     */
    public function test_stage_index_treats_an_unknown_stage_as_the_first_stage(): void
    {
        $this->assertSame(0, $this->service->stageIndex('not_a_stage'));
    }

    public function test_stage_label_maps_pipeline_stages_to_display_names(): void
    {
        $this->assertSame('GM Signature', $this->service->stageLabel('gm_approval'));
        $this->assertSame('Quote Comparison', $this->service->stageLabel('comparison'));
    }

    public function test_stage_label_falls_back_to_a_readable_form_for_unknown_stages(): void
    {
        $this->assertSame('Cancelled', $this->service->stageLabel('cancelled'));
    }
}
