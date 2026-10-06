<?php

namespace Tests\Unit;

use App\Events\PurchaseRequestStageChanged;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Services\PurchaseStageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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
        // Payment was the eighth; the pipeline ends at receiving now.
        $this->assertSame(7, $this->service->stageIndex('complete'));
        $this->assertLessThan(
            $this->service->stageIndex('receiving'),
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

    public function test_a_new_request_records_its_first_stage(): void
    {
        $request = PurchaseRequest::factory()->create(['stage' => 'draft', 'requested_by_name' => 'Ali']);

        $event = $request->stageEvents()->sole();
        $this->assertSame('draft', $event->stage);
        $this->assertSame('Ali', $event->actor_name);
    }

    public function test_moving_stage_records_when_and_who(): void
    {
        $user = User::factory()->create(['name' => 'Nelson']);
        $this->actingAs($user);
        Carbon::setTestNow('2026-10-06 08:15:00');
        $request = PurchaseRequest::factory()->create(['stage' => 'rfq']);

        Carbon::setTestNow('2026-10-06 09:45:00');
        $this->service->advance($request);

        $event = $request->stageEvents()->where('stage', 'quoting')->sole();
        $this->assertSame($user->id, $event->user_id);
        $this->assertSame('Nelson', $event->actor_name);
        $this->assertSame('2026-10-06 09:45:00', $event->reached_at->format('Y-m-d H:i:s'));
        Carbon::setTestNow();
    }

    public function test_a_supplier_on_the_portal_is_named_when_nobody_is_signed_in(): void
    {
        $request = PurchaseRequest::factory()->create(['stage' => 'quoting']);

        $this->service->setStage($request, 'comparison', 'Yousif Dhneem');

        $event = $request->stageEvents()->where('stage', 'comparison')->sole();
        $this->assertNull($event->user_id);
        $this->assertSame('Yousif Dhneem', $event->actor_name);
    }

    public function test_moving_back_drops_the_later_stages_and_keeps_the_first_arrival(): void
    {
        Carbon::setTestNow('2026-10-06 08:00:00');
        $request = PurchaseRequest::factory()->create(['stage' => 'quoting']);
        $this->service->setStage($request, 'comparison');
        Carbon::setTestNow('2026-10-06 09:00:00');
        $this->service->setStage($request, 'lpo');

        // An award taken back.
        Carbon::setTestNow('2026-10-06 10:00:00');
        $this->service->setStage($request, 'comparison');

        $this->assertFalse($request->stageEvents()->where('stage', 'lpo')->exists());
        $comparison = $request->stageEvents()->where('stage', 'comparison')->sole();
        $this->assertSame('08:00', $comparison->reached_at->format('H:i'));

        Carbon::setTestNow('2026-10-06 11:00:00');
        $this->service->setStage($request, 'lpo');
        $this->assertSame('11:00', $request->stageEvents()->where('stage', 'lpo')->sole()->reached_at->format('H:i'));
        Carbon::setTestNow();
    }
}
