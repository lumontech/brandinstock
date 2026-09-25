<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 40)->index();
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('changes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['auditable_type', 'auditable_id']);
        });

        // Registro append-only: l'utente applicativo del database non può
        // modificare né cancellare le righe di audit (vedi deploy/postgres).
        $appRole = config('database.app_role');
        $connection = DB::connection();
        if ($connection->getDriverName() === 'pgsql' && $appRole && $appRole !== $connection->getConfig('username')) {
            DB::statement(sprintf('REVOKE UPDATE, DELETE, TRUNCATE ON audit_logs FROM %s', DB::getQueryGrammar()->wrap($appRole)));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
