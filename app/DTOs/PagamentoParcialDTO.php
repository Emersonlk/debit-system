<?php

namespace App\DTOs;

class PagamentoParcialDTO
{
    public function __construct(
        public float $valor_pago,
        public string $data_pagamento,
        public ?string $observacoes = null
    ) {
    }

    public function toArray(): array
    {
        return [
            'valor_pago' => $this->valor_pago,
            'data_pagamento' => $this->data_pagamento,
            'observacoes' => $this->observacoes,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            valor_pago: $data['valor_pago'],
            data_pagamento: $data['data_pagamento'],
            observacoes: $data['observacoes'] ?? null
        );
    }
}
