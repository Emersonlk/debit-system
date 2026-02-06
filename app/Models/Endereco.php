<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Endereco extends Model
{
    protected $table = 'enderecos';

    protected $fillable = [
        'cliente_id',
        'rua',
        'numero',
        'bairro',
        'cidade',
        'estado',
        'complemento',
    ];

    /**
     * Relacionamento com Cliente
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * Retorna o endereço formatado em uma linha (para exibição)
     */
    public function getFormatadoAttribute(): string
    {
        $partes = array_filter([
            $this->rua,
            $this->numero,
            $this->complemento,
            $this->bairro,
            $this->cidade ? ($this->estado ? "{$this->cidade}/{$this->estado}" : $this->cidade) : null,
        ]);

        return implode(', ', $partes) ?: '';
    }
}
