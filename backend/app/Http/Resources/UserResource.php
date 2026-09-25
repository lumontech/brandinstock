<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
            'role_label' => $this->role->label(),
            'is_active' => $this->is_active,
            'two_factor_enabled' => $this->hasTwoFactorEnabled(),
            'two_factor_required' => $this->requiresTwoFactor(),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
        ];
    }
}
