<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait OwnedByUser
{
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** Limita la query ai record visibili all'utente (i venditori vedono solo i propri). */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->seesEverything() ? $query : $query->where($this->qualifyColumn('owner_id'), $user->id);
    }
}
