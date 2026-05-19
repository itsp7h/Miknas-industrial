<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Models\RfqInvitation;
use App\Models\SupplierQuote;
use App\Models\SupplierQuoteItem;
use App\Services\PurchaseStageService;
use Illuminate\Http\Request;

class RfqPortalController extends Controller
{
    private function resolve(string $token): RfqInvitation
    {
        return RfqInvitation::where('token', $token)
            ->with(['purchaseRequest.items', 'supplier'])
            ->firstOrFail();
    }

    public function show(string $token)
    {
        $invitation = $this->resolve($token);

        if ($invitation->isSubmitted()) {
            return view('rfq.submitted', compact('invitation'));
        }

        if ($invitation->isExpired()) {
            return view('rfq.expired', compact('invitation'));
        }

        if ($invitation->status === 'sent') {
            $invitation->update(['status' => 'opened', 'opened_at' => now()]);
        }

        $purchaseRequest = $invitation->purchaseRequest;
        $items           = $purchaseRequest->items;

        return view('rfq.show', compact('invitation', 'purchaseRequest', 'items'));
    }

    public function submit(Request $request, string $token)
    {
        $invitation = $this->resolve($token);

        if ($invitation->isSubmitted() || $invitation->isExpired()) {
            abort(403, 'This link is no longer valid.');
        }

        $validated = $request->validate([
            'lead_time_days'     => ['nullable', 'integer', 'min:0'],
            'payment_terms'      => ['nullable', 'string', 'max:200'],
            'notes'              => ['nullable', 'string', 'max:1000'],
            'items'              => ['required', 'array'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        $purchaseItems = $invitation->purchaseRequest->items;

        $quote = SupplierQuote::create([
            'rfq_invitation_id'   => $invitation->id,
            'purchase_request_id' => $invitation->purchase_request_id,
            'supplier_id'         => $invitation->supplier_id,
            'submitted_at'        => now(),
            'lead_time_days'      => $validated['lead_time_days'],
            'payment_terms'       => $validated['payment_terms'],
            'notes'               => $validated['notes'],
            'total_amount'        => 0,
        ]);

        $total = 0;
        foreach ($purchaseItems as $i => $item) {
            $unitPrice  = (float)($validated['items'][$i]['unit_price'] ?? 0);
            $qty        = (float)$item->quantity;
            $totalPrice = round($unitPrice * $qty, 3);
            $total     += $totalPrice;

            SupplierQuoteItem::create([
                'supplier_quote_id' => $quote->id,
                'description'       => $item->description,
                'unit'              => $item->unit ?? '',
                'quantity'          => $qty,
                'unit_price'        => $unitPrice,
                'total_price'       => $totalPrice,
            ]);
        }

        $quote->update(['total_amount' => round($total, 3)]);
        $invitation->update(['status' => 'submitted']);

        // If at least 1 quote is in, move to comparison stage
        $pr = $invitation->purchaseRequest;
        if ($pr->stage === 'quoting') {
            app(PurchaseStageService::class)->setStage($pr, 'comparison');
        }

        return view('rfq.submitted', compact('invitation'));
    }
}
