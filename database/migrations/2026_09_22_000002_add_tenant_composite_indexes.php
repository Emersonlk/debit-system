<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices compostos com company_id à frente, derivados das consultas reais.
 *
 * Os índices simples herdados do sistema single-tenant começam na coluna errada:
 * como o global scope garante que toda consulta da aplicação filtra por empresa,
 * um índice em `status` ou `data_vencimento` sozinho obriga o MySQL a varrer as
 * linhas das demais empresas antes de descartá-las.
 *
 * A coluna de ordenação/range vem sempre por último. Foi verificado que mover
 * `notificado` para antes de `data_vencimento` — tentador, porque o scheduler
 * filtra por ele — devolve a ordenação ao filesort e custa muito mais do que
 * economiza. Os índices simples atuais permanecem: a remoção será avaliada à
 * parte, com dados de uso real.
 */
return new class extends Migration
{
    /**
     * Índices criados, na forma [tabela => [nome => colunas]].
     *
     * @var array<string, array<string, list<string>>>
     */
    private const INDICES = [
        'clientes' => [
            'clientes_company_email_index' => ['company_id', 'email'],
            'clientes_company_cpf_index' => ['company_id', 'cpf'],
            'clientes_company_nome_index' => ['company_id', 'nome'],
        ],
        'promissorias' => [
            'promissorias_company_status_vencimento_index' => ['company_id', 'status', 'data_vencimento'],
            'promissorias_company_status_pagamento_index' => ['company_id', 'status', 'data_pagamento'],
            'promissorias_company_vencimento_index' => ['company_id', 'data_vencimento'],
        ],
        'historico_pagamentos' => [
            'historico_pagamentos_company_pagamento_index' => ['company_id', 'data_pagamento'],
        ],
    ];

    public function up(): void
    {
        foreach (self::INDICES as $tabela => $indices) {
            Schema::table($tabela, function (Blueprint $table) use ($indices) {
                foreach ($indices as $nome => $colunas) {
                    $table->index($colunas, $nome);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::INDICES, true) as $tabela => $indices) {
            // O InnoDB descarta sozinho o índice que ele próprio gerou para a FK de
            // company_id assim que um índice composto passa a cobrir essa coluna — e
            // foi o que aconteceu no up(), porque a migration anterior recriou as FKs
            // sem índice explícito. Remover os compostos sem devolver esse suporte
            // deixaria a constraint sem índice, e o MySQL recusa (erro 1553). Recriar
            // antes mantém a FK sustentada durante toda a operação.
            $suporteDaFk = $tabela . '_company_id_foreign';

            if (! $this->possuiIndice($tabela, $suporteDaFk)) {
                Schema::table($tabela, function (Blueprint $table) use ($suporteDaFk) {
                    $table->index('company_id', $suporteDaFk);
                });
            }

            Schema::table($tabela, function (Blueprint $table) use ($indices) {
                foreach (array_reverse(array_keys($indices)) as $nome) {
                    $table->dropIndex($nome);
                }
            });
        }
    }

    private function possuiIndice(string $tabela, string $indice): bool
    {
        foreach (Schema::getIndexes($tabela) as $existente) {
            if ($existente['name'] === $indice) {
                return true;
            }
        }

        return false;
    }
};
