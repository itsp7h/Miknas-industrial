<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Models\RfqInvitation;
use App\Models\Setting;
use App\Models\SupplierQuote;
use App\Models\SupplierQuoteItem;
use App\Models\User;
use App\Notifications\QuoteReceived;
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
        $itemIds         = $invitation->item_ids;
        $items           = $itemIds
            ? $purchaseRequest->items->whereIn('id', $itemIds)->values()
            : $purchaseRequest->items;

        // Generate a fresh confirmation code per page load and store in session
        $confirmCode = strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
        session(['rfq_confirm_' . $token => $confirmCode]);

        $vatRate = (float) Setting::get('vat_rate', 0);

        return view('rfq.show', compact('invitation', 'purchaseRequest', 'items', 'confirmCode', 'vatRate'));
    }

    public function submit(Request $request, string $token)
    {
        $invitation = $this->resolve($token);

        if ($invitation->isSubmitted() || $invitation->isExpired()) {
            abort(403, 'This link is no longer valid.');
        }

        $validated = $request->validate([
            'terms'                          => ['accepted'],
            'confirm_code'                   => ['required', 'string'],
            'lead_time_days'                 => ['nullable', 'integer', 'min:0'],
            'payment_terms'                  => ['nullable', 'string', 'max:200'],
            'notes'                          => ['nullable', 'string', 'max:1000'],
            'items'                          => ['required', 'array'],
            'items.*.unit_price'             => ['nullable', 'numeric', 'min:0'],
            'items.*.is_vatable'             => ['nullable', 'boolean'],
            'items.*.not_available'          => ['nullable', 'boolean'],
            'items.*.supplier_description'   => ['nullable', 'string', 'max:500'],
        ]);

        $expectedCode = session('rfq_confirm_' . $token);
        if (!$expectedCode || strtoupper(trim($validated['confirm_code'])) !== $expectedCode) {
            return back()->withErrors(['confirm_code' => 'Incorrect confirmation code. Please copy the code exactly as shown.'])->withInput();
        }
        session()->forget('rfq_confirm_' . $token);

        $itemIds       = $invitation->item_ids;
        $purchaseItems = $itemIds
            ? $invitation->purchaseRequest->items->whereIn('id', $itemIds)->values()
            : $invitation->purchaseRequest->items;

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

        $subtotal  = 0;
        $vatAmount = 0;
        $vatRate   = (float) Setting::get('vat_rate', 0);

        foreach ($purchaseItems as $i => $item) {
            $notAvailable       = !empty($validated['items'][$i]['not_available']);
            $unitPrice          = $notAvailable ? 0 : (float)($validated['items'][$i]['unit_price'] ?? 0);
            $qty                = (float)$item->quantity_required;
            $totalPrice         = $notAvailable ? 0 : round($unitPrice * $qty, 3);
            $isVatable          = !$notAvailable && !empty($validated['items'][$i]['is_vatable']);
            $supplierDescription = !empty($validated['items'][$i]['supplier_description'])
                ? trim($validated['items'][$i]['supplier_description'])
                : null;

            $subtotal += $totalPrice;

            if ($isVatable && $vatRate > 0) {
                $vatAmount += round($totalPrice * $vatRate / 100, 3);
            }

            SupplierQuoteItem::create([
                'supplier_quote_id'         => $quote->id,
                'purchase_request_item_id'  => $item->id,
                'description'         => $item->description,
                'supplier_description'=> $supplierDescription,
                'unit'                => $item->unit ?? '',
                'quantity'            => $qty,
                'unit_price'          => $unitPrice,
                'total_price'         => $totalPrice,
                'is_vatable'          => $isVatable,
                'not_available'       => $notAvailable,
            ]);
        }

        $quote->update(['total_amount' => round($subtotal + $vatAmount, 3)]);
        $invitation->update(['status' => 'submitted']);

        // If at least 1 quote is in, move to comparison stage
        $pr = $invitation->purchaseRequest;
        if ($pr->stage === 'quoting') {
            app(PurchaseStageService::class)->setStage($pr, 'comparison');
        }

        // Notify all admin users
        $invitation->load('supplier', 'purchaseRequest');
        User::role('Admin')->each(fn($u) => $u->notify(new QuoteReceived($invitation)));

        return view('rfq.submitted', compact('invitation'));
    }
}
