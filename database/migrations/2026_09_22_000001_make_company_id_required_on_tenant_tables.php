<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Torna company_id obrigatório nas tabelas de negócio e impede que uma empresa com
 * dados seja excluída.
 *
 * Até aqui a garantia era só de aplicação: o hook `creating` do BelongsToCompany
 * preenche a coluna e falha sem contexto. NOT NULL transforma isso em garantia de
 * banco.
 *
 * A FK sai de SET NULL para RESTRICT porque SET NULL, combinado com o global scope,
 * transformaria os dados de uma empresa excluída em registros órfãos — invisíveis
 * para todos, porém ainda no banco. RESTRICT força um processo explícito de
 * offboarding antes de remover a empresa.
 *
 * users e audit_logs ficam de fora por decisão arquitetural: Super Admin tem
 * company_id nulo, e o AuditService grava log órfão quando a empresa não é
 * determinável (2B.6.3).
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const TABELAS = ['clientes', 'promissorias', 'historico_pagamentos'];

    public function up(): void
    {
        // Precondição antes de qualquer DDL: DDL no MySQL não é transacional, então
        // validar as três tabelas primeiro evita parar no meio da migration.
        foreach (self::TABELAS as $tabela) {
            $orfaos = DB::table($tabela)->whereNull('company_id')->count();

            if ($orfaos > 0) {
                throw new RuntimeException(
                    "A tabela {$tabela} possui {$orfaos} registro(s) com company_id nulo. "
                    . 'Atribua a empresa correta a esses registros antes de aplicar NOT NULL.'
                );
            }
        }

        foreach (self::TABELAS as $tabela) {
            // 1. A FK sai primeiro: o MySQL não permite alterar a nulabilidade de uma
            //    coluna sob foreign key. O índice de mesmo nome sobrevive ao drop e é
            //    reaproveitado pela FK recriada — não há rebuild de índice.
            Schema::table($tabela, function (Blueprint $table) {
                $table->dropForeign(['company_id']);
            });

            // 2. Coluna redeclarada por inteiro: no Laravel 11+ o change() descarta
            //    atributos que não forem declarados explicitamente.
            Schema::table($tabela, function (Blueprint $table) {
                $table->unsignedBigInteger('company_id')->nullable(false)->change();
            });

            // 3. FK de volta, agora bloqueando a exclusão de empresa com dados.
            Schema::table($tabela, function (Blueprint $table) {
                $table->foreign('company_id')
                    ->references('id')
                    ->on('companies')
                    ->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        // Ordem inversa. Relaxar a restrição nunca falha por conteúdo existente,
        // então não há precondição a verificar aqui.
        foreach (self::TABELAS as $tabela) {
            Schema::table($tabela, function (Blueprint $table) {
                $table->dropForeign(['company_id']);
            });

            Schema::table($tabela, function (Blueprint $table) {
                $table->unsignedBigInteger('company_id')->nullable()->change();
            });

            Schema::table($tabela, function (Blueprint $table) {
                $table->foreign('company_id')
                    ->references('id')
                    ->on('companies')
                    ->nullOnDelete();
            });
        }
    }
};
