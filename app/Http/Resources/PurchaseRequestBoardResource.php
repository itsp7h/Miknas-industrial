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
            'date' => $this->date,
            'project_name' => $this->project_name,
            'department' => $this->department,
            'requested_by_name' => $this->requested_by_name ?? $this->requestedBy?->name,
            'stage' => $this->stage,
            'remarks' => $this->remarks,
        ];
    }
}
