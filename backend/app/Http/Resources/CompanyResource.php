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
            'source' => $this->source,
            'lead_status' => $this->lead_status,
            'status' => $this->status,
            'converted_at' => $this->converted_at?->toIso8601String(),
            'billing_name' => $this->billing_name,
            'billing_address' => $this->billing_address,
            'billing_zip' => $this->billing_zip,
            'billing_city' => $this->billing_city,
            'billing_province' => $this->billing_province,
            'billing_country' => $this->billing_country,
            'sdi_code' => $this->sdi_code,
            'pec' => $this->pec,
            'iban' => $this->iban,
            'payment_terms' => $this->payment_terms,
            'billing_notes' => $this->billing_notes,
            'billing_complete' => $this->hasCompleteBilling(),
            'won_value' => $this->whenHas('won_value', fn ($value) => (float) $value),
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
            'contact_person' => $this->contact_person,
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
