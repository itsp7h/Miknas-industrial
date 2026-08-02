<?php

namespace Tests\Feature;

use App\Events\PurchaseRequestStageChanged;
use App\Models\PurchaseRequest;
use App\Services\PurchaseStageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class PurchaseRequestStageChangedBroadcastTest extends TestCase
{
    use RefreshDatabase;

    public function test_advancing_a_stage_broadcasts_the_change(): void
    {
        Event::fake([PurchaseRequestStageChanged::class]);
        $pr = PurchaseRequest::factory()->create(['stage' => 'draft']);

        app(PurchaseStageService::class)->advance($pr);

        Event::assertDispatched(
            PurchaseRequestStageChanged::class,
            fn ($e) => $e->purchaseRequestId === $pr->id && $e->stage === 'gm_approval'
        );
    }

    public function test_set_stage_broadcasts_the_change(): void
    {
        Event::fake([PurchaseRequestStageChanged::class]);
        $pr = PurchaseRequest::factory()->create(['stage' => 'draft']);

        app(PurchaseStageService::class)->setStage($pr, 'rfq');

        Event::assertDispatched(
            PurchaseRequestStageChanged::class,
            fn ($e) => $e->purchaseRequestId === $pr->id && $e->stage === 'rfq'
        );
    }

    public function test_set_stage_if_not_past_broadcasts_exactly_once_when_it_moves(): void
    {
        Event::fake([PurchaseRequestStageChanged::class]);
        $pr = PurchaseRequest::factory()->create(['stage' => 'draft']);

        app(PurchaseStageService::class)->setStageIfNotPast($pr, 'rfq');

        Event::assertDispatched(PurchaseRequestStageChanged::class, 1);
    }

    public function test_set_stage_if_not_past_does_not_broadcast_when_it_does_not_move(): void
    {
        Event::fake([PurchaseRequestStageChanged::class]);
        $pr = PurchaseRequest::factory()->create(['stage' => 'lpo']);

        app(PurchaseStageService::class)->setStageIfNotPast($pr, 'rfq');

        Event::assertNotDispatched(PurchaseRequestStageChanged::class);
    }

    public function test_event_broadcasts_on_the_shared_purchase_channel(): void
    {
        $event = new PurchaseRequestStageChanged(1, 'MPR26-0001', 'rfq');

        $channels = $event->broadcastOn();

        $this->assertSame('private-purchase', $channels[0]->name);
        $this->assertSame('purchase-request.stage-changed', $event->broadcastAs());
        $this->assertSame(['id' => 1, 'request_number' => 'MPR26-0001', 'stage' => 'rfq'], $event->broadcastWith());
    }
}
