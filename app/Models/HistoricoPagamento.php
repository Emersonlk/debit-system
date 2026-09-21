<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistoricoPagamento extends Model
{
    use BelongsToCompany, HasFactory;

    protected $table = 'historico_pagamentos';

    /**
     * `company_id` fica deliberadamente fora: é definido pelo contexto de empresa
     * (trait BelongsToCompany), nunca por dado vindo da requisição.
     */
    protected $fillable = [
        'promissoria_id',
        'valor_pago',
        'data_pagamento',
        'observacoes',
    ];

    protected $casts = [
        'valor_pago' => 'decimal:2',
        'data_pagamento' => 'date',
    ];

    /**
     * Relacionamento com Promissoria
     */
    public function promissoria(): BelongsTo
    {
        return $this->belongsTo(Promissoria::class);
    }
}
