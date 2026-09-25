<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/** Crea il primo amministratore in modo interattivo (la password non passa mai da argomenti o history della shell). */
class CreateAdmin extends Command
{
    protected $signature = 'crm:create-admin {email} {--name=Amministratore}';

    protected $description = 'Crea un utente amministratore del CRM';

    public function handle(): int
    {
        $password = $this->secret('Password (min. 12 caratteri, maiuscole, minuscole e numeri)');
        $confirm = $this->secret('Conferma password');

        $validator = Validator::make(
            ['email' => $this->argument('email'), 'password' => $password, 'password_confirmation' => $confirm],
            ['email' => ['required', 'email', 'unique:users,email'], 'password' => ['required', 'confirmed', Password::defaults()]],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = new User(['name' => $this->option('name'), 'email' => mb_strtolower($this->argument('email')), 'password' => $password]);
        $user->role = UserRole::Admin;
        $user->is_active = true;
        $user->save();

        $this->info("Amministratore {$user->email} creato. Al primo accesso dovrà attivare la 2FA.");

        return self::SUCCESS;
    }
}
