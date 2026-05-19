<?php

namespace App\Services;

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
        $request->update(['stage' => self::STAGES[$current + 1]]);
    }

    public function setStage(PurchaseRequest $request, string $stage): void
    {
        abort_unless(in_array($stage, self::STAGES), 422, 'Invalid stage');
        $request->update(['stage' => $stage]);
    }

    public function stageIndex(string $stage): int
    {
        $idx = array_search($stage, self::STAGES);
        return $idx === false ? 0 : $idx;
    }

    public function stageLabel(string $stage): string
    {
        return match ($stage) {
            'draft'       => 'Purchase Request',
            'gm_approval' => 'GM Signature',
            'rfq'         => 'Select Suppliers',
            'quoting'     => 'Awaiting Quotes',
            'comparison'  => 'Quote Comparison',
            'lpo'         => 'LPO Issued',
            'receiving'   => 'Receiving Materials',
            'payment'     => 'Payment',
            'complete'    => 'Complete',
            default       => ucfirst($stage),
        };
    }
}
