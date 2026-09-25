<?php

namespace App\Policies;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ActivityPolicy extends OwnedRecordPolicy
{
    protected string $ownerColumn = 'user_id';

    /** Chi ha creato l'attività può anche eliminarla. */
    public function delete(User $user, Model $record): bool
    {
        /** @var Activity $record */
        return $this->owns($user, $record);
    }
}
