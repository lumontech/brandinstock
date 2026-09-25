<?php

namespace App\Http\Resources;

use App\Models\PipelineStage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PipelineStage */
class StageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'position' => $this->position,
            'probability' => $this->probability,
            'color' => $this->color,
            'is_won' => $this->is_won,
            'is_lost' => $this->is_lost,
        ];
    }
}
