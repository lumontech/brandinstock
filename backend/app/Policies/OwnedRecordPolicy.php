<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Regole comuni: admin e manager gestiscono tutto, il venditore solo i record di cui è owner.
 * L'eliminazione è riservata ad admin e manager (i dati commerciali non si perdono per errore).
 */
abstract class OwnedRecordPolicy
{
    protected string $ownerColumn = 'owner_id';

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, Model $record): bool
    {
        return $this->owns($user, $record);
    }

    public function update(User $user, Model $record): bool
    {
        return $this->owns($user, $record);
    }

    public function delete(User $user, Model $record): bool
    {
        return $user->seesEverything();
    }

    /** Solo admin e manager possono assegnare un record a un altro venditore. */
    public function assign(User $user): bool
    {
        return $user->seesEverything();
    }

    protected function owns(User $user, Model $record): bool
    {
        return $user->seesEverything() || (int) $record->getAttribute($this->ownerColumn) === $user->id;
    }
}
