<?php

namespace App\Models\Concerns;

use App\Models\Company;
use App\Support\CurrentCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Isola o model por empresa (tenant).
 *
 * Leitura: um global scope restringe toda consulta à empresa do contexto atual.
 * Escrita: o evento `creating` define company_id a partir do mesmo contexto.
 *
 * Sem contexto, ambos falham com TenantContextMissingException — nunca consultam
 * ou gravam "todas as empresas" (fail-closed). Para operar fora de uma requisição
 * autenticada (comandos, jobs, seeders) ou sobre outra empresa, use
 * CurrentCompany::runAs(). Para operações legitimamente entre empresas, é preciso
 * remover o scope explicitamente com withoutGlobalScope(self::COMPANY_SCOPE).
 */
trait BelongsToCompany
{
    /**
     * Nome do global scope, para uso em withoutGlobalScope().
     */
    public static string $companyScope = 'company';

    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(static::$companyScope, function (Builder $builder) {
            $builder->where(
                // Qualificado (ex.: clientes.company_id) para não gerar ambiguidade
                // quando o model é usado em subquery correlacionada — ver
                // PromissoriaRepository::paginate(), que ordena por nome do cliente.
                $builder->getModel()->qualifyColumn('company_id'),
                app(CurrentCompany::class)->id()
            );
        });

        static::creating(function (Model $model) {
            // setAttribute em vez de atribuição em massa: funciona com company_id fora
            // do $fillable, que é justamente o que impede o frontend de escolher a
            // empresa. O valor do contexto sempre prevalece.
            $model->setAttribute('company_id', app(CurrentCompany::class)->id());
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
