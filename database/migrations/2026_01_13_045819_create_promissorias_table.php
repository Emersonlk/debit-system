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
        Schema::create('promissorias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->onDelete('cascade');
            $table->decimal('valor', 10, 2);
            $table->date('data_vencimento');
            $table->enum('status', ['pendente', 'paga', 'vencida'])->default('pendente');
            $table->text('observacoes')->nullable();
            $table->boolean('notificado')->default(false);
            $table->timestamp('data_pagamento')->nullable();
            $table->timestamps();
            
            $table->index('data_vencimento');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promissorias');
    }
};
