<?php

namespace App\Http\Controllers\Api\Purchase;

use App\Events\PurchaseRequestStageChanged;
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
use App\Support\IssuerSignature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PurchasePipelineController extends Controller
{
    public function index()
    {
        $query = PurchaseRequest::with('requestedBy');
        $user = auth()->user();

        if (! $user->can('pipeline.view-all')) {
            if ($user->can('pipeline.view-active-pipeline')) {
                $query->whereIn('stage', PurchaseRequestPolicy::ACTIVE_PIPELINE_STAGES);
            } elseif ($user->can('pipeline.view-own')) {
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
            'requestedBy', 'items', 'signature.signedBy', 'rejectedBy', 'stageEvents',
            'rfqInvitations.supplier', 'rfqInvitations.selectedBy', 'rfqInvitations.sentBy',
            'supplierQuotes.supplier', 'supplierQuotes.items',
            'purchaseOrders.supplier', 'purchaseOrders.approvedBy', 'purchaseOrders.createdBy', 'purchaseOrders.goodsReceiptNotes.warehouse',
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

        // Suppliers added after quotes are in must not roll the request back:
        // the quotes and any awards already made still stand.
        $stages->setStageIfNotPast($purchaseRequest, 'rfq');

        return $this->fresh($purchaseRequest, $added.' supplier(s) added. Now send them the quote request links.');
    }

    public function sendInvitations(PurchaseRequest $purchaseRequest, RfqInvitationService $service, PurchaseStageService $stages)
    {
        $this->authorize('manageRfq', $purchaseRequest);

        $pending = $purchaseRequest->rfqInvitations()->where('status', 'pending')->with('supplier')->get();

        abort_if($pending->isEmpty(), 422, 'No unsent invitations. Select suppliers first.');

        // One supplier's bad address must not cost the others their invitation,
        // so each is sent on its own and the failures are collected.
        $sent = 0;
        $failed = [];

        foreach ($pending as $invitation) {
            try {
                $service->sendInvitation($invitation);
                $sent++;
            } catch (RuntimeException $e) {
                $failed[$invitation->supplier->name] = $e->getMessage();
            }
        }

        // Nothing got through. The request stays where it is and the caller is
        // told why, rather than being shown a success it did not get.
        abort_if($sent === 0, 422, 'Could not send any invitation. '.reset($failed));

        $stages->setStageIfNotPast($purchaseRequest, 'quoting');

        return $this->fresh($purchaseRequest, $failed
            ? $sent.' of '.$pending->count().' supplier(s) notified. Could not reach '
                .implode(', ', array_keys($failed)).' — their invitations are still unsent.'
            : $pending->count().' supplier(s) notified. Waiting for quotes.');
    }

    /**
     * Issues the LPO(s), signed under Prepared By. They are not sent: an LPO
     * reaches its supplier only once someone else approves it under Approved
     * By (PurchaseOrderController::approve).
     */
    public function generateLpo(
        PurchaseRequest $purchaseRequest,
        LpoGenerationService $service
    ) {
        $this->authorize('generateLpo', $purchaseRequest);
        // Every LPO carries its issuer's signature, so none is issued without one.
        IssuerSignature::require(auth()->user());

        try {
            $orders = $service->generate($purchaseRequest);
        } catch (RuntimeException $e) {
            // The service refuses when nothing is awarded; that is a message for
            // the user, not a 500.
            abort(422, $e->getMessage());
        }

        // The request stays at 'lpo' until the LPOs are approved — approving is
        // what sends them and moves it to Receiving (LpoApprovalService). The
        // event is the invalidation signal other open screens refetch on.
        event(new PurchaseRequestStageChanged($purchaseRequest->id, $purchaseRequest->request_number, $purchaseRequest->stage));

        return $this->fresh(
            $purchaseRequest,
            $orders->count().' LPO(s) issued: '.$orders->pluck('po_number')->implode(', ')
                .'. Each goes to its supplier once it is approved.'
        );
    }

    public function storeSignature(Request $request, PurchaseRequest $purchaseRequest, PurchaseStageService $stages)
    {
        $this->authorize('approve', $purchaseRequest);

        $data = $request->validate(['signature_image' => ['required', 'string']]);

        // Signing twice would overwrite who approved it and when.
        abort_if((bool) $purchaseRequest->signature, 422, 'This request has already been signed.');

        DB::transaction(function () use ($data, $request, $purchaseRequest, $stages) {
            PurchaseSignature::create([
                'purchase_request_id' => $purchaseRequest->id,
                'signed_by' => auth()->id(),
                'signature_image' => $data['signature_image'],
                'signed_at' => now(),
                'ip_address' => $request->ip(),
            ]);

            // Signing IS the GM approval — the dialog is titled "Approve &
            // Sign" and says so. Until this, nothing wrote these three
            // columns: the Blade `approve` action was the only writer and lost
            // its UI in the cutover, so every signed request stayed 'pending'
            // for ever and the MPR sheet's approval block could never appear.
            $purchaseRequest->update([
                'status' => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            $stages->advance($purchaseRequest);
        });

        return $this->fresh($purchaseRequest, 'Approved and signed. Request moved to the RFQ stage.');
    }

    /**
     * The other half of the same gate: the GM refuses the request. Same policy
     * as signing, and like the Blade action it replaces it records the refusal
     * without moving the stage — a rejected request stops where it is rather
     * than travelling on down the pipeline.
     */
    public function reject(Request $request, PurchaseRequest $purchaseRequest)
    {
        $this->authorize('approve', $purchaseRequest);

        abort_if($purchaseRequest->status === 'rejected', 422, 'This request has already been rejected.');

        // Required, and long enough to say something: the reason is what the
        // requester reads to know what to change, so an empty one wastes a
        // round trip through the whole approval gate. Same rule as an award.
        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:5'],
        ]);

        // The refusal gets its own three columns rather than borrowing
        // `approved_by`/`approved_at`, which mean what they say — a request
        // approved and then rejected still carries them.
        $purchaseRequest->update([
            'status' => 'rejected',
            'rejection_reason' => $data['rejection_reason'],
            'rejected_by' => auth()->id(),
            'rejected_at' => now(),
        ]);

        return $this->fresh($purchaseRequest, $purchaseRequest->request_number.' rejected.');
    }

    /** Every action answers with the whole request, so the page re-renders once. */
    private function fresh(PurchaseRequest $purchaseRequest, string $message)
    {
        $purchaseRequest->refresh()->load([
            'requestedBy', 'items', 'signature.signedBy', 'rejectedBy', 'stageEvents',
            'rfqInvitations.supplier', 'rfqInvitations.selectedBy', 'rfqInvitations.sentBy',
            'supplierQuotes.supplier', 'supplierQuotes.items',
            'purchaseOrders.supplier', 'purchaseOrders.approvedBy', 'purchaseOrders.createdBy', 'purchaseOrders.goodsReceiptNotes.warehouse',
        ]);

        return (new PurchaseRequestDetailResource($purchaseRequest))
            ->additional(['message' => $message]);
    }
}
