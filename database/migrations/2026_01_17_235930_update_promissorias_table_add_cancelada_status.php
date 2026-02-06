<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Atualiza o enum para incluir 'cancelada'
        DB::statement("ALTER TABLE promissorias MODIFY COLUMN status ENUM('pendente', 'paga', 'vencida', 'cancelada') DEFAULT 'pendente'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverte para o enum original sem 'cancelada'
        DB::statement("ALTER TABLE promissorias MODIFY COLUMN status ENUM('pendente', 'paga', 'vencida') DEFAULT 'pendente'");
    }
};
