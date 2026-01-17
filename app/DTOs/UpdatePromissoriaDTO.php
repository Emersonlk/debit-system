<?php

namespace App\DTOs;

class UpdatePromissoriaDTO
{
    public function __construct(
        public ?int $cliente_id = null,
        public ?float $valor = null,
        public ?string $data_vencimento = null,
        public ?string $status = null,
        public ?string $observacoes = null,
        public ?bool $notificado = null,
        public ?string $data_pagamento = null
    ) {
    }

    public function toArray(): array
    {
        $data = [];

        if ($this->cliente_id !== null) {
            $data['cliente_id'] = $this->cliente_id;
        }
        if ($this->valor !== null) {
            $data['valor'] = $this->valor;
        }
        if ($this->data_vencimento !== null) {
            $data['data_vencimento'] = $this->data_vencimento;
        }
        if ($this->status !== null) {
            $data['status'] = $this->status;
        }
        if ($this->observacoes !== null) {
            $data['observacoes'] = $this->observacoes;
        }
        if ($this->notificado !== null) {
            $data['notificado'] = $this->notificado;
        }
        if ($this->data_pagamento !== null) {
            $data['data_pagamento'] = $this->data_pagamento;
        }

        return $data;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            cliente_id: $data['cliente_id'] ?? null,
            valor: $data['valor'] ?? null,
            data_vencimento: $data['data_vencimento'] ?? null,
            status: $data['status'] ?? null,
            observacoes: $data['observacoes'] ?? null,
            notificado: $data['notificado'] ?? null,
            data_pagamento: $data['data_pagamento'] ?? null
        );
    }
}
