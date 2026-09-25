<?php

namespace App\Http\Resources;

use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Activity */
class ActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'subject' => $this->subject,
            'description' => $this->description,
            'deal_id' => $this->deal_id,
            'company_id' => $this->company_id,
            'contact_id' => $this->contact_id,
            'deal' => $this->whenLoaded('deal', fn () => $this->deal ? ['id' => $this->deal->id, 'title' => $this->deal->title] : null),
            'company' => $this->whenLoaded('company', fn () => $this->company ? ['id' => $this->company->id, 'name' => $this->company->name] : null),
            'user' => new OwnerResource($this->whenLoaded('user')),
            'due_at' => $this->due_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'is_overdue' => $this->completed_at === null && $this->due_at?->isPast(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
