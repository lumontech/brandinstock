<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

trait ResolvesOwner
{
    /**
     * Owner del record: il venditore è sempre owner dei propri record; admin e manager
     * possono assegnarlo a un altro utente attivo tramite "owner_id".
     *
     * @param  class-string  $model
     */
    protected function resolveOwner(Request $request, string $model): int
    {
        if (! Gate::allows('assign', $model) || ! $request->filled('owner_id')) {
            return $request->user()->id;
        }

        $request->validate(['owner_id' => ['integer', Rule::exists(User::class, 'id')->where('is_active', true)]]);

        return (int) $request->input('owner_id');
    }
}
