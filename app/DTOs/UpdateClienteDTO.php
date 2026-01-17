<?php

namespace App\DTOs;

class UpdateClienteDTO
{
    public function __construct(
        public ?string $nome = null,
        public ?string $cpf = null,
        public ?string $email = null,
        public ?string $telefone = null,
        public ?string $endereco = null
    ) {
    }

    public function toArray(): array
    {
        $data = [];

        if ($this->nome !== null) {
            $data['nome'] = $this->nome;
        }
        if ($this->cpf !== null) {
            $data['cpf'] = $this->cpf;
        }
        if ($this->email !== null) {
            $data['email'] = $this->email;
        }
        if ($this->telefone !== null) {
            $data['telefone'] = $this->telefone;
        }
        if ($this->endereco !== null) {
            $data['endereco'] = $this->endereco;
        }

        return $data;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            nome: $data['nome'] ?? null,
            cpf: $data['cpf'] ?? null,
            email: $data['email'] ?? null,
            telefone: $data['telefone'] ?? null,
            endereco: $data['endereco'] ?? null
        );
    }
}
