<?php

namespace App\Models;

use App\Enums\ActivityType;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['type', 'subject', 'description', 'deal_id', 'company_id', 'contact_id', 'due_at'])]
class Activity extends Model
{
    use Auditable, HasFactory;

    protected function casts(): array
    {
        return [
            'type' => ActivityType::class,
            'description' => 'encrypted',
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->seesEverything() ? $query : $query->where('user_id', $user->id);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
