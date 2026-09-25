<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            $table->string('vat_number', 32)->nullable()->unique();
            $table->string('type', 30)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('province', 10)->nullable();
            $table->string('country', 2)->default('IT');
            // Campi cifrati a livello applicativo (cast "encrypted").
            $table->text('address')->nullable();
            $table->text('email')->nullable();
            $table->text('phone')->nullable();
            $table->text('notes')->nullable();
            $table->string('website')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
