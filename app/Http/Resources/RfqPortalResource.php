<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Everything the public quote portal renders, for all three of its states.
 *
 * The portal is the one screen in the app a supplier sees, and it is
 * unauthenticated — so this deliberately carries the invitation's own
 * context and nothing else about the request. No internal notes, no other
 * suppliers, no pricing history: a token is a weak credential and the payload
 * behind it is sized accordingly.
 *
 * `state` (open | submitted | expired) and, when open, `confirm_code` and
 * `vat_rate` ride along via `additional()` — they are facts about this visit
 * rather than about the invitation record.
 */
class RfqPortalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $purchaseRequest = $this->purchaseRequest;

        return [
            'token' => $this->token,
            'supplier_name' => $this->supplier->name,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'expires_at_text' => $this->expires_at?->format('d M Y'),
            'submitted_at_text' => $this->quote?->submitted_at?->format('d M Y, H:i'),

            'request' => [
                'request_number' => $purchaseRequest->request_number,
                'project_name' => $purchaseRequest->project_name,
            ],

            // Keyed by id rather than positional, because the submit side pairs
            // prices back to items by id. The Blade form matched on array
            // position, which silently mispriced the whole quote if the two
            // lists ever fell out of step.
            'items' => $this->quotedItems()->map(fn ($item) => [
                'id' => $item->id,
                'description' => $item->description,
                'unit' => $item->unit,
                'quantity_required' => (float) $item->quantity_required,
            ])->values(),
        ];
    }
}
