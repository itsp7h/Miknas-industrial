<?php

namespace App\Http\Controllers\Api\Purchase;

use App\Events\GrnDeleted;
use App\Events\GrnSaved;
use App\Http\Controllers\Controller;
use App\Http\Resources\GrnResource;
use App\Models\GoodsReceiptNote;
use App\Models\GrnDocument;
use App\Models\GrnItem;
use App\Models\PurchaseOrder;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\Purchase\GoodsReceiptConfirmedNotification;
use App\Services\PurchaseStageService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

class GoodsReceiptNoteController extends Controller
{
    public const TYPES = ['inventory', 'consumable'];

    /** What each uploaded file may be: a scan or a PDF, up to 10 MB. */
    private const FILE_RULE = 'file|mimes:pdf,jpg,jpeg,png|max:10240';

    private const DOCUMENT_RULE = 'nullable|'.self::FILE_RULE;

    /** Relations every single-GRN response carries. */
    private const DETAIL = ['purchaseOrder.supplier', 'warehouse', 'items.item', 'items.purchaseOrderItem', 'documents'];

    /**
     * Company name -> warehouse id, for the life of one request.
     *
     * Open purchase orders are many and the companies behind them are three,
     * so resolving each order separately would ask the same question dozens of
     * times.
     *
     * @var array<string, int|null>
     */
    private array $warehouseByCompany = [];

    /**
     * The warehouse this order's goods belong in, from its company's link.
     *
     * Null when the order has no request behind it, when its company is not on
     * file, or when that company has no warehouse set. In each case the receipt
     * falls back to offering every warehouse, exactly as it did before — an
     * unlinked company is a valid state, not a fault.
     */
    private function impliedWarehouseId(PurchaseOrder $po): ?int
    {
        $name = $po->purchaseRequest?->company_name;

        if (! $name) {
            return null;
        }

        if (! array_key_exists($name, $this->warehouseByCompany)) {
            $this->warehouseByCompany[$name] = $po->purchaseRequest->resolveCompany()?->warehouse_id;
        }

        return $this->warehouseByCompany[$name];
    }

    public function index()
    {
        return GrnResource::collection(
            // Documents too, so each row can say what paperwork it still needs.
            GoodsReceiptNote::with(['purchaseOrder.supplier', 'warehouse', 'documents'])->latest()->get()
        );
    }

    public function show(GoodsReceiptNote $grn)
    {
        return new GrnResource($grn->load([
            ...self::DETAIL, 'receivedBy',
        ]));
    }

    /**
     * Receivable orders and their lines, so the form can populate its item rows
     * the way the Blade page's data-items JSON blob did.
     */
    public function formOptions()
    {
        $orders = PurchaseOrder::whereIn('status', ['sent', 'partial'])
            ->with(['supplier', 'items.item', 'purchaseRequest'])
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'purchase_orders' => $orders->map(fn ($po) => [
                'id' => $po->id,
                'po_number' => $po->po_number ?? 'PO-'.str_pad((string) $po->id, 5, '0', STR_PAD_LEFT),
                'supplier_name' => $po->supplier?->name,
                // Where this order's goods land, decided by its company rather
                // than by whoever is filling the form in. Null leaves the
                // choice open.
                'warehouse_id' => $this->impliedWarehouseId($po),
                'company_name' => $po->purchaseRequest?->company_name,
                'items' => $po->items->map(fn ($line) => [
                    'purchase_order_item_id' => $line->id,
                    'item_id' => $line->item_id,
                    'item_name' => $line->item?->item_name ?? "Item #{$line->item_id}",
                    'unit_of_measure' => $line->item?->unit_of_measure,
                    'quantity' => $line->quantity,
                    'quantity_received' => $line->quantity_received,
                    'rate' => $line->rate,
                ])->values(),
            ])->values(),
            'warehouses' => Warehouse::orderBy('name')->get(['id', 'name']),
            'types' => self::TYPES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'purchase_order_id' => 'required|exists:purchase_orders,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'received_date' => 'required|date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.purchase_order_item_id' => 'nullable|exists:purchase_order_items,id',
            'items.*.quantity_received' => 'required|numeric|min:0.01',
            'items.*.unit_cost' => 'nullable|numeric|min:0',
            'items.*.type' => 'nullable|in:'.implode(',', self::TYPES),
            // The LPO, the supplier's GRN and the tax invoice. Any of them may
            // follow the goods, so a receipt saves without them and says what
            // it still needs (missing_documents) until they are uploaded.
            ...$this->documentRules(),
        ], ...$this->documentMessages());

        $po = PurchaseOrder::with(['items', 'purchaseRequest'])->findOrFail($data['purchase_order_id']);

        // The company's link decides the yard. The form does not offer the
        // choice, so a receipt naming a different warehouse did not come from
        // it — coercing it silently would hide that, and honouring it would
        // put the stock where the link says it must not go.
        $implied = $this->impliedWarehouseId($po);

        if ($implied !== null && (int) $data['warehouse_id'] !== $implied) {
            return response()->json([
                'message' => "This order is received into its company's warehouse. Change the link in Settings to receive it elsewhere.",
                'errors' => ['warehouse_id' => ['This order belongs to a different warehouse.']],
            ], 422);
        }

        // Files written so far, so a failed save leaves none behind: the
        // transaction rolls the rows back, but not the disk.
        $stored = [];

        try {
            $grn = DB::transaction(function () use ($data, $po, $request, &$stored) {
                $grn = GoodsReceiptNote::create([
                    'grn_number' => $this->nextGrnNumber(),
                    'purchase_order_id' => $po->id,
                    'supplier_id' => $po->supplier_id,
                    'warehouse_id' => $data['warehouse_id'],
                    'received_date' => $data['received_date'],
                    // The Blade controller validated no notes field and omitted it
                    // from create(), so whatever the user typed was discarded.
                    'notes' => $data['notes'] ?? null,
                    'status' => 'draft',
                    'received_by' => auth()->id(),
                ]);

                foreach ($data['items'] as $line) {
                    $poItemId = $line['purchase_order_item_id']
                        ?? $po->items->firstWhere('item_id', $line['item_id'])?->id;

                    GrnItem::create([
                        'goods_receipt_note_id' => $grn->id,
                        'purchase_order_item_id' => $poItemId,
                        'item_id' => $line['item_id'],
                        'quantity_received' => $line['quantity_received'],
                        'unit_cost' => $line['unit_cost'] ?? 0,
                        'type' => $line['type'] ?? 'inventory',
                    ]);
                }

                $this->storeFiles($grn, $this->filesFrom($request), $stored);

                return $grn;
            });
        } catch (\Throwable $e) {
            Storage::disk(GrnDocument::DISK)->delete($stored);

            throw $e;
        }

        event(new GrnSaved($grn));

        return (new GrnResource($grn->load(self::DETAIL)))
            ->response()->setStatusCode(201);
    }

    /**
     * Adds paperwork to a saved receipt: a missing LPO, GRN or tax invoice
     * (or a replacement for one), and more "Other" files. Allowed after
     * confirming too — a tax invoice often arrives after the goods.
     */
    public function addDocuments(Request $request, GoodsReceiptNote $grn)
    {
        $request->validate($this->documentRules(), ...$this->documentMessages());

        $files = $this->filesFrom($request);

        if ($files === []) {
            return response()->json([
                'message' => 'Choose a file to upload.',
                'errors' => ['documents' => ['Choose a file to upload.']],
            ], 422);
        }

        $others = count(array_filter($files, fn ($file) => $file[0] === GrnDocument::OTHER));

        if ($others > 0 && $grn->documents()->where('kind', GrnDocument::OTHER)->count() + $others > GrnDocument::MAX_OTHER) {
            return response()->json([
                'message' => 'A receipt holds at most '.GrnDocument::MAX_OTHER.' other files.',
                'errors' => ['other_documents' => ['A receipt holds at most '.GrnDocument::MAX_OTHER.' other files.']],
            ], 422);
        }

        // A named document uploaded again replaces the one before; its old
        // file goes once the new one is safely recorded.
        $replaced = $grn->documents()
            ->whereIn('kind', array_column($files, 0))
            ->where('kind', '!=', GrnDocument::OTHER)
            ->get();
        $stored = [];

        try {
            DB::transaction(function () use ($grn, $files, $replaced, &$stored) {
                $replaced->each->delete();
                $this->storeFiles($grn, $files, $stored);
            });
        } catch (\Throwable $e) {
            Storage::disk(GrnDocument::DISK)->delete($stored);

            throw $e;
        }

        Storage::disk(GrnDocument::DISK)->delete($replaced->pluck('path')->all());

        event(new GrnSaved($grn));

        return response()->json([
            'data' => (new GrnResource($grn->fresh([...self::DETAIL, 'receivedBy'])))->resolve(),
            'message' => 'Documents uploaded.',
        ]);
    }

    /** Every file rule, all optional: store and addDocuments share them. */
    private function documentRules(): array
    {
        return [
            ...collect(GrnDocument::KINDS)->keys()->mapWithKeys(fn ($kind) => ["{$kind}_document" => self::DOCUMENT_RULE])->all(),
            // Anything else that came with the delivery.
            'other_documents' => 'nullable|array|max:'.GrnDocument::MAX_OTHER,
            'other_documents.*' => self::FILE_RULE,
        ];
    }

    /** The messages and attribute names for documentRules(), as validate()'s last two arguments. */
    private function documentMessages(): array
    {
        return [
            ['other_documents.max' => 'Attach at most '.GrnDocument::MAX_OTHER.' other files.'],
            [
                ...collect(GrnDocument::KINDS)->mapWithKeys(fn ($label, $kind) => ["{$kind}_document" => "{$label} document"])->all(),
                'other_documents.*' => 'other file',
            ],
        ];
    }

    /** @return list<array{0: string, 1: UploadedFile}> kind => file, for what was sent */
    private function filesFrom(Request $request): array
    {
        return [
            ...collect(GrnDocument::KINDS)->keys()
                ->filter(fn ($kind) => $request->hasFile("{$kind}_document"))
                ->map(fn ($kind) => [$kind, $request->file("{$kind}_document")])
                ->values()->all(),
            ...array_map(fn ($file) => [GrnDocument::OTHER, $file], $request->file('other_documents', [])),
        ];
    }

    /** Writes each file to the disk and records it, noting each path in $stored for cleanup. */
    private function storeFiles(GoodsReceiptNote $grn, array $files, array &$stored): void
    {
        foreach ($files as [$kind, $file]) {
            $stored[] = $path = $file->store("grn-documents/{$grn->id}", GrnDocument::DISK);

            $grn->documents()->create([
                'kind' => $kind,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'uploaded_by' => auth()->id(),
            ]);
        }
    }

    /**
     * Receives the goods: raises stock, writes the movements, advances the PO's
     * received quantities, and marks the PO received once every line is met.
     * Same logic as the Blade controller — which no page ever linked to, so
     * confirming was unreachable and stock never actually moved.
     */
    public function confirm(GoodsReceiptNote $grn, PurchaseStageService $stages)
    {
        abort_if($grn->status === 'confirmed', 422, 'This GRN is already confirmed.');

        // A receipt may be saved while its paperwork is still coming, but it
        // is not complete — and does not move stock — until the LPO, the GRN
        // and the tax invoice are all on it.
        $missing = collect(GrnDocument::KINDS)
            ->reject(fn ($label, $kind) => $grn->documents()->where('kind', $kind)->exists())
            ->values();

        if ($missing->isNotEmpty()) {
            return response()->json([
                'message' => 'Upload the '.$missing->join(', ', ' and ').' before confirming this GRN.',
                'missing_documents' => $missing,
            ], 422);
        }

        DB::transaction(function () use ($grn) {
            $grn->load('items', 'purchaseOrder.items');

            foreach ($grn->items as $grnItem) {
                $stockLevel = StockLevel::firstOrCreate(
                    ['item_id' => $grnItem->item_id, 'warehouse_id' => $grn->warehouse_id],
                    ['quantity' => 0]
                );
                $stockLevel->increment('quantity', $grnItem->quantity_received);

                StockMovement::create([
                    'item_id' => $grnItem->item_id,
                    'warehouse_id' => $grn->warehouse_id,
                    'type' => 'in',
                    'quantity' => $grnItem->quantity_received,
                    'reference_type' => 'GoodsReceiptNote',
                    'reference_id' => $grn->id,
                    'created_by' => auth()->id(),
                ]);

                $grn->purchaseOrder->items()
                    ->where('item_id', $grnItem->item_id)
                    ->increment('quantity_received', $grnItem->quantity_received);
            }

            $grn->update(['status' => 'confirmed']);

            $po = $grn->purchaseOrder->fresh(['items']);
            $allReceived = $po->items->every(fn ($line) => $line->quantity_received >= $line->quantity);

            if ($allReceived) {
                $po->update(['status' => 'received']);
            }
        });

        $this->advanceRequestIfFullyReceived($grn, $stages);

        $operations = User::withProfile(config('purchase_access.notifications.operations'))->whereNotNull('whatsapp_number')->get();
        Notification::send($operations, new GoodsReceiptConfirmedNotification($grn));

        event(new GrnSaved($grn));

        return new GrnResource($grn->fresh(self::DETAIL));
    }

    public function destroy(GoodsReceiptNote $grn)
    {
        // A confirmed GRN has already moved stock; deleting it would leave those
        // movements referencing nothing.
        abort_if($grn->status === 'confirmed', 422, 'A confirmed GRN cannot be deleted.');

        $id = $grn->id;

        DB::transaction(function () use ($grn) {
            $grn->items()->delete();
            $grn->documents()->delete();
            $grn->delete();
        });

        // After the rows are gone, so a failed delete keeps the files the
        // surviving rows point at.
        Storage::disk(GrnDocument::DISK)->deleteDirectory("grn-documents/{$id}");

        event(new GrnDeleted($id));

        return response()->json(['deleted' => true]);
    }

    /**
     * Move the originating request on to Payment once every LPO on it has been
     * received in full.
     *
     * Nothing did this before, so a request sat at 'receiving' for ever: the
     * pipeline kept offering "Record GRN" with no sign that anything had been
     * recorded, and the only way to tell was to go and look at the GRN list.
     *
     * Keyed on confirmed receipts, not recorded ones — a draft GRN has moved no
     * stock, so it has received nothing. Cancelled LPOs are skipped for the
     * same reason they are skipped everywhere else: a superseded order will
     * never be received, and waiting on it would strand the request here.
     */
    private function advanceRequestIfFullyReceived(GoodsReceiptNote $grn, PurchaseStageService $stages): void
    {
        $purchaseRequest = $grn->purchaseOrder?->purchaseRequest;

        if (! $purchaseRequest) {
            return;
        }

        $live = $purchaseRequest->purchaseOrders()->where('status', '!=', 'cancelled')->get();

        if ($live->isNotEmpty() && $live->every(fn ($po) => $po->status === 'received')) {
            // Receiving everything ends the request. Payment used to be a
            // stage after this one and is no longer tracked on the pipeline,
            // which also means a finished request finally reaches 'complete' —
            // nothing ever set it before.
            $stages->setStageIfNotPast($purchaseRequest, 'complete');
        }
    }

    private function nextGrnNumber(): string
    {
        return 'GRN-'.str_pad((string) (GoodsReceiptNote::max('id') + 1), 5, '0', STR_PAD_LEFT);
    }
}
