<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseRequestBoardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'request_number' => $this->request_number,
            'date' => $this->date?->toDateString(),
            'company_name' => $this->company_name,
            'department' => $this->department,
            'requested_by_name' => $this->requested_by_name ?? $this->requestedBy?->name,
            'stage' => $this->stage,
            // The board filters rows by owner for a view-own user, and it applies
            // that filter to this payload as well as to the broadcast, so the two
            // have to carry the same field.
            'requested_by_id' => $this->requested_by,
            'remarks' => $this->remarks,
        ];
    }
}
