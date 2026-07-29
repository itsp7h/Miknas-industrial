<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Mail\LpoIssuedMail;
use App\Models\Item;
use App\Models\MailAccount;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\Setting;
use App\Models\Supplier;
use App\Notifications\Purchase\PurchaseOrderConfirmedNotification;
use App\Services\LpoGenerationService;
use App\Services\PurchaseStageService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class PurchaseOrderController extends Controller
{
    public function index()
    {
        $orders = PurchaseOrder::with('supplier')->paginate(15);

        return view('purchase.orders.index', compact('orders'));
    }

    public function create()
    {
        $suppliers        = Supplier::all();
        $items            = Item::all();
        $purchaseRequests = PurchaseRequest::where('status', 'approved')->get();

        return view('purchase.orders.create', compact('suppliers', 'items', 'purchaseRequests'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'supplier_id'          => 'required|exists:suppliers,id',
            'po_date'              => 'required|date',
            'items'                => 'required|array|min:1',
            'items.*.item_id'      => 'required|exists:items,id',
            'items.*.quantity'     => 'required|numeric|min:1',
            'items.*.rate'         => 'required|numeric|min:0',
        ]);

        $poNumber = 'PO-' . str_pad(PurchaseOrder::max('id') + 1, 5, '0', STR_PAD_LEFT);

        $totalAmount = collect($request->items)->sum(fn($item) => $item['quantity'] * $item['rate']);

        $order = PurchaseOrder::create([
            'po_number'    => $poNumber,
            'supplier_id'  => $request->supplier_id,
            'po_date'      => $request->po_date,
            'total_amount' => $totalAmount,
            'status'       => 'draft',
            'created_by'   => auth()->id(),
        ]);

        foreach ($request->items as $item) {
            PurchaseOrderItem::create([
                'purchase_order_id' => $order->id,
                'item_id'           => $item['item_id'],
                'quantity'          => $item['quantity'],
                'rate'              => $item['rate'],
                'total_amount'      => $item['quantity'] * $item['rate'],
            ]);
        }

        if ($order->supplier && $order->supplier->whatsapp_number) {
            $order->supplier->notify(new PurchaseOrderConfirmedNotification($order));
        }

        return redirect()->route('purchase.orders.show', $order)->with('success', 'Purchase order created successfully.');
    }

    /**
     * Auto-generate the LPO(s) for a request straight from its awarded quote items —
     * one LPO per winning supplier, since items on the same request can be split
     * across different suppliers.
     */
    public function generateFromRequest(PurchaseRequest $purchaseRequest, LpoGenerationService $service, PurchaseStageService $stages)
    {
        try {
            $orders = $service->generate($purchaseRequest);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $stages->setStage($purchaseRequest, 'receiving');

        foreach ($orders as $order) {
            $this->issueLpoToSupplier($order);
        }

        if ($orders->count() === 1) {
            return redirect()->route('purchase.orders.show', $orders->first())
                ->with('success', 'LPO ' . $orders->first()->po_number . ' generated.');
        }

        return redirect()->route('purchase.orders.index')
            ->with('success', $orders->count() . ' LPOs generated: ' . $orders->pluck('po_number')->implode(', '));
    }

    public function show(PurchaseOrder $order)
    {
        $order->load(['supplier', 'items.item', 'createdBy', 'purchaseRequest', 'goodsReceiptNotes.warehouse']);

        $company = $order->purchaseRequest
            ? \App\Models\Settings\ProjectSetting::where('name', $order->purchaseRequest->project_name)->with('company')->first()?->company
            : null;

        return view('purchase.orders.show', compact('order', 'company'));
    }

    public function print(PurchaseOrder $order)
    {
        $data = $this->lpoDocumentData($order);

        return view('purchase.orders.print', $data);
    }

    public function pdf(PurchaseOrder $order)
    {
        $data = $this->lpoDocumentData($order);

        $pdf = Pdf::loadView('purchase.orders.pdf', $data)
            ->setPaper('a4', 'portrait');

        return $pdf->download(($order->po_number ?? 'PO-' . str_pad($order->id, 5, '0', STR_PAD_LEFT)) . '.pdf');
    }

    /**
     * Notify the winning supplier that their LPO was issued — email with the
     * signed PDF attached, plus a WhatsApp heads-up if a number is on file.
     * Failures here must not block LPO issuance, but must be logged so a
     * failed send is discoverable instead of silently disappearing.
     */
    private function issueLpoToSupplier(PurchaseOrder $order): void
    {
        $order->loadMissing('supplier');

        if ($order->supplier && $order->supplier->email) {
            $account = MailAccount::where('enabled', true)->first();

            try {
                if (! $account) {
                    throw new RuntimeException('No enabled mail account is configured.');
                }

                $pdf = Pdf::loadView('purchase.orders.pdf', $this->lpoDocumentData($order))
                    ->setPaper('a4', 'portrait')
                    ->output();

                Mail::mailer($account->name)
                    ->to($order->supplier->email)
                    ->send(new LpoIssuedMail($order, $pdf));
            } catch (\Throwable $e) {
                Log::error('LPO email failed to send', [
                    'purchase_order_id' => $order->id,
                    'po_number'         => $order->po_number,
                    'supplier_id'       => $order->supplier->id,
                    'supplier_email'    => $order->supplier->email,
                    'mail_account'      => $account?->name,
                    'error'             => $e->getMessage(),
                ]);
            }
        }

        if ($order->supplier && $order->supplier->whatsapp_number) {
            $order->supplier->notify(new PurchaseOrderConfirmedNotification($order));
        }
    }

    private function lpoDocumentData(PurchaseOrder $order): array
    {
        $order->load(['supplier', 'items.item', 'createdBy', 'purchaseRequest']);

        $company = $order->purchaseRequest
            ? \App\Models\Settings\ProjectSetting::where('name', $order->purchaseRequest->project_name)->with('company')->first()?->company
            : null;

        $subtotal  = (float) $order->items->sum('total_amount');
        $vatRate   = (float) Setting::get('vat_rate', 0);
        $vatAmount = $vatRate > 0 ? round($subtotal * $vatRate / 100, 3) : 0;
        $discount  = 0;
        $total     = $subtotal + $vatAmount - $discount;

        return compact('order', 'company', 'subtotal', 'vatRate', 'vatAmount', 'discount', 'total');
    }

    public function edit(PurchaseOrder $order)
    {
        $suppliers = Supplier::all();
        $items     = Item::all();

        return view('purchase.orders.edit', compact('order', 'suppliers', 'items'));
    }

    public function update(Request $request, PurchaseOrder $order)
    {
        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'po_date'     => 'required|date',
        ]);

        $order->update($request->only('supplier_id', 'po_date', 'status'));

        return redirect()->route('purchase.orders.show', $order)->with('success', 'Purchase order updated successfully.');
    }

    public function destroy(PurchaseOrder $order)
    {
        $order->delete();

        return redirect()->route('purchase.orders.index')->with('success', 'Purchase order deleted successfully.');
    }
}
