<?php

namespace Tests\Unit;

use App\DTOs\CreatePromissoriaDTO;
use App\Enums\PromissoriaStatus;
use PHPUnit\Framework\TestCase;

class CreatePromissoriaDTOTest extends TestCase
{
    /**
     * Testa criação de DTO com todos os campos
     */
    public function test_creates_dto_with_all_fields(): void
    {
        $dto = new CreatePromissoriaDTO(
            cliente_id: 1,
            valor: 500.50,
            data_vencimento: '2026-01-20',
            status: PromissoriaStatus::PAGA->value,
            observacoes: 'Teste de observações'
        );

        $this->assertEquals(1, $dto->cliente_id);
        $this->assertEquals(500.50, $dto->valor);
        $this->assertEquals('2026-01-20', $dto->data_vencimento);
        $this->assertEquals(PromissoriaStatus::PAGA->value, $dto->status);
        $this->assertEquals('Teste de observações', $dto->observacoes);
    }

    /**
     * Testa criação de DTO com status padrão
     */
    public function test_creates_dto_with_default_status(): void
    {
        $dto = new CreatePromissoriaDTO(
            cliente_id: 1,
            valor: 500.50,
            data_vencimento: '2026-01-20'
        );

        $this->assertEquals(PromissoriaStatus::PENDENTE->value, $dto->status);
    }

    /**
     * Testa método toArray()
     */
    public function test_to_array_returns_correct_structure(): void
    {
        $dto = new CreatePromissoriaDTO(
            cliente_id: 1,
            valor: 500.50,
            data_vencimento: '2026-01-20',
            status: PromissoriaStatus::PAGA->value,
            observacoes: 'Teste'
        );

        $array = $dto->toArray();

        $this->assertIsArray($array);
        $this->assertEquals(1, $array['cliente_id']);
        $this->assertEquals(500.50, $array['valor']);
        $this->assertEquals('2026-01-20', $array['data_vencimento']);
        $this->assertEquals(PromissoriaStatus::PAGA->value, $array['status']);
        $this->assertEquals('Teste', $array['observacoes']);
    }

    /**
     * Testa método fromArray() com status padrão
     */
    public function test_from_array_uses_default_status_when_missing(): void
    {
        $data = [
            'cliente_id' => 1,
            'valor' => 500.50,
            'data_vencimento' => '2026-01-20',
        ];

        $dto = CreatePromissoriaDTO::fromArray($data);

        $this->assertEquals(PromissoriaStatus::PENDENTE->value, $dto->status);
    }

    /**
     * Testa método fromArray() com todos os campos
     */
    public function test_from_array_creates_dto_with_all_fields(): void
    {
        $data = [
            'cliente_id' => 1,
            'valor' => 500.50,
            'data_vencimento' => '2026-01-20',
            'status' => PromissoriaStatus::PAGA->value,
            'observacoes' => 'Teste',
        ];

        $dto = CreatePromissoriaDTO::fromArray($data);

        $this->assertInstanceOf(CreatePromissoriaDTO::class, $dto);
        $this->assertEquals(1, $dto->cliente_id);
        $this->assertEquals(500.50, $dto->valor);
        $this->assertEquals(PromissoriaStatus::PAGA->value, $dto->status);
    }
}
