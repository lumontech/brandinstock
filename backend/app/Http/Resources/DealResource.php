<?php

namespace App\Http\Resources;

use App\Models\Deal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Deal */
class DealResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'value' => (float) $this->value,
            'currency' => $this->currency,
            'brand' => $this->brand,
            'product_category' => $this->product_category,
            'quantity' => $this->quantity,
            'expected_close_date' => $this->expected_close_date?->toDateString(),
            'source' => $this->source,
            'lost_reason' => $this->lost_reason,
            'notes' => $this->notes,
            'pipeline_stage_id' => $this->pipeline_stage_id,
            'company_id' => $this->company_id,
            'contact_id' => $this->contact_id,
            'stage' => new StageResource($this->whenLoaded('stage')),
            'company' => $this->whenLoaded('company', fn () => ['id' => $this->company->id, 'name' => $this->company->name]),
            'contact' => $this->whenLoaded('contact', fn () => $this->contact ? ['id' => $this->contact->id, 'name' => trim($this->contact->first_name.' '.$this->contact->last_name)] : null),
            'owner' => new OwnerResource($this->whenLoaded('owner')),
            'activities' => ActivityResource::collection($this->whenLoaded('activities')),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
