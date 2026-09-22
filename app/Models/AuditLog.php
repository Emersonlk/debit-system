<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    use HasFactory;

    protected $table = 'audit_logs';

    /**
     * `company_id` fica deliberadamente fora: é resolvido pelo AuditService a partir
     * do model auditado, nunca por atribuição em massa.
     *
     * Nota: este model NÃO usa BelongsToCompany. Auditoria é efeito colateral — o
     * hook `creating` da trait é fail-closed e derrubaria a operação de negócio
     * quando não houvesse contexto; e a empresa correta vem do model auditado, não
     * do contexto. O escopo de leitura será decidido quando existir um endpoint de
     * auditoria.
     */
    protected $fillable = [
        'user_id',
        'action',
        'model_type',
        'model_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    /**
     * Empresa à qual o registro auditado pertence.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Relacionamento com User
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relacionamento polimórfico com o modelo auditado
     */
    public function model(): MorphTo
    {
        return $this->morphTo();
    }
}
