<?php

namespace Tests\Feature;

use App\Http\Requests\DashboardRequest;
use App\Models\Company;
use App\Models\User;
use App\Support\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Validação dos parâmetros de GET /api/dashboard.
 *
 * Antes do DashboardRequest o endpoint respondia 200 para tudo: um `periodo`
 * desconhecido, datas invertidas ou um intervalo de dois séculos caíam no default
 * de 30 dias sem qualquer sinal de que o pedido fora ignorado — e, no caso do
 * intervalo grande, só depois de montar uma resposta de megabytes, já que a série
 * de recebimentos tem uma entrada por dia.
 *
 * O DashboardPeriodResolver não mudou: seus fallbacks continuam valendo como
 * defesa em profundidade para quem chame o serviço sem passar pelo controller.
 */
class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'admin']);

        $empresa = Company::factory()->create(['name' => 'Empresa A']);
        $usuario = User::factory()->create(['company_id' => $empresa->id]);
        $usuario->assignRole('admin');
        $this->token = $usuario->createToken('token')->plainTextToken;

        app(CurrentCompany::class)->set($empresa->id);
    }

    private function dashboard(string $query = ''): \Illuminate\Testing\TestResponse
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/dashboard' . $query);
    }

    // ------------------------------------------------------- caminho feliz

    public function test_sem_parametros_usa_os_defaults_e_responde_200(): void
    {
        $this->dashboard()
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.resumo_vencimento.proximas_vencimento.dias_verificacao', 3);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function periodosValidos(): array
    {
        return [
            'hoje' => ['hoje'],
            'sete dias' => ['7'],
            'trinta dias' => ['30'],
        ];
    }

    #[DataProvider('periodosValidos')]
    public function test_periodos_do_seletor_do_frontend_sao_aceitos(string $periodo): void
    {
        $this->dashboard('?periodo=' . $periodo)->assertStatus(200);
    }

    public function test_personalizado_com_datas_validas_e_aceito(): void
    {
        $this->dashboard('?periodo=personalizado&data_inicio=2026-09-01&data_fim=2026-09-30')
            ->assertStatus(200);
    }

    public function test_a_lista_de_periodos_cobre_exatamente_o_seletor_do_frontend(): void
    {
        // src/lib/dashboardData.js expõe PERIODS com estas quatro chaves; se uma
        // delas sair daqui, o frontend passa a receber 422 numa opção legítima.
        $this->assertSame(['hoje', '7', '30', 'personalizado'], DashboardRequest::PERIODOS);
    }

    // ------------------------------------------------------------- periodo

    public function test_periodo_desconhecido_retorna_422_em_vez_de_cair_no_default(): void
    {
        $this->dashboard('?periodo=banana')
            ->assertStatus(422)
            ->assertJsonPath('status_code', 422)
            ->assertJsonStructure(['errors' => ['periodo']]);
    }

    // --------------------------------------------------------------- datas

    public function test_personalizado_sem_data_inicio_retorna_422(): void
    {
        $this->dashboard('?periodo=personalizado&data_fim=2026-09-30')
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['data_inicio']]);
    }

    public function test_personalizado_sem_data_fim_retorna_422(): void
    {
        $this->dashboard('?periodo=personalizado&data_inicio=2026-09-01')
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['data_fim']]);
    }

    public function test_personalizado_sem_nenhuma_data_retorna_422(): void
    {
        $this->dashboard('?periodo=personalizado')
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['data_inicio', 'data_fim']]);
    }

    public function test_data_em_formato_invalido_retorna_422(): void
    {
        $this->dashboard('?periodo=personalizado&data_inicio=01/09/2026&data_fim=2026-09-30')
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['data_inicio']]);
    }

    public function test_datas_invertidas_retornam_422(): void
    {
        $this->dashboard('?periodo=personalizado&data_inicio=2026-09-30&data_fim=2026-09-01')
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['data_fim']]);
    }

    // ------------------------------------------------- limite do intervalo

    public function test_intervalo_de_exatamente_366_dias_inclusivos_e_aceito(): void
    {
        // 2026-01-01 a 2027-01-01: 365 dias de diferença, 366 contando os extremos.
        $this->dashboard('?periodo=personalizado&data_inicio=2026-01-01&data_fim=2027-01-01')
            ->assertStatus(200);
    }

    public function test_intervalo_de_367_dias_retorna_422(): void
    {
        $this->dashboard('?periodo=personalizado&data_inicio=2026-01-01&data_fim=2027-01-02')
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['data_fim']]);
    }

    public function test_ano_civil_completo_continua_valido(): void
    {
        // Caso citado no contrato: primeiro de janeiro a 31 de dezembro.
        $this->dashboard('?periodo=personalizado&data_inicio=2026-01-01&data_fim=2026-12-31')
            ->assertStatus(200);
    }

    public function test_intervalo_absurdo_nao_gera_mais_resposta_gigante(): void
    {
        // Este era o caso de 73.414 dias e cerca de 2,6 MB de resposta.
        $this->dashboard('?periodo=personalizado&data_inicio=1900-01-01&data_fim=2100-12-31')
            ->assertStatus(422);
    }

    // ---------------------------------------------------------------- dias

    /**
     * @return array<string, array{string}>
     */
    public static function valoresInvalidosDeDias(): array
    {
        return [
            'zero' => ['0'],
            'negativo' => ['-1'],
            'acima do maximo' => ['366'],
            'texto' => ['abc'],
        ];
    }

    #[DataProvider('valoresInvalidosDeDias')]
    public function test_dias_invalido_retorna_422(string $valor): void
    {
        $this->dashboard('?dias=' . $valor)
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['dias']]);
    }

    public function test_dias_nos_limites_e_aceito(): void
    {
        $this->dashboard('?dias=1')->assertStatus(200);
        $this->dashboard('?dias=365')->assertStatus(200);
    }

    public function test_dias_enviado_e_refletido_na_resposta(): void
    {
        $this->dashboard('?dias=10')
            ->assertStatus(200)
            ->assertJsonPath('data.resumo_vencimento.proximas_vencimento.dias_verificacao', 10);
    }

    // -------------------------------------------------------------- formato

    public function test_formato_da_resposta_permanece_o_mesmo(): void
    {
        $this->dashboard()
            ->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'status_code',
                'data' => [
                    'clientes',
                    'promissorias',
                    'recebimentos_periodo',
                    'resumo_vencimento',
                    'distribuicao_cliente',
                    'maiores_dividas',
                    'ultimos_pagamentos',
                ],
            ]);
    }
}
