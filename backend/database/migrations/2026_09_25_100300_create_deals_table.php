<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deals', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('pipeline_stage_id')->constrained()->restrictOnDelete();
            $table->decimal('value', 14, 2)->default(0);
            $table->string('currency', 3)->default('EUR');
            $table->string('brand', 100)->nullable();
            $table->string('product_category', 100)->nullable();
            $table->unsignedInteger('quantity')->nullable();
            $table->date('expected_close_date')->nullable();
            $table->string('source', 50)->nullable();
            $table->string('lost_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('expected_close_date');
            $table->index(['owner_id', 'pipeline_stage_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deals');
    }
};
