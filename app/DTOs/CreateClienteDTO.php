<?php

namespace App\DTOs;

class CreateClienteDTO
{
    public function __construct(
        public string $nome,
        public string $cpf,
        public ?string $email = null,
        public ?string $telefone = null,
        public ?string $endereco = null
    ) {
    }

    public function toArray(): array
    {
        return [
            'nome' => $this->nome,
            'cpf' => $this->cpf,
            'email' => $this->email,
            'telefone' => $this->telefone,
            'endereco' => $this->endereco,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            nome: $data['nome'],
            cpf: $data['cpf'],
            email: $data['email'] ?? null,
            telefone: $data['telefone'] ?? null,
            endereco: $data['endereco'] ?? null
        );
    }
}
