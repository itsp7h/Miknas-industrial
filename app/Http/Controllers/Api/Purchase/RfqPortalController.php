<?php

namespace App\Http\Controllers\Api\Purchase;

use App\Events\NotificationPushed;
use App\Http\Controllers\Controller;
use App\Http\Resources\RfqPortalResource;
use App\Models\RfqInvitation;
use App\Models\Setting;
use App\Models\SupplierQuote;
use App\Models\SupplierQuoteItem;
use App\Models\User;
use App\Notifications\QuoteReceived;
use App\Services\PurchaseStageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The public quote portal's JSON API — no auth, the token is the credential.
 *
 * The Blade portal decided desktop-versus-mobile by sniffing the user agent
 * and shipped two hand-written pages behind that guess; the React portal
 * picks with `useViewport()` and re-picks on rotation, so all this side has
 * to answer is "what is the state of this invitation and what may this
 * supplier price".
 */
class RfqPortalController extends Controller
{
    private function resolve(string $token): RfqInvitation
    {
        return RfqInvitation::where('token', $token)
            ->with(['purchaseRequest.items', 'supplier', 'quote'])
            ->firstOrFail();
    }

    public function show(Request $request, string $token)
    {
        $invitation = $this->resolve($token);

        if ($invitation->isSubmitted()) {
            return $this->payload($invitation, 'submitted');
        }

        if ($invitation->isExpired()) {
            return $this->payload($invitation, 'expired');
        }

        if ($invitation->status === 'sent') {
            $invitation->update(['status' => 'opened', 'opened_at' => now()]);
        }

        return $this->payload($invitation, 'open', $this->confirmCode($request, $token));
    }

    public function submit(Request $request, string $token)
    {
        $invitation = $this->resolve($token);

        if ($invitation->isSubmitted() || $invitation->isExpired()) {
            return response()->json(['message' => 'This link is no longer valid.'], 403);
        }

        $validated = $request->validate([
            'terms' => ['accepted'],
            'confirm_code' => ['required', 'string'],
            'lead_time_days' => ['nullable', 'integer', 'min:0'],
            'payment_terms' => ['nullable', 'string', 'max:200'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.is_vatable' => ['nullable', 'boolean'],
            'items.*.not_available' => ['nullable', 'boolean'],
            'items.*.supplier_description' => ['nullable', 'string', 'max:500'],
        ], [
            'terms.accepted' => 'Please accept the terms and conditions before submitting.',
        ]);

        $expected = $request->session()->get($this->sessionKey($token));

        if (! $expected || strtoupper(trim($validated['confirm_code'])) !== $expected) {
            return response()->json([
                'message' => 'Incorrect confirmation code.',
                'errors' => ['confirm_code' => ['Incorrect confirmation code. Please copy the code exactly as shown.']],
            ], 422);
        }

        $quote = $this->recordQuote($invitation, $validated);

        $request->session()->forget($this->sessionKey($token));

        $this->announce($invitation);

        return $this->payload(
            $invitation->fresh(['purchaseRequest.items', 'supplier', 'quote']),
            'submitted',
            extra: ['message' => 'Your quote has been submitted. Thank you.'],
        )->response()->setStatusCode(201);
    }

    /**
     * The whole quote in one transaction: the header, its lines, the
     * invitation's new status. Half a quote — lines written, invitation still
     * open — would let the supplier submit again over the top of it.
     */
    private function recordQuote(RfqInvitation $invitation, array $validated): SupplierQuote
    {
        $vatRate = $this->vatRate();
        $posted = collect($validated['items'])->keyBy('id');

        return DB::transaction(function () use ($invitation, $validated, $vatRate, $posted) {
            $quote = SupplierQuote::create([
                'rfq_invitation_id' => $invitation->id,
                'purchase_request_id' => $invitation->purchase_request_id,
                'supplier_id' => $invitation->supplier_id,
                'submitted_at' => now(),
                'lead_time_days' => $validated['lead_time_days'] ?? null,
                'payment_terms' => $validated['payment_terms'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'total_amount' => 0,
            ]);

            $subtotal = 0;
            $vatAmount = 0;

            // The invited items drive the loop, not the payload: a line the
            // supplier never sent is a line they did not price, and a line
            // they invented is not part of this invitation.
            foreach ($invitation->quotedItems() as $item) {
                $row = $posted->get($item->id, []);

                $notAvailable = ! empty($row['not_available']);
                $unitPrice = $notAvailable ? 0 : (float) ($row['unit_price'] ?? 0);
                $qty = (float) $item->quantity_required;
                $totalPrice = $notAvailable ? 0 : round($unitPrice * $qty, 3);
                $isVatable = ! $notAvailable && ! empty($row['is_vatable']);

                $subtotal += $totalPrice;

                if ($isVatable && $vatRate > 0) {
                    $vatAmount += round($totalPrice * $vatRate / 100, 3);
                }

                SupplierQuoteItem::create([
                    'supplier_quote_id' => $quote->id,
                    'purchase_request_item_id' => $item->id,
                    'description' => $item->description,
                    'supplier_description' => filled($row['supplier_description'] ?? null)
                        ? trim($row['supplier_description'])
                        : null,
                    'unit' => $item->unit ?? '',
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'total_price' => $totalPrice,
                    'is_vatable' => $isVatable,
                    'not_available' => $notAvailable,
                ]);
            }

            $quote->update(['total_amount' => round($subtotal + $vatAmount, 3)]);
            $invitation->update(['status' => 'submitted']);

            // One quote in is enough to start comparing.
            if ($invitation->purchaseRequest->stage === 'quoting') {
                app(PurchaseStageService::class)->setStage($invitation->purchaseRequest, 'comparison');
            }

            return $quote;
        });
    }

    /** Tell the buyers, in the bell and live over Reverb. */
    private function announce(RfqInvitation $invitation): void
    {
        $invitation->load('supplier', 'purchaseRequest');

        User::role('Admin')->each(function ($user) use ($invitation) {
            $user->notify(new QuoteReceived($invitation));

            $notification = $user->notifications()->latest()->first();

            if ($notification) {
                event(new NotificationPushed(
                    $user->id,
                    'New Quote Received',
                    $notification->data['message'] ?? '',
                    $notification->data['url'] ?? null,
                    $notification->id,
                    optional($notification->created_at)->toIso8601String(),
                ));
            }
        });
    }

    /**
     * Stable for the life of the session rather than regenerated per read.
     * React fetches this screen on mount — twice under StrictMode — and a
     * fresh code each time would leave whichever response painted last
     * disagreeing with whichever the server stored last.
     */
    private function confirmCode(Request $request, string $token): string
    {
        $key = $this->sessionKey($token);

        if (! $request->session()->has($key)) {
            $request->session()->put($key, strtoupper(substr(bin2hex(random_bytes(3)), 0, 5)));
        }

        return $request->session()->get($key);
    }

    private function sessionKey(string $token): string
    {
        return 'rfq_confirm_'.$token;
    }

    private function vatRate(): float
    {
        return (float) Setting::get('vat_rate', 0);
    }

    private function payload(RfqInvitation $invitation, string $state, ?string $confirmCode = null, array $extra = []): RfqPortalResource
    {
        return RfqPortalResource::make($invitation)->additional(array_merge(array_filter([
            'state' => $state,
            'vat_rate' => $state === 'open' ? $this->vatRate() : null,
            'confirm_code' => $confirmCode,
        ], fn ($value) => $value !== null), $extra));
    }
}
