<?php

namespace Tests\Unit;

use App\DTOs\EnderecoDTO;
use PHPUnit\Framework\TestCase;

class EnderecoDTOTest extends TestCase
{
    public function test_creates_dto_with_all_fields(): void
    {
        $dto = new EnderecoDTO(
            rua: 'Rua Teste',
            numero: '123',
            bairro: 'Centro',
            cidade: 'São Paulo',
            estado: 'SP',
            complemento: 'Sala 1'
        );

        $this->assertEquals('Rua Teste', $dto->rua);
        $this->assertEquals('123', $dto->numero);
        $this->assertEquals('Centro', $dto->bairro);
        $this->assertEquals('São Paulo', $dto->cidade);
        $this->assertEquals('SP', $dto->estado);
        $this->assertEquals('Sala 1', $dto->complemento);
    }

    public function test_to_array_returns_only_filled_fields(): void
    {
        $dto = new EnderecoDTO(rua: 'Rua A', numero: '1', cidade: 'SP', estado: 'SP');
        $array = $dto->toArray();

        $this->assertIsArray($array);
        $this->assertEquals('Rua A', $array['rua']);
        $this->assertEquals('1', $array['numero']);
        $this->assertEquals('SP', $array['cidade']);
        $this->assertEquals('SP', $array['estado']);
        $this->assertArrayNotHasKey('bairro', $array);
        $this->assertArrayNotHasKey('complemento', $array);
    }

    public function test_is_empty_returns_true_when_all_null(): void
    {
        $dto = new EnderecoDTO();
        $this->assertTrue($dto->isEmpty());
    }

    public function test_is_empty_returns_false_when_has_data(): void
    {
        $dto = new EnderecoDTO(rua: 'Rua A');
        $this->assertFalse($dto->isEmpty());
    }

    public function test_from_array_creates_dto(): void
    {
        $data = [
            'rua' => 'Av. Brasil',
            'numero' => '100',
            'bairro' => 'Centro',
            'cidade' => 'Rio de Janeiro',
            'estado' => 'RJ',
            'complemento' => null,
        ];

        $dto = EnderecoDTO::fromArray($data);

        $this->assertInstanceOf(EnderecoDTO::class, $dto);
        $this->assertEquals('Av. Brasil', $dto->rua);
        $this->assertEquals('100', $dto->numero);
        $this->assertEquals('RJ', $dto->estado);
    }

    public function test_from_array_with_missing_keys_uses_null(): void
    {
        $dto = EnderecoDTO::fromArray([]);
        $this->assertNull($dto->rua);
        $this->assertNull($dto->numero);
        $this->assertTrue($dto->isEmpty());
    }
}
