<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('historico_pagamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promissoria_id')->constrained('promissorias')->onDelete('cascade');
            $table->decimal('valor_pago', 10, 2);
            $table->date('data_pagamento');
            $table->text('observacoes')->nullable();
            $table->timestamps();
            
            $table->index('promissoria_id');
            $table->index('data_pagamento');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('historico_pagamentos');
    }
};
