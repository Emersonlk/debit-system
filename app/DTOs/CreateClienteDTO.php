<?php

namespace App\DTOs;

class CreateClienteDTO
{
    public function __construct(
        public string $nome,
        public string $cpf,
        public ?string $email = null,
        public ?string $telefone = null,
        public ?EnderecoDTO $endereco = null
    ) {
    }

    public function toArray(): array
    {
        return [
            'nome' => $this->nome,
            'cpf' => $this->cpf,
            'email' => $this->email,
            'telefone' => $this->telefone,
        ];
    }

    public static function fromArray(array $data): self
    {
        $endereco = isset($data['endereco']) && is_array($data['endereco']) && !empty(array_filter($data['endereco']))
            ? EnderecoDTO::fromArray($data['endereco'])
            : null;

        return new self(
            nome: $data['nome'],
            cpf: $data['cpf'],
            email: $data['email'] ?? null,
            telefone: $data['telefone'] ?? null,
            endereco: $endereco
        );
    }
}
