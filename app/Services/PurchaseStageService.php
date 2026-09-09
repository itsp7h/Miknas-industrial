<?php

namespace App\Services;

use App\Events\PurchaseRequestStageChanged;
use App\Models\PurchaseRequest;

class PurchaseStageService
{
    const STAGES = [
        'draft', 'gm_approval', 'rfq', 'quoting',
        'comparison', 'lpo', 'receiving', 'payment', 'complete',
    ];

    public function advance(PurchaseRequest $request): void
    {
        $current = array_search($request->stage, self::STAGES);
        if ($current === false || $current === count(self::STAGES) - 1) {
            return;
        }
        $this->setStage($request, self::STAGES[$current + 1]);
    }

    public function setStage(PurchaseRequest $request, string $stage): void
    {
        abort_unless(in_array($stage, self::STAGES), 422, 'Invalid stage');
        $request->update(['stage' => $stage]);
        event(new PurchaseRequestStageChanged($request->id, $request->request_number, $stage));
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
            'payment' => 'Payment',
            'complete' => 'Complete',
            default => ucfirst($stage),
        };
    }
}
