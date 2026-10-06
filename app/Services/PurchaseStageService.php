<?php

namespace App\Services;

use App\Events\PurchaseRequestStageChanged;
use App\Models\PurchaseRequest;

class PurchaseStageService
{
    const STAGES = [
        'draft', 'gm_approval', 'rfq', 'quoting',
        'comparison', 'lpo', 'receiving', 'complete',
    ];

    public function advance(PurchaseRequest $request): void
    {
        $current = array_search($request->stage, self::STAGES);
        if ($current === false || $current === count(self::STAGES) - 1) {
            return;
        }
        $this->setStage($request, self::STAGES[$current + 1]);
    }

    /**
     * `$actorName` names who moved it when there is no signed-in user — a
     * supplier answering on the public portal.
     */
    public function setStage(PurchaseRequest $request, string $stage, ?string $actorName = null): void
    {
        abort_unless(in_array($stage, self::STAGES), 422, 'Invalid stage');

        $from = $request->stage;
        $request->update(['stage' => $stage]);
        $this->record($request, $from, $stage, $actorName);

        event(new PurchaseRequestStageChanged($request->id, $request->request_number, $stage));
    }

    /**
     * Writes the stage history. Moving back (taking an award back drops a
     * request from lpo to comparison) undoes the later stages, so their times
     * go; the stage it returns to keeps the time it was first reached.
     */
    private function record(PurchaseRequest $request, ?string $from, string $stage, ?string $actorName): void
    {
        $index = $this->stageIndex($stage);

        if ($from !== null && $index < $this->stageIndex($from)) {
            $request->stageEvents()
                ->whereIn('stage', array_slice(self::STAGES, $index + 1))
                ->delete();
        }

        if ($from === $stage || $request->stageEvents()->where('stage', $stage)->exists()) {
            return;
        }

        $user = auth()->user();
        $request->stageEvents()->create([
            'stage' => $stage,
            'user_id' => $user?->id,
            'actor_name' => $user?->name ?? $actorName,
            'reached_at' => now(),
        ]);
    }

    /**
     * Move to a stage only if the request hasn't already progressed past it —
     * e.g. re-awarding an item after LPOs are issued shouldn't roll the
     * request back from 'receiving' to 'lpo'.
     */
    public function setStageIfNotPast(PurchaseRequest $request, string $stage): void
    {
        if ($this->stageIndex($request->stage) < $this->stageIndex($stage)) {
            $this->setStage($request, $stage);
        }
    }

    public function stageIndex(string $stage): int
    {
        $idx = array_search($stage, self::STAGES);

        return $idx === false ? 0 : $idx;
    }

    public function stageLabel(string $stage): string
    {
        return match ($stage) {
            'draft' => 'Purchase Request',
            'gm_approval' => 'GM Signature',
            'rfq' => 'Select Suppliers',
            'quoting' => 'Awaiting Quotes',
            'comparison' => 'Quote Comparison',
            'lpo' => 'LPO Issued',
            'receiving' => 'Receiving Materials',
            'complete' => 'Complete',
            default => ucfirst($stage),
        };
    }
}
