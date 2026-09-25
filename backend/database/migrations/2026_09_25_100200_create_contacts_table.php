<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('first_name', 100);
            $table->string('last_name', 100)->nullable();
            $table->string('job_title', 100)->nullable();
            // Dati personali cifrati; email_hash è un indice "cieco" (HMAC) per ricerca e unicità.
            $table->text('email')->nullable();
            $table->string('email_hash', 64)->nullable()->index();
            $table->text('phone')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('marketing_consent')->default(false);
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
