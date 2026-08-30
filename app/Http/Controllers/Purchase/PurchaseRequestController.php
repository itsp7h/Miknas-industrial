<?php

namespace App\Http\Controllers\Purchase;

use App\Events\PurchaseRequestCreated;
use App\Http\Controllers\Controller;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseRequestController extends Controller
{
    public function index()
    {
        return redirect()->route('purchase.pipeline.index');
    }

    public function create()
    {
        return view('purchase.requests.create');
    }

    public function store(Request $request)
    {
        $this->authorize('create', PurchaseRequest::class);

        $request->validate([
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

        $pr = DB::transaction(function () use ($request) {
            $year = now()->format('y');
            $next = PurchaseRequest::max('id') + 1;
            $mprNumber = 'MPR'.$year.'-'.str_pad($next, 4, '0', STR_PAD_LEFT);

            $pr = PurchaseRequest::create([
                'request_number' => $mprNumber,
                'date' => $request->date,
                'project_name' => $request->project_name,
                'department' => $request->department,
                'requested_by_name' => $request->requested_by_name,
                'required_date_text' => $request->required_date_text,
                'location' => $request->location,
                'remarks' => $request->remarks,
                'status' => 'pending',
                'requested_by' => auth()->id(),
            ]);

            foreach ($request->items as $item) {
                if (empty(trim($item['description']))) {
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

            return $pr->refresh();
        });

        event(new PurchaseRequestCreated(
            $pr->id, $pr->request_number, $pr->date?->toDateString(), $pr->project_name,
            $pr->requested_by_name, $pr->department, $pr->stage, $pr->requested_by
        ));

        return redirect()->route('purchase.requests.index')->with('success', 'Purchase request submitted successfully.');
    }

    public function show(PurchaseRequest $purchaseRequest)
    {
        $this->authorize('view', $purchaseRequest);

        $purchaseRequest->load(['items', 'requestedBy', 'approvedBy']);

        return view('purchase.requests.show', compact('purchaseRequest'));
    }

    public function edit(PurchaseRequest $purchaseRequest)
    {
        $this->authorize('view', $purchaseRequest);

        $purchaseRequest->load('items');

        return view('purchase.requests.edit', compact('purchaseRequest'));
    }

    public function update(Request $request, PurchaseRequest $purchaseRequest)
    {
        $this->authorize('update', $purchaseRequest);

        $request->validate([
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

        DB::transaction(function () use ($request, $purchaseRequest) {
            $purchaseRequest->update([
                'date' => $request->date,
                'project_name' => $request->project_name,
                'department' => $request->department,
                'requested_by_name' => $request->requested_by_name,
                'required_date_text' => $request->required_date_text,
                'location' => $request->location,
                'remarks' => $request->remarks,
            ]);

            $purchaseRequest->items()->delete();

            foreach ($request->items as $item) {
                if (empty(trim($item['description']))) {
                    continue;
                }
                PurchaseRequestItem::create([
                    'purchase_request_id' => $purchaseRequest->id,
                    'description' => $item['description'],
                    'unit' => $item['unit'] ?? null,
                    'quantity_required' => $item['quantity_required'],
                    'purpose_use' => $item['purpose_use'] ?? null,
                    'required_date' => $item['required_date'] ?? null,
                ]);
            }
        });

        return redirect()->route('purchase.requests.show', $purchaseRequest)->with('success', 'Purchase request updated successfully.');
    }

    public function destroy(PurchaseRequest $purchaseRequest)
    {
        $this->authorize('delete', $purchaseRequest);

        $purchaseRequest->delete();

        return redirect()->route('purchase.requests.index')->with('success', 'Purchase request deleted.');
    }

    public function approve(PurchaseRequest $purchaseRequest)
    {
        $this->authorize('approve', $purchaseRequest);

        $purchaseRequest->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Purchase request approved.');
    }

    public function reject(PurchaseRequest $purchaseRequest)
    {
        $this->authorize('approve', $purchaseRequest);

        $purchaseRequest->update(['status' => 'rejected']);

        return redirect()->back()->with('success', 'Purchase request rejected.');
    }

    public function print(PurchaseRequest $purchaseRequest)
    {
        $this->authorize('view', $purchaseRequest);

        $purchaseRequest->load(['items', 'requestedBy', 'approvedBy', 'signature.signedBy']);

        return view('purchase.requests.print', compact('purchaseRequest'));
    }
}
