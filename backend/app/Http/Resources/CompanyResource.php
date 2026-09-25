<?php

namespace App\Http\Resources;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Company */
class CompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'vat_number' => $this->vat_number,
            'type' => $this->type,
            'city' => $this->city,
            'province' => $this->province,
            'country' => $this->country,
            'address' => $this->address,
            'email' => $this->email,
            'phone' => $this->phone,
            'website' => $this->website,
            'notes' => $this->notes,
            'owner' => new OwnerResource($this->whenLoaded('owner')),
            'deals_count' => $this->whenCounted('deals'),
            'open_deals_value' => $this->when(isset($this->open_deals_value), fn () => (float) $this->open_deals_value),
            'contacts' => ContactResource::collection($this->whenLoaded('contacts')),
            'deals' => DealResource::collection($this->whenLoaded('deals')),
            'activities' => ActivityResource::collection($this->whenLoaded('activities')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
