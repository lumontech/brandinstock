<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // Categoria commerciale del cliente: b2b, b2c, franchising.
            $table->string('segment', 20)->default('b2b')->index()->after('name');
            // Codice fiscale (clienti privati): dato personale, cifrato dal model.
            $table->text('tax_code')->nullable()->after('vat_number');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropIndex(['segment']);
            $table->dropColumn(['segment', 'tax_code']);
        });
    }
};
