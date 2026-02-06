<?php

namespace Tests\Unit;

use App\DTOs\EnderecoDTO;
use App\DTOs\UpdateClienteDTO;
use PHPUnit\Framework\TestCase;

class UpdateClienteDTOTest extends TestCase
{
    public function test_creates_dto_with_all_null(): void
    {
        $dto = new UpdateClienteDTO();
        $this->assertNull($dto->nome);
        $this->assertNull($dto->cpf);
        $this->assertNull($dto->email);
        $this->assertNull($dto->telefone);
        $this->assertNull($dto->endereco);
        $this->assertFalse($dto->removerEndereco);
    }

    public function test_to_array_returns_only_non_null_fields(): void
    {
        $dto = new UpdateClienteDTO(nome: 'Maria', email: 'maria@example.com');
        $array = $dto->toArray();
        $this->assertEquals(['nome' => 'Maria', 'email' => 'maria@example.com'], $array);
    }

    public function test_to_array_returns_empty_when_all_null(): void
    {
        $dto = new UpdateClienteDTO();
        $this->assertEmpty($dto->toArray());
    }

    public function test_from_array_with_endereco_object(): void
    {
        $data = [
            'nome' => 'João',
            'endereco' => ['rua' => 'Rua Nova', 'numero' => '50', 'cidade' => 'SP', 'estado' => 'SP'],
        ];
        $dto = UpdateClienteDTO::fromArray($data);
        $this->assertEquals('João', $dto->nome);
        $this->assertInstanceOf(EnderecoDTO::class, $dto->endereco);
        $this->assertEquals('Rua Nova', $dto->endereco->rua);
        $this->assertFalse($dto->removerEndereco);
    }

    public function test_from_array_with_endereco_null_sets_remover_endereco(): void
    {
        $data = ['nome' => 'João', 'endereco' => null];
        $dto = UpdateClienteDTO::fromArray($data);
        $this->assertTrue($dto->removerEndereco);
        $this->assertNull($dto->endereco);
    }

    public function test_from_array_without_endereco_key(): void
    {
        $data = ['nome' => 'João'];
        $dto = UpdateClienteDTO::fromArray($data);
        $this->assertNull($dto->endereco);
        $this->assertFalse($dto->removerEndereco);
    }
}
