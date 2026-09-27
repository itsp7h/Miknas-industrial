<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RequesterResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $companies = $this->companies->sortBy('name')->values();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'company_ids' => $companies->pluck('id')->values(),
            'companies' => $companies->map(fn ($company) => [
                'id' => $company->id,
                'name' => $company->name,
            ])->values(),
        ];
    }
}
