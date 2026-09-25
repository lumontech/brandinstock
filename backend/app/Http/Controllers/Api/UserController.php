<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', User::class);

        return UserResource::collection(User::orderBy('name')->get());
    }

    /** Elenco minimo (id, nome) dei venditori attivi, per filtri e assegnazioni. */
    public function options(): JsonResponse
    {
        return response()->json([
            'data' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', User::class);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = new User([
            'name' => $data['name'],
            'email' => mb_strtolower($data['email']),
            'password' => $data['password'],
        ]);
        $user->role = UserRole::from($data['role']);
        $user->is_active = true;
        $user->save();

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    public function update(Request $request, User $user): UserResource
    {
        Gate::authorize('update', $user);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'role' => ['sometimes', Rule::enum(UserRole::class)],
            'is_active' => ['sometimes', 'boolean'],
            'password' => ['sometimes', 'confirmed', Password::defaults()],
            'reset_two_factor' => ['sometimes', 'boolean'],
        ]);

        // Un admin non può togliersi da solo i privilegi o disattivarsi (evita di restare senza amministratori).
        if ($request->user()->is($user) && ((isset($data['role']) && $data['role'] !== UserRole::Admin->value) || ($data['is_active'] ?? true) === false)) {
            abort(422, 'Non puoi modificare il tuo ruolo o disattivare il tuo account.');
        }

        $user->fill(array_intersect_key($data, array_flip(['name', 'email', 'password'])));
        if (isset($data['email'])) {
            $user->email = mb_strtolower($data['email']);
        }
        if (isset($data['role'])) {
            $user->role = UserRole::from($data['role']);
        }
        if (isset($data['is_active'])) {
            $user->is_active = $data['is_active'];
        }
        if (! empty($data['reset_two_factor'])) {
            $user->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null, 'two_factor_recovery_codes' => null]);
        }
        $user->save();

        // Cambio password o disattivazione: chiude subito tutte le sessioni dell'utente.
        if (isset($data['password']) || ($data['is_active'] ?? true) === false) {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }

        return new UserResource($user);
    }
}
