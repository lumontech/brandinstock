<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;

/**
 * Verifica che l'ID esista E sia visibile all'utente corrente: impedisce di collegare
 * (e quindi scoprire) record di altri venditori passando ID arbitrari (IDOR).
 */
class VisibleRecord implements ValidationRule
{
    /** @param  class-string<Model>  $model */
    public function __construct(private readonly string $model, private readonly User $user) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_numeric($value) || ! $this->model::query()->visibleTo($this->user)->whereKey((int) $value)->exists()) {
            $fail('Il record selezionato non è valido.');
        }
    }
}
