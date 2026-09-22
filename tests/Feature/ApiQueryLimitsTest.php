<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Support\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Limites dos parâmetros de consulta das listagens.
 *
 * Antes desta fase `per_page` ia direto para o LIMIT sem teto: um valor alto
 * carregava a base inteira da empresa e um negativo produzia SQL inválido, que o
 * handler traduzia em HTTP 500. `dias` tinha o mesmo problema e ainda compõe a
 * chave de cache do resumo de vencimento.
 *
 * Os casos inválidos aqui valem por dois: garantem o 422 e garantem que ele chega
 * como 422 até o cliente, atravessando o render() customizado de bootstrap/app.php.
 */
class ApiQueryLimitsTest extends TestCase
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

    private function chamar(string $url): \Illuminate\Testing\TestResponse
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token)->getJson($url);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function listagens(): array
    {
        return [
            'clientes' => ['/api/clientes'],
            'promissorias' => ['/api/promissorias'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function valoresInvalidosDePerPage(): array
    {
        return [
            'acima do maximo' => ['101'],
            'muito acima do maximo' => ['50000'],
            'zero' => ['0'],
            'negativo' => ['-5'],
            'texto' => ['abc'],
            'decimal' => ['10.9'],
        ];
    }

    // ------------------------------------------------------------ per_page

    #[DataProvider('listagens')]
    public function test_per_page_ausente_usa_o_default_de_15(string $url): void
    {
        $this->chamar($url)
            ->assertStatus(200)
            ->assertJsonPath('meta.per_page', 15);
    }

    #[DataProvider('listagens')]
    public function test_per_page_dentro_do_limite_e_aceito(string $url): void
    {
        $this->chamar($url . '?per_page=10')->assertStatus(200)->assertJsonPath('meta.per_page', 10);
        $this->chamar($url . '?per_page=100')->assertStatus(200)->assertJsonPath('meta.per_page', 100);
    }

    #[DataProvider('valoresInvalidosDePerPage')]
    public function test_per_page_invalido_em_clientes_retorna_422(string $valor): void
    {
        $this->chamar('/api/clientes?per_page=' . $valor)
            ->assertStatus(422)
            ->assertJsonPath('status_code', 422)
            ->assertJsonStructure(['errors' => ['per_page']]);
    }

    #[DataProvider('valoresInvalidosDePerPage')]
    public function test_per_page_invalido_em_promissorias_retorna_422(string $valor): void
    {
        $this->chamar('/api/promissorias?per_page=' . $valor)
            ->assertStatus(422)
            ->assertJsonPath('status_code', 422)
            ->assertJsonStructure(['errors' => ['per_page']]);
    }

    public function test_per_page_negativo_nao_produz_mais_erro_500(): void
    {
        // Regressão: este era o caso que gerava `... order by nome asc offset 0`,
        // SQL inválido, e respondia 500 expondo a query.
        foreach (['/api/clientes', '/api/promissorias'] as $url) {
            $resposta = $this->chamar($url . '?per_page=-5');

            $this->assertNotSame(500, $resposta->getStatusCode(), "{$url} ainda responde 500.");
            $resposta->assertStatus(422);
        }
    }

    // ---------------------------------------------------------------- dias

    /**
     * @return array<string, array{string}>
     */
    public static function endpointsComDias(): array
    {
        return [
            'listagem com proximas_vencimento' => ['/api/promissorias?proximas_vencimento=1&dias='],
            'listagem sem o filtro que consome dias' => ['/api/promissorias?dias='],
            'resumo de vencimento' => ['/api/promissorias/resumo/vencimento?dias='],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function valoresInvalidosDeDias(): array
    {
        return [
            'zero' => ['0'],
            'negativo' => ['-1'],
            'acima do maximo' => ['366'],
            'muito acima do maximo' => ['99999'],
            'texto' => ['abc'],
        ];
    }

    #[DataProvider('endpointsComDias')]
    public function test_dias_dentro_do_limite_e_aceito(string $url): void
    {
        $this->chamar($url . '1')->assertStatus(200);
        $this->chamar($url . '365')->assertStatus(200);
    }

    #[DataProvider('valoresInvalidosDeDias')]
    public function test_dias_invalido_na_listagem_retorna_422(string $valor): void
    {
        // Validado mesmo sem proximas_vencimento: o parâmetro enviado é conferido
        // ainda que o fluxo não vá consumi-lo.
        $this->chamar('/api/promissorias?dias=' . $valor)
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['dias']]);
    }

    #[DataProvider('valoresInvalidosDeDias')]
    public function test_dias_invalido_no_resumo_de_vencimento_retorna_422(string $valor): void
    {
        $this->chamar('/api/promissorias/resumo/vencimento?dias=' . $valor)
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['dias']]);
    }

    public function test_dias_ausente_mantem_o_default_nos_dois_endpoints(): void
    {
        $this->chamar('/api/promissorias')->assertStatus(200);

        $this->chamar('/api/promissorias/resumo/vencimento')
            ->assertStatus(200)
            ->assertJsonPath('data.proximas_vencimento.dias_verificacao', 3);
    }
}
