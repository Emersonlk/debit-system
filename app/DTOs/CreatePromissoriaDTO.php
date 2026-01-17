<?php

namespace App\DTOs;

class CreatePromissoriaDTO
{
    public function __construct(
        public int $cliente_id,
        public float $valor,
        public string $data_vencimento,
        public string $status = 'pendente',
        public ?string $observacoes = null
    ) {
    }

    public function toArray(): array
    {
        return [
            'cliente_id' => $this->cliente_id,
            'valor' => $this->valor,
            'data_vencimento' => $this->data_vencimento,
            'status' => $this->status,
            'observacoes' => $this->observacoes,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            cliente_id: $data['cliente_id'],
            valor: $data['valor'],
            data_vencimento: $data['data_vencimento'],
            status: $data['status'] ?? 'pendente',
            observacoes: $data['observacoes'] ?? null
        );
    }
}
