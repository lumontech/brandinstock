<?php

namespace App\Http\Resources;

use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Contact */
class ContactResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'company' => $this->whenLoaded('company', fn () => ['id' => $this->company->id, 'name' => $this->company->name]),
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => trim($this->first_name.' '.$this->last_name),
            'job_title' => $this->job_title,
            'email' => $this->email,
            'phone' => $this->phone,
            'notes' => $this->notes,
            'marketing_consent' => $this->marketing_consent,
            'owner' => new OwnerResource($this->whenLoaded('owner')),
        ];
    }
}
