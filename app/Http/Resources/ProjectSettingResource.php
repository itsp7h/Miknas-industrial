<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'is_active' => (bool) $this->is_active,
            'company_id' => $this->company_id,
            'company_name' => $this->whenLoaded('company', fn () => $this->company?->name),
            'locations' => $this->whenLoaded('locations', fn () => $this->locations->map(fn ($location) => [
                'id' => $location->id,
                'name' => $location->name,
                'address' => $location->address,
                // Cast so the page can format them; SQLite hands back strings.
                'latitude' => $location->latitude !== null ? (float) $location->latitude : null,
                'longitude' => $location->longitude !== null ? (float) $location->longitude : null,
                'is_active' => (bool) $location->is_active,
            ])->values()),
        ];
    }
}
