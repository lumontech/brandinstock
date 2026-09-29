<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Crea un amministratore. La password si inserisce in modo interattivo (non passa mai da
 * argomenti o dalla history della shell) oppure viene generata con --generate-password.
 */
class CreateAdmin extends Command
{
    protected $signature = 'crm:create-admin {email} {--name=Amministratore} {--generate-password : Genera una password casuale e la stampa una sola volta}';

    protected $description = 'Crea un utente amministratore del CRM';

    public function handle(): int
    {
        if ($this->option('generate-password')) {
            $password = $confirm = $this->generatePassword();
        } else {
            $password = $this->secret('Password (min. 12 caratteri, maiuscole, minuscole e numeri)');
            $confirm = $this->secret('Conferma password');
        }

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
        if ($this->option('generate-password')) {
            // Riga in formato fisso, letta dallo script di installazione.
            $this->line("GENERATED_PASSWORD={$password}");
        }

        return self::SUCCESS;
    }

    private function generatePassword(): string
    {
        // Senza simboli ambigui da copiare; garantisce maiuscole, minuscole e numeri.
        do {
            $password = Str::password(20, symbols: false);
        } while (! preg_match('/[a-z]/', $password) || ! preg_match('/[A-Z]/', $password) || ! preg_match('/\d/', $password));

        return $password;
    }
}
