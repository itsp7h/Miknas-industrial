<?php

namespace App\Http\Controllers\Api\Purchase;

use App\Events\PurchaseRequestCreated;
use App\Events\PurchaseRequestDeleted;
use App\Events\PurchaseRequestUpdated;
use App\Http\Controllers\Controller;
use App\Http\Resources\PurchaseRequestBoardResource;
use App\Http\Resources\PurchaseRequestDetailResource;
use App\Http\Resources\PurchaseRequestSheetResource;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Settings\Department;
use App\Models\Settings\ProjectSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The MPR create and edit forms. Both were Blade components — one 627-line
 * modal for new requests, embedded in the SPA host page purely so React could
 * call its Alpine open function, and a 489-line near-duplicate for editing.
 */
class PurchaseRequestController extends Controller
{
    /** The unit list the Blade modal offered, in its order. */
    public const UNITS = [
        'PCS', 'NOS', 'KG', 'TON', 'MTR', 'SQM', 'LTR',
        'BAG', 'BOX', 'ROLL', 'SET', 'EA', 'CANS', 'LOT',
    ];

    /**
     * Projects (with their locations), departments and units — everything the
     * cascading dropdowns need, in one request.
     *
     * The create modal used to hide projects with no company while the edit
     * modal offered them, so a project could be selectable when editing and
     * invisible when creating. Both get the same list here.
     */
    public function formOptions(Request $request)
    {
        // Either form needs this list, and the two are gated by different
        // permissions — creating a request and editing one are separate grants.
        abort_unless(
            $request->user()->can('purchase-requests.create')
                || $request->user()->can('purchase-requests.edit'),
            403
        );

        $projects = ProjectSetting::active()
            ->with([
                'company',
                'locations' => fn ($q) => $q->where('is_active', true)->orderBy('name'),
            ])
            ->orderBy('name')
            ->get();

        return response()->json([
            'projects' => $projects->map(fn ($project) => [
                'id' => $project->id,
                'name' => $project->name,
                'company_id' => $project->company_id,
                'company_name' => $project->company?->name,
                // Blade's own option label: "Company — Project".
                'label' => ($project->company ? $project->company->name.' — ' : '').$project->name,
                'locations' => $project->locations->map(fn ($l) => $l->name)->values(),
            ])->values(),
            'departments' => Department::where('is_active', true)->orderBy('name')
                ->get(['id', 'name', 'company_id']),
            'units' => self::UNITS,
            'today' => now()->toDateString(),
        ]);
    }

    /**
     * The full MPR sheet, read-only. This is the page the pipeline detail
     * header's "View Full Request" opens.
     */
    public function show(PurchaseRequest $purchaseRequest)
    {
        $this->authorize('view', $purchaseRequest);

        $purchaseRequest->load(['items', 'requestedBy', 'approvedBy', 'rejectedBy']);

        return response()->json(['data' => new PurchaseRequestSheetResource($purchaseRequest)]);
    }

    /**
     * The current values for the edit form. The pipeline detail payload shapes
     * its items for the timeline (quote counts, award flags), so the editable
     * fields have to come from here.
     */
    public function edit(PurchaseRequest $purchaseRequest)
    {
        $this->authorize('update', $purchaseRequest);

        $purchaseRequest->load('items');

        return response()->json(['data' => [
            'id' => $purchaseRequest->id,
            'request_number' => $purchaseRequest->request_number,
            'date' => $purchaseRequest->date?->toDateString(),
            'project_name' => $purchaseRequest->project_name,
            'requested_by_name' => $purchaseRequest->requested_by_name,
            'required_date_text' => $purchaseRequest->required_date_text,
            'location' => $purchaseRequest->location,
            'department' => $purchaseRequest->department,
            'remarks' => $purchaseRequest->remarks,
            'items' => $purchaseRequest->items->map(fn ($item) => [
                'description' => $item->description,
                'unit' => $item->unit,
                'quantity_required' => $item->quantity_required,
                'purpose_use' => $item->purpose_use,
                'required_date' => $item->required_date?->toDateString(),
            ])->values(),
        ]]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', PurchaseRequest::class);

        $data = $this->validated($request);

        $pr = DB::transaction(function () use ($data) {
            $pr = PurchaseRequest::create($this->fields($data) + [
                'request_number' => $this->nextRequestNumber(),
                'status' => 'pending',
                'requested_by' => auth()->id(),
            ]);

            $this->writeItems($pr, $data['items']);

            return $pr->refresh();
        });

        event(new PurchaseRequestCreated(
            $pr->id, $pr->request_number, $pr->date->toDateString(), $pr->project_name,
            $pr->requested_by_name, $pr->department, $pr->stage, $pr->requested_by
        ));

        // The board is what opens this form, so it answers with a board row —
        // the caller can drop it straight in without waiting for the broadcast
        // to come back round (which it never does with Reverb stopped).
        return response()->json([
            'data' => new PurchaseRequestBoardResource($pr),
            'message' => "{$pr->request_number} submitted successfully.",
        ], 201);
    }

    public function update(Request $request, PurchaseRequest $purchaseRequest)
    {
        $this->authorize('update', $purchaseRequest);

        $data = $this->validated($request);

        DB::transaction(function () use ($data, $purchaseRequest) {
            $purchaseRequest->update($this->fields($data));
            // Items are replaced wholesale, as the Blade form did: the rows are
            // a free-form table with no stable identity to match on.
            $purchaseRequest->items()->delete();
            $this->writeItems($purchaseRequest, $data['items']);
        });

        $purchaseRequest->refresh();

        event(new PurchaseRequestUpdated(
            $purchaseRequest->id, $purchaseRequest->request_number,
            $purchaseRequest->date?->toDateString(), $purchaseRequest->project_name,
            $purchaseRequest->requested_by_name, $purchaseRequest->department,
            $purchaseRequest->stage, $purchaseRequest->requested_by
        ));

        // Editing is reached from the pipeline detail page, so it answers with
        // that page's whole payload — one response re-renders it.
        $purchaseRequest->load([
            'requestedBy', 'items', 'signature.signedBy',
            'rfqInvitations.supplier', 'supplierQuotes.supplier', 'supplierQuotes.items',
            'purchaseOrders.supplier',
        ]);

        return response()->json([
            'data' => new PurchaseRequestDetailResource($purchaseRequest),
            'message' => "{$purchaseRequest->request_number} updated successfully.",
        ]);
    }

    public function destroy(PurchaseRequest $purchaseRequest)
    {
        $this->authorize('delete', $purchaseRequest);

        $number = $purchaseRequest->request_number;
        $id = $purchaseRequest->id;
        $purchaseRequest->delete();

        event(new PurchaseRequestDeleted($id));

        return response()->json(['message' => "{$number} deleted."]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'date' => 'required|date',
            'project_name' => 'required|string|max:255',
            'department' => 'nullable|string|max:255',
            'requested_by_name' => 'required|string|max:255',
            'required_date_text' => 'nullable|string|max:100',
            'location' => 'nullable|string|max:255',
            'remarks' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string',
            'items.*.unit' => 'nullable|string|max:50',
            'items.*.quantity_required' => 'required|numeric|min:0.01',
            'items.*.purpose_use' => 'nullable|string|max:255',
            'items.*.required_date' => 'nullable|date',
        ]);
    }

    private function fields(array $data): array
    {
        return [
            'date' => $data['date'],
            'project_name' => $data['project_name'],
            'department' => $data['department'] ?? null,
            'requested_by_name' => $data['requested_by_name'],
            'required_date_text' => $data['required_date_text'] ?? null,
            'location' => $data['location'] ?? null,
            'remarks' => $data['remarks'] ?? null,
        ];
    }

    private function writeItems(PurchaseRequest $pr, array $items): void
    {
        foreach ($items as $item) {
            // Defensive only: the modal drops empty rows before submitting and
            // TrimStrings + `required` would reject a whitespace-only one.
            if (trim((string) ($item['description'] ?? '')) === '') {
                continue;
            }

            PurchaseRequestItem::create([
                'purchase_request_id' => $pr->id,
                'description' => $item['description'],
                'unit' => $item['unit'] ?? null,
                'quantity_required' => $item['quantity_required'],
                'purpose_use' => $item['purpose_use'] ?? null,
                'required_date' => $item['required_date'] ?? null,
            ]);
        }
    }

    private function nextRequestNumber(): string
    {
        return 'MPR'.now()->format('y').'-'
            .str_pad((string) (PurchaseRequest::max('id') + 1), 4, '0', STR_PAD_LEFT);
    }
}
