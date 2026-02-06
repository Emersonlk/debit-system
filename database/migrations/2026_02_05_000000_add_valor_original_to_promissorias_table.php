<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Armazena o valor original da promissória quando há pagamento parcial,
     * permitindo que o campo 'valor' seja reduzido (saldo restante).
     */
    public function up(): void
    {
        Schema::table('promissorias', function (Blueprint $table) {
            $table->decimal('valor_original', 10, 2)->nullable()->after('valor');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('promissorias', function (Blueprint $table) {
            $table->dropColumn('valor_original');
        });
    }
};
