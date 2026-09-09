<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'is_active' => (bool) $this->is_active,
            // The card header counts departments, and deletion is refused while
            // projects still belong to the company, so the page needs both.
            'project_count' => $this->whenCounted('projects'),
            'departments' => $this->whenLoaded('departments', fn () => $this->departments->map(fn ($department) => [
                'id' => $department->id,
                'name' => $department->name,
                'is_active' => (bool) $department->is_active,
            ])->values()),
        ];
    }
}
