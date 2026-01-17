<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    use HasFactory;

    protected $table = 'clientes';

    protected $fillable = [
        'nome',
        'cpf',
        'email',
        'telefone',
        'endereco'
    ];

    /**
     * Relacionamento com Promissórias
     */
    public function promissorias(): HasMany
    {
        return $this->hasMany(Promissoria::class);
    }
}
