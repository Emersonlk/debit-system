<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Promissoria extends Model
{
    use HasFactory;

    protected $table = 'promissorias';

    protected $fillable = [
        'cliente_id',
        'valor',
        'data_vencimento',
        'status',
        'observacoes',
        'notificado',
        'data_pagamento',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'data_vencimento' => 'date',
        'data_pagamento' => 'datetime',
        'notificado' => 'boolean',
    ];

    /**
     * Relacionamento com Cliente
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * Verifica se a promissória está próxima do vencimento (3 dias antes)
     */
    public function estaProximaVencimento(int $diasAntes = 3): bool
    {
        $dataLimite = now()->addDays($diasAntes);
        return $this->data_vencimento->lte($dataLimite) 
            && $this->data_vencimento->gte(now())
            && $this->status === 'pendente';
    }

    /**
     * Verifica se a promissória está vencida
     */
    public function estaVencida(): bool
    {
        return $this->data_vencimento->lt(now()) && $this->status === 'pendente';
    }

    /**
     * Marca a promissória como paga
     */
    public function marcarComoPaga(): bool
    {
        return $this->update([
            'status' => 'paga',
            'data_pagamento' => now(),
        ]);
    }
}
