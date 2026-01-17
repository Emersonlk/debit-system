<?php

namespace Tests\Unit;

use App\DTOs\UpdatePromissoriaDTO;
use PHPUnit\Framework\TestCase;

class UpdatePromissoriaDTOTest extends TestCase
{
    /**
     * Testa criação de DTO com todos os campos nulos (padrão)
     */
    public function test_creates_dto_with_all_null_fields(): void
    {
        $dto = new UpdatePromissoriaDTO();

        $this->assertNull($dto->cliente_id);
        $this->assertNull($dto->valor);
        $this->assertNull($dto->data_vencimento);
        $this->assertNull($dto->status);
        $this->assertNull($dto->observacoes);
        $this->assertNull($dto->notificado);
        $this->assertNull($dto->data_pagamento);
    }

    /**
     * Testa método toArray() retorna apenas campos não nulos
     */
    public function test_to_array_returns_only_non_null_fields(): void
    {
        $dto = new UpdatePromissoriaDTO(
            valor: 750.00,
            observacoes: 'Atualizado'
        );

        $array = $dto->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('valor', $array);
        $this->assertArrayHasKey('observacoes', $array);
        $this->assertArrayNotHasKey('cliente_id', $array);
        $this->assertArrayNotHasKey('status', $array);
        $this->assertEquals(750.00, $array['valor']);
        $this->assertEquals('Atualizado', $array['observacoes']);
    }

    /**
     * Testa método toArray() retorna array vazio quando todos campos são nulos
     */
    public function test_to_array_returns_empty_array_when_all_null(): void
    {
        $dto = new UpdatePromissoriaDTO();

        $array = $dto->toArray();

        $this->assertIsArray($array);
        $this->assertEmpty($array);
    }

    /**
     * Testa método fromArray() com alguns campos
     */
    public function test_from_array_creates_dto_with_some_fields(): void
    {
        $data = [
            'valor' => 750.00,
            'status' => 'paga',
        ];

        $dto = UpdatePromissoriaDTO::fromArray($data);

        $this->assertInstanceOf(UpdatePromissoriaDTO::class, $dto);
        $this->assertEquals(750.00, $dto->valor);
        $this->assertEquals('paga', $dto->status);
        $this->assertNull($dto->cliente_id);
        $this->assertNull($dto->data_vencimento);
    }

    /**
     * Testa método fromArray() com array vazio
     */
    public function test_from_array_creates_dto_with_empty_array(): void
    {
        $dto = UpdatePromissoriaDTO::fromArray([]);

        $this->assertInstanceOf(UpdatePromissoriaDTO::class, $dto);
        $this->assertNull($dto->valor);
        $this->assertNull($dto->status);
    }
}
