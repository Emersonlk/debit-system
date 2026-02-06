<?php

namespace Tests\Unit;

use App\DTOs\CreateClienteDTO;
use App\DTOs\EnderecoDTO;
use PHPUnit\Framework\TestCase;

class CreateClienteDTOTest extends TestCase
{
    /**
     * Testa criação de DTO com todos os campos
     */
    public function test_creates_dto_with_all_fields(): void
    {
        $endereco = new EnderecoDTO(
            rua: 'Rua Teste',
            numero: '123',
            bairro: 'Centro',
            cidade: 'São Paulo',
            estado: 'SP',
            complemento: null
        );
        $dto = new CreateClienteDTO(
            nome: 'João Silva',
            cpf: '11144477735',
            email: 'joao@example.com',
            telefone: '11999999999',
            endereco: $endereco
        );

        $this->assertEquals('João Silva', $dto->nome);
        $this->assertEquals('11144477735', $dto->cpf);
        $this->assertEquals('joao@example.com', $dto->email);
        $this->assertEquals('11999999999', $dto->telefone);
        $this->assertInstanceOf(EnderecoDTO::class, $dto->endereco);
        $this->assertEquals('Rua Teste', $dto->endereco->rua);
        $this->assertEquals('123', $dto->endereco->numero);
    }

    /**
     * Testa criação de DTO com campos opcionais nulos
     */
    public function test_creates_dto_with_nullable_fields(): void
    {
        $dto = new CreateClienteDTO(
            nome: 'João Silva',
            cpf: '11144477735'
        );

        $this->assertEquals('João Silva', $dto->nome);
        $this->assertEquals('11144477735', $dto->cpf);
        $this->assertNull($dto->email);
        $this->assertNull($dto->telefone);
        $this->assertNull($dto->endereco);
    }

    /**
     * Testa método toArray()
     */
    public function test_to_array_returns_correct_structure(): void
    {
        $dto = new CreateClienteDTO(
            nome: 'João Silva',
            cpf: '11144477735',
            email: 'joao@example.com',
            telefone: '11999999999',
            endereco: null
        );

        $array = $dto->toArray();

        $this->assertIsArray($array);
        $this->assertEquals('João Silva', $array['nome']);
        $this->assertEquals('11144477735', $array['cpf']);
        $this->assertEquals('joao@example.com', $array['email']);
        $this->assertEquals('11999999999', $array['telefone']);
        $this->assertArrayNotHasKey('endereco', $array);
    }

    /**
     * Testa método fromArray() com todos os campos incluindo endereço
     */
    public function test_from_array_creates_dto_with_all_fields(): void
    {
        $data = [
            'nome' => 'João Silva',
            'cpf' => '11144477735',
            'email' => 'joao@example.com',
            'telefone' => '11999999999',
            'endereco' => [
                'rua' => 'Rua Teste',
                'numero' => '123',
                'bairro' => 'Centro',
                'cidade' => 'São Paulo',
                'estado' => 'SP',
            ],
        ];

        $dto = CreateClienteDTO::fromArray($data);

        $this->assertInstanceOf(CreateClienteDTO::class, $dto);
        $this->assertEquals('João Silva', $dto->nome);
        $this->assertEquals('11144477735', $dto->cpf);
        $this->assertEquals('joao@example.com', $dto->email);
        $this->assertInstanceOf(EnderecoDTO::class, $dto->endereco);
        $this->assertEquals('Rua Teste', $dto->endereco->rua);
    }

    /**
     * Testa método fromArray() com campos opcionais ausentes
     */
    public function test_from_array_creates_dto_with_missing_optional_fields(): void
    {
        $data = [
            'nome' => 'João Silva',
            'cpf' => '11144477735',
        ];

        $dto = CreateClienteDTO::fromArray($data);

        $this->assertEquals('João Silva', $dto->nome);
        $this->assertEquals('11144477735', $dto->cpf);
        $this->assertNull($dto->email);
        $this->assertNull($dto->telefone);
        $this->assertNull($dto->endereco);
    }
}
