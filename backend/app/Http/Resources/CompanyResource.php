<?php

namespace App\Http\Resources;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/** @mixin Company */
class CompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'segment' => $this->segment,
            'vat_number' => $this->vat_number,
            'tax_code' => $this->tax_code,
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
            'contacts_count' => $this->whenCounted('contacts'),
            'open_deals_value' => $this->whenHas('open_deals_value', fn ($value) => (float) $value),
            'last_activity_at' => $this->whenHas('last_activity_at', fn ($value) => $value ? Carbon::parse($value)->toIso8601String() : null),
            'contacts' => ContactResource::collection($this->whenLoaded('contacts')),
            'deals' => DealResource::collection($this->whenLoaded('deals')),
            'activities' => ActivityResource::collection($this->whenLoaded('activities')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
