<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'position', 'probability', 'color', 'is_won', 'is_lost'])]
class PipelineStage extends Model
{
    use Auditable, HasFactory;

    protected function casts(): array
    {
        return ['is_won' => 'boolean', 'is_lost' => 'boolean', 'probability' => 'integer', 'position' => 'integer'];
    }

    public function isClosed(): bool
    {
        return $this->is_won || $this->is_lost;
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }
}
