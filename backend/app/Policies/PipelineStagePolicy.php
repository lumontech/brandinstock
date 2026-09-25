<?php

namespace App\Policies;

use App\Models\User;

class PipelineStagePolicy
{
    public function manage(User $user): bool
    {
        return $user->isAdmin();
    }
}
