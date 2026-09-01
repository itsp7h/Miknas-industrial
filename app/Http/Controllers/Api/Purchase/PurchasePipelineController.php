<?php

namespace App\Http\Controllers\Api\Purchase;

use App\Http\Controllers\Controller;
use App\Http\Resources\PurchaseRequestBoardResource;
use App\Http\Resources\PurchaseRequestDetailResource;
use App\Models\PurchaseRequest;
use App\Models\PurchaseSignature;
use App\Models\Supplier;
use App\Policies\PurchaseRequestPolicy;
use App\Services\LpoGenerationService;
use App\Services\PurchaseStageService;
use App\Services\RfqInvitationService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PurchasePipelineController extends Controller
{
    public function index()
    {
        $query = PurchaseRequest::with('requestedBy');
        $user = auth()->user();

        if (! $user->can('purchase-requests.view-all')) {
            if ($user->can('purchase-requests.view-active-pipeline')) {
                $query->whereIn('stage', PurchaseRequestPolicy::ACTIVE_PIPELINE_STAGES);
            } elseif ($user->can('purchase-requests.view-own')) {
                $query->where('requested_by', $user->id);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        return PurchaseRequestBoardResource::collection($query->latest()->get());
    }

    /** Backs the React pipeline detail page. */
    public function show(PurchaseRequest $purchaseRequest)
    {
        $this->authorize('view', $purchaseRequest);

        // The same relation graph the Blade show() loaded, so the sidebar and
        // timeline have every count and name they render without N+1 queries.
        $purchaseRequest->load([
            'requestedBy', 'items', 'signature.signedBy',
            'rfqInvitations.supplier', 'supplierQuotes.supplier', 'supplierQuotes.items',
            'purchaseOrders.supplier',
        ]);

        return new PurchaseRequestDetailResource($purchaseRequest);
    }

    /** The supplier picker's options: who can be invited, and who already is. */
    public function formOptions(PurchaseRequest $purchaseRequest)
    {
        $this->authorize('manageRfq', $purchaseRequest);

        return response()->json([
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')
                ->get(['id', 'name', 'email', 'phone'])
                ->map(fn ($supplier) => [
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                    'email' => $supplier->email,
                    'phone' => $supplier->phone,
                    // The channel each supplier can actually be reached on.
                    'can_email' => (bool) $supplier->email,
                    'can_whatsapp' => (bool) $supplier->phone,
                ]),
            'selected_supplier_ids' => $purchaseRequest->rfqInvitations()->pluck('supplier_id'),
            'items' => $purchaseRequest->items()->get(['id', 'description'])
                ->map(fn ($item) => ['id' => $item->id, 'description' => $item->description]),
        ]);
    }

    /**
     * Both of the Blade modal's modes: one set of suppliers for the whole
     * request, or a different set per item. Suppliers already invited are
     * skipped rather than duplicated, as before.
     */
    public function selectSuppliers(Request $request, PurchaseRequest $purchaseRequest, RfqInvitationService $service, PurchaseStageService $stages)
    {
        $this->authorize('manageRfq', $purchaseRequest);

        $data = $request->validate([
            'mode' => ['required', 'in:global,by_item'],
            'supplier_ids' => ['array'],
            'supplier_ids.*' => ['integer', 'exists:suppliers,id'],
            'item_suppliers' => ['array'],
            'channels' => ['array'],
        ]);

        $channels = $data['channels'] ?? [];
        $already = $purchaseRequest->rfqInvitations()->pluck('supplier_id')->all();
        $added = 0;

        if ($data['mode'] === 'by_item') {
            // item_suppliers[itemId] = [supplierId, …] → supplierId => [itemIds]
            $supplierItems = [];
            foreach ($data['item_suppliers'] ?? [] as $itemId => $supplierIds) {
                foreach ((array) $supplierIds as $supplierId) {
                    $supplierItems[(int) $supplierId][] = (int) $itemId;
                }
            }

            if (! $supplierItems) {
                throw ValidationException::withMessages([
                    'item_suppliers' => ['Please assign at least one supplier to an item.'],
                ]);
            }

            foreach ($supplierItems as $supplierId => $itemIds) {
                if (in_array($supplierId, $already)) {
                    continue;
                }
                $service->select($purchaseRequest, Supplier::findOrFail($supplierId), $channels[$supplierId] ?? 'email', $itemIds);
                $added++;
            }
        } else {
            if (! ($data['supplier_ids'] ?? [])) {
                throw ValidationException::withMessages([
                    'supplier_ids' => ['Please choose at least one supplier.'],
                ]);
            }

            foreach ($data['supplier_ids'] as $supplierId) {
                if (in_array($supplierId, $already)) {
                    continue;
                }
                $service->select($purchaseRequest, Supplier::findOrFail($supplierId), $channels[$supplierId] ?? 'email');
                $added++;
            }
        }

        $stages->setStage($purchaseRequest, 'rfq');

        return $this->fresh($purchaseRequest, $added.' supplier(s) added. Now send them the quote request links.');
    }

    public function sendInvitations(PurchaseRequest $purchaseRequest, RfqInvitationService $service, PurchaseStageService $stages)
    {
        $this->authorize('manageRfq', $purchaseRequest);

        $pending = $purchaseRequest->rfqInvitations()->where('status', 'pending')->with('supplier')->get();

        abort_if($pending->isEmpty(), 422, 'No unsent invitations. Select suppliers first.');

        foreach ($pending as $invitation) {
            $service->sendInvitation($invitation);
        }

        $stages->setStage($purchaseRequest, 'quoting');

        return $this->fresh($purchaseRequest, $pending->count().' supplier(s) notified. Waiting for quotes.');
    }

    public function generateLpo(PurchaseRequest $purchaseRequest, LpoGenerationService $service, PurchaseStageService $stages)
    {
        $this->authorize('generateLpo', $purchaseRequest);

        try {
            $orders = $service->generate($purchaseRequest);
        } catch (RuntimeException $e) {
            // The service refuses when nothing is awarded; that is a message for
            // the user, not a 500.
            abort(422, $e->getMessage());
        }

        $stages->setStage($purchaseRequest, 'receiving');

        return $this->fresh(
            $purchaseRequest,
            $orders->count().' LPO(s) generated: '.$orders->pluck('po_number')->implode(', ')
        );
    }

    public function storeSignature(Request $request, PurchaseRequest $purchaseRequest, PurchaseStageService $stages)
    {
        $this->authorize('approve', $purchaseRequest);

        $data = $request->validate(['signature_image' => ['required', 'string']]);

        // Signing twice would overwrite who approved it and when.
        abort_if((bool) $purchaseRequest->signature, 422, 'This request has already been signed.');

        PurchaseSignature::create([
            'purchase_request_id' => $purchaseRequest->id,
            'signed_by' => auth()->id(),
            'signature_image' => $data['signature_image'],
            'signed_at' => now(),
            'ip_address' => $request->ip(),
        ]);

        $stages->advance($purchaseRequest);

        return $this->fresh($purchaseRequest, 'Signature saved. Request moved to the RFQ stage.');
    }

    /** Every action answers with the whole request, so the page re-renders once. */
    private function fresh(PurchaseRequest $purchaseRequest, string $message)
    {
        $purchaseRequest->refresh()->load([
            'requestedBy', 'items', 'signature.signedBy',
            'rfqInvitations.supplier', 'supplierQuotes.supplier', 'supplierQuotes.items',
            'purchaseOrders.supplier',
        ]);

        return (new PurchaseRequestDetailResource($purchaseRequest))
            ->additional(['message' => $message]);
    }
}
