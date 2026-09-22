<?php

namespace Tests\Feature\Tenancy;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Protege as decisões de indexação da Phase 2B.7.3.
 *
 * O ponto destes testes é a ORDEM das colunas, não a existência dos índices. Um
 * índice com as mesmas colunas em ordem diferente é um índice diferente: com
 * `data_vencimento` antes de `company_id`, ou com `notificado` entre `status` e
 * `data_vencimento`, a ordenação volta ao filesort e o índice deixa de servir a
 * consulta que motivou sua criação. Por isso a comparação é com assertSame sobre
 * a lista ordenada, e não com asserções de conjunto.
 *
 * Os dois últimos testes existem para impedir que a fase extrapole o escopo sem
 * ninguém notar: users e audit_logs ficaram deliberadamente de fora.
 */
class CompositeIndexesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Índices aprovados na Phase 2B.7.3.
     *
     * @return array<string, array{string, string, list<string>}>
     */
    public static function indicesAprovados(): array
    {
        return [
            'clientes: empresa + email' => ['clientes', 'clientes_company_email_index', ['company_id', 'email']],
            'clientes: empresa + cpf' => ['clientes', 'clientes_company_cpf_index', ['company_id', 'cpf']],
            'clientes: empresa + nome' => ['clientes', 'clientes_company_nome_index', ['company_id', 'nome']],
            'promissorias: empresa + status + vencimento' => ['promissorias', 'promissorias_company_status_vencimento_index', ['company_id', 'status', 'data_vencimento']],
            'promissorias: empresa + status + pagamento' => ['promissorias', 'promissorias_company_status_pagamento_index', ['company_id', 'status', 'data_pagamento']],
            'promissorias: empresa + vencimento' => ['promissorias', 'promissorias_company_vencimento_index', ['company_id', 'data_vencimento']],
            'historico_pagamentos: empresa + pagamento' => ['historico_pagamentos', 'historico_pagamentos_company_pagamento_index', ['company_id', 'data_pagamento']],
        ];
    }

    /**
     * @return array<string, list<string>> nome do índice => colunas, na ordem declarada
     */
    private function indicesDa(string $tabela): array
    {
        $indices = [];

        foreach (Schema::getIndexes($tabela) as $indice) {
            $indices[$indice['name']] = array_values($indice['columns']);
        }

        return $indices;
    }

    // ------------------------------------------- os 7 índices aprovados

    /**
     * @param  list<string>  $colunasEsperadas
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('indicesAprovados')]
    public function test_indice_existe_com_as_colunas_na_ordem_correta(string $tabela, string $nome, array $colunasEsperadas): void
    {
        $indices = $this->indicesDa($tabela);

        $this->assertArrayHasKey($nome, $indices, "O índice {$nome} não existe em {$tabela}.");

        // assertSame compara ordem: colunas trocadas de lugar reprovam aqui.
        $this->assertSame(
            $colunasEsperadas,
            $indices[$nome],
            "As colunas de {$nome} não estão na ordem aprovada na Phase 2B.7.3."
        );
    }

    public function test_company_id_e_sempre_a_primeira_coluna(): void
    {
        foreach (self::indicesAprovados() as $caso) {
            [$tabela, $nome, $colunas] = $caso;

            $this->assertSame(
                'company_id',
                $this->indicesDa($tabela)[$nome][0] ?? null,
                "O índice {$nome} precisa começar por company_id para servir o global scope."
            );
            $this->assertSame('company_id', $colunas[0]);
        }
    }

    public function test_indices_simples_anteriores_foram_preservados(): void
    {
        // A remoção destes foi deliberadamente adiada para uma análise própria.
        $promissorias = $this->indicesDa('promissorias');

        $this->assertSame(['status'], $promissorias['promissorias_status_index'] ?? null);
        $this->assertSame(['data_vencimento'], $promissorias['promissorias_data_vencimento_index'] ?? null);
    }

    /**
     * Os índices compostos não podem custar as garantias da Phase 2B.7.2.
     *
     * Não é hipótese: ao criar (company_id, ...) o InnoDB descarta o índice que
     * ele mesmo havia gerado para a FK de company_id e passa a sustentá-la pelo
     * composto. A constraint continua valendo, mas quem for mexer nestes índices
     * precisa que uma falha nisso apareça como teste vermelho, não como erro 1553
     * num rollback.
     */
    public function test_foreign_keys_de_company_id_continuam_intactas(): void
    {
        foreach (['clientes', 'promissorias', 'historico_pagamentos'] as $tabela) {
            $fks = collect(Schema::getForeignKeys($tabela))
                ->firstWhere('name', $tabela . '_company_id_foreign');

            $this->assertNotNull($fks, "A FK de company_id sumiu de {$tabela}.");
            $this->assertSame(['company_id'], array_values($fks['columns']));
            $this->assertSame('companies', $fks['foreign_table']);
            $this->assertSame('restrict', $fks['on_delete'], "O RESTRICT de {$tabela} foi perdido.");
        }
    }

    // ------------------------------- tabelas deliberadamente fora do escopo

    public function test_users_nao_recebeu_indice_novo(): void
    {
        $this->assertSame(
            [
                'primary' => ['id'],
                'users_company_id_foreign' => ['company_id'],
                'users_email_unique' => ['email'],
            ],
            $this->ordenarPorNome($this->indicesDa('users')),
            'users ficou fora da Phase 2B.7.3: o índice simples em company_id já atende as consultas reais.'
        );
    }

    public function test_audit_logs_nao_recebeu_indice_novo(): void
    {
        $this->assertSame(
            [
                'audit_logs_action_index' => ['action'],
                'audit_logs_company_id_foreign' => ['company_id'],
                'audit_logs_created_at_index' => ['created_at'],
                'audit_logs_model_type_model_id_index' => ['model_type', 'model_id'],
                'audit_logs_user_id_index' => ['user_id'],
                'primary' => ['id'],
            ],
            $this->ordenarPorNome($this->indicesDa('audit_logs')),
            'audit_logs ficou fora da Phase 2B.7.3: a tabela é write-only, não há consulta a otimizar.'
        );
    }

    /**
     * @param  array<string, list<string>>  $indices
     * @return array<string, list<string>>
     */
    private function ordenarPorNome(array $indices): array
    {
        ksort($indices);

        return $indices;
    }
}
