<?php

namespace App\DTOs;

class EnderecoDTO
{
    public function __construct(
        public ?string $rua = null,
        public ?string $numero = null,
        public ?string $bairro = null,
        public ?string $cidade = null,
        public ?string $estado = null,
        public ?string $complemento = null
    ) {
    }

    public function toArray(): array
    {
        return array_filter([
            'rua' => $this->rua,
            'numero' => $this->numero,
            'bairro' => $this->bairro,
            'cidade' => $this->cidade,
            'estado' => $this->estado,
            'complemento' => $this->complemento,
        ], fn ($v) => $v !== null && $v !== '');
    }

    public function isEmpty(): bool
    {
        return empty($this->toArray());
    }

    public static function fromArray(array $data): self
    {
        return new self(
            rua: $data['rua'] ?? null,
            numero: $data['numero'] ?? null,
            bairro: $data['bairro'] ?? null,
            cidade: $data['cidade'] ?? null,
            estado: $data['estado'] ?? null,
            complemento: $data['complemento'] ?? null
        );
    }
}
