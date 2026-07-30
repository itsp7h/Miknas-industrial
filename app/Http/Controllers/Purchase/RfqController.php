<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Services\PurchaseStageService;
use App\Services\RfqInvitationService;
use Illuminate\Http\Request;

class RfqController extends Controller
{
    public function show(PurchaseRequest $purchaseRequest)
    {
        $suppliers   = Supplier::where('is_active', true)->orderBy('name')->get();
        $invitations = $purchaseRequest->rfqInvitations()->with('supplier', 'quote')->get();
        return view('purchase.rfq.show', ['request' => $purchaseRequest, 'suppliers' => $suppliers, 'invitations' => $invitations]);
    }

    public function selectSuppliers(Request $request, PurchaseRequest $purchaseRequest, RfqInvitationService $service, PurchaseStageService $stages)
    {
        $this->authorize('manageRfq', $purchaseRequest);

        $mode = $request->input('mode', 'global');

        $alreadySelected = $purchaseRequest->rfqInvitations()->pluck('supplier_id')->toArray();
        $added = 0;

        if ($mode === 'by_item') {
            // item_suppliers[item_id][] = supplier_id  →  invert to supplier_id → [item_ids]
            $itemSuppliers = $request->input('item_suppliers', []);
            $supplierItems = [];
            foreach ($itemSuppliers as $itemId => $supplierIds) {
                foreach ((array) $supplierIds as $supplierId) {
                    $supplierItems[$supplierId][] = (int) $itemId;
                }
            }

            if (empty($supplierItems)) {
                if ($request->expectsJson()) {
                    return response()->json(['message' => 'Please assign at least one supplier to an item.'], 422);
                }
                return redirect()->back()->with('error', 'Please assign at least one supplier to an item.');
            }

            foreach ($supplierItems as $supplierId => $itemIds) {
                if (in_array($supplierId, $alreadySelected)) {
                    continue;
                }
                $channel  = $request->input('channel_' . $supplierId, 'email');
                $supplier = Supplier::findOrFail($supplierId);
                $service->select($purchaseRequest, $supplier, $channel, $itemIds);
                $added++;
            }
        } else {
            $validated = $request->validate([
                'supplier_ids'   => ['required', 'array', 'min:1'],
                'supplier_ids.*' => ['required', 'exists:suppliers,id'],
            ]);

            foreach ($validated['supplier_ids'] as $supplierId) {
                if (in_array($supplierId, $alreadySelected)) {
                    continue;
                }
                $channel  = $request->input('channel_' . $supplierId, 'email');
                $supplier = Supplier::findOrFail($supplierId);
                $service->select($purchaseRequest, $supplier, $channel);
                $added++;
            }
        }

        $stages->setStage($purchaseRequest, 'rfq');

        if ($request->expectsJson()) {
            $invitations = $purchaseRequest->rfqInvitations()->with('supplier')->get()->map(function ($inv) {
                return [
                    'supplier_name' => $inv->supplier->name,
                    'channel'       => $inv->channel,
                    'url'           => route('rfq.show', $inv->token),
                    'status'        => $inv->status,
                ];
            });
            return response()->json([
                'added'       => $added,
                'invitations' => $invitations,
                'redirect'    => route('purchase.pipeline.show', $purchaseRequest),
            ]);
        }

        return redirect()->route('purchase.pipeline.show', $purchaseRequest)
            ->with('success', $added . ' supplier(s) added. Now send them the quote request links.');
    }

    public function sendAll(PurchaseRequest $purchaseRequest, RfqInvitationService $service, PurchaseStageService $stages)
    {
        $this->authorize('manageRfq', $purchaseRequest);

        $pending = $purchaseRequest->rfqInvitations()->where('status', 'pending')->with('supplier')->get();

        if ($pending->isEmpty()) {
            return redirect()->back()->with('error', 'No unsent invitations. Select suppliers first.');
        }

        foreach ($pending as $invitation) {
            $service->sendInvitation($invitation);
        }

        $stages->setStage($purchaseRequest, 'quoting');

        return redirect()->route('purchase.pipeline.show', $purchaseRequest)
            ->with('success', $pending->count() . ' supplier(s) notified. Waiting for quotes.');
    }

    public function store(Request $request, PurchaseRequest $purchaseRequest, RfqInvitationService $service, PurchaseStageService $stages)
    {
        $this->authorize('manageRfq', $purchaseRequest);

        $validated = $request->validate([
            'supplier_ids'   => ['required', 'array', 'min:1'],
            'supplier_ids.*' => ['required', 'exists:suppliers,id'],
        ]);

        $alreadyInvited = $purchaseRequest->rfqInvitations()->pluck('supplier_id')->toArray();

        $sent = 0;
        foreach ($validated['supplier_ids'] as $supplierId) {
            if (in_array($supplierId, $alreadyInvited)) {
                continue;
            }
            $channel  = $request->input('channel_' . $supplierId, 'both');
            $supplier = Supplier::findOrFail($supplierId);
            $service->invite($purchaseRequest, $supplier, $channel);
            $sent++;
        }

        $stages->setStage($purchaseRequest, 'quoting');

        return redirect()->route('purchase.requests.rfq', $purchaseRequest)
            ->with('success', $sent . ' invitation(s) sent. Waiting for supplier quotes.');
    }
}
