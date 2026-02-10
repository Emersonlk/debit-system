<?php

namespace App\Models;

use App\Enums\PromissoriaStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Promissoria extends Model
{
    use HasFactory;

    protected $table = 'promissorias';

    protected $fillable = [
        'cliente_id',
        'valor',
        'valor_original',
        'data_vencimento',
        'status',
        'observacoes',
        'notificado',
        'data_pagamento',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'valor_original' => 'decimal:2',
        'data_vencimento' => 'date',
        'data_pagamento' => 'datetime',
        'notificado' => 'boolean',
        'status' => PromissoriaStatus::class,
    ];

    /**
     * Atributos computados incluídos no array/JSON (valor original = total antes de pagamentos parciais).
     */
    protected $appends = ['valor_original_total'];

    /**
     * Relacionamento com Cliente
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * Relacionamento com Histórico de Pagamentos
     */
    public function historicoPagamentos(): HasMany
    {
        return $this->hasMany(HistoricoPagamento::class);
    }

    /**
     * Verifica se a promissória está próxima do vencimento (3 dias antes)
     */
    public function estaProximaVencimento(int $diasAntes = 3): bool
    {
        $dataLimite = now()->addDays($diasAntes);
        return $this->data_vencimento->lte($dataLimite) 
            && $this->data_vencimento->gte(now())
            && $this->status === PromissoriaStatus::PENDENTE;
    }

    /**
     * Verifica se a promissória está vencida
     */
    public function estaVencida(): bool
    {
        return $this->data_vencimento->lt(now()) && $this->status === PromissoriaStatus::PENDENTE;
    }

    /**
     * Marca a promissória como paga
     */
    public function marcarComoPaga(): bool
    {
        return $this->update([
            'status' => PromissoriaStatus::PAGA,
            'data_pagamento' => now(),
        ]);
    }

    /**
     * Retorna o valor original da promissória (antes de qualquer pagamento parcial).
     * Quando há pagamentos parciais, valor_original é preenchido e valor passa a ser o saldo restante.
     */
    public function getValorOriginalTotalAttribute(): float
    {
        return (float) ($this->valor_original ?? $this->valor);
    }

    /**
     * Calcula o valor total pago (soma de todos os pagamentos parciais).
     * Quando valor_original está definido, usa valor_original - valor (valor já é o saldo restante).
     * Quando status é PAGA e não há histórico, considera pagamento integral (valor total).
     */
    public function getValorTotalPagoAttribute(): float
    {
        if ($this->valor_original !== null) {
            return (float) $this->valor_original - (float) $this->valor;
        }
        $somaHistorico = (float) $this->historicoPagamentos()->sum('valor_pago');
        // Promissória paga sem registro no histórico (ex.: marcada como paga antes do registro de pagamento integral)
        if ($somaHistorico == 0 && $this->status === PromissoriaStatus::PAGA) {
            return (float) $this->valor;
        }
        return $somaHistorico;
    }

    /**
     * Calcula o saldo restante.
     * Quando valor_original está definido, valor já é o saldo restante; senão, valor original - valor pago.
     * Quando status é PAGA e não há histórico, saldo restante é zero.
     */
    public function getSaldoRestanteAttribute(): float
    {
        if ($this->valor_original !== null) {
            return max(0, (float) $this->valor);
        }
        $valorPago = (float) $this->historicoPagamentos()->sum('valor_pago');
        // Promissória paga sem registro no histórico: saldo restante zero
        if ($valorPago == 0 && $this->status === PromissoriaStatus::PAGA) {
            return 0.0;
        }
        return max(0, (float) $this->valor - $valorPago);
    }

    /**
     * Verifica se tem pagamentos parciais
     */
    public function temPagamentosParciais(): bool
    {
        return $this->historicoPagamentos()->exists();
    }

    /**
     * Marca a promissória como cancelada
     */
    public function cancelar(): bool
    {
        // Só pode cancelar se não estiver paga
        if ($this->status === PromissoriaStatus::PAGA) {
            return false;
        }

        return $this->update([
            'status' => PromissoriaStatus::CANCELADA,
        ]);
    }
}
