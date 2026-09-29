<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // Ciclo di vita: "lead" finché non si vince un'opportunità, poi "customer".
            $table->string('status', 20)->default('lead')->index();
            $table->timestamp('converted_at')->nullable();

            // Dati di fatturazione (clienti). I dati sensibili sono cifrati dal model.
            $table->string('billing_name')->nullable();
            $table->text('billing_address')->nullable();
            $table->string('billing_zip', 10)->nullable();
            $table->string('billing_city', 100)->nullable();
            $table->string('billing_province', 10)->nullable();
            $table->string('billing_country', 2)->nullable();
            $table->string('sdi_code', 7)->nullable();
            $table->text('pec')->nullable();
            $table->text('iban')->nullable();
            $table->string('payment_terms', 100)->nullable();
            $table->text('billing_notes')->nullable();
        });

        // I clienti che hanno già un'opportunità vinta diventano "customer".
        $wonStages = DB::table('pipeline_stages')->where('is_won', true)->pluck('id');
        if ($wonStages->isNotEmpty()) {
            $customers = DB::table('deals')->whereIn('pipeline_stage_id', $wonStages)->whereNull('deleted_at')->distinct()->pluck('company_id');
            DB::table('companies')->whereIn('id', $customers)->update(['status' => 'customer', 'converted_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn([
                'status', 'converted_at', 'billing_name', 'billing_address', 'billing_zip', 'billing_city',
                'billing_province', 'billing_country', 'sdi_code', 'pec', 'iban', 'payment_terms', 'billing_notes',
            ]);
        });
    }
};
