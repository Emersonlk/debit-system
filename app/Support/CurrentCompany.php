<?php

namespace App\Support;

use App\Exceptions\TenantContextMissingException;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Contexto de empresa (tenant) da execução atual.
 *
 * Registrado como `scoped` no container: uma instância por requisição/job.
 *
 * Fontes de contexto, nesta ordem:
 *  1. contexto explícito, definido por set()/runAs() — usado pelo middleware tenant
 *     e, no futuro, por rotas de Super Admin, comandos, jobs e seeders;
 *  2. resolução lazy a partir do usuário autenticado, quando ele é um usuário normal
 *     com empresa definida.
 *
 * Não existe terceira opção: sem contexto, id() e company() lançam exceção em vez de
 * devolver algo que possa virar uma consulta global (fail-closed). Super Admin
 * (company_id = null) nunca é tratado como tenant válido.
 */
class CurrentCompany
{
    private ?int $companyId = null;

    private ?Company $company = null;

    /**
     * Indica que um contexto explícito foi definido (inclusive um inválido),
     * suspendendo a resolução lazy pelo usuário autenticado.
     */
    private bool $bound = false;

    /**
     * Define explicitamente a empresa do contexto atual.
     */
    public function set(Company|int $company): void
    {
        if ($company instanceof Company) {
            $this->company = $company;
            $this->companyId = $company->getKey();
        } else {
            $this->company = null;
            $this->companyId = $company;
        }

        $this->bound = true;
    }

    /**
     * Há uma empresa no contexto atual?
     */
    public function has(): bool
    {
        return $this->resolveId() !== null;
    }

    /**
     * ID da empresa do contexto atual.
     *
     * @throws TenantContextMissingException quando não há contexto
     */
    public function id(): int
    {
        return $this->resolveId() ?? throw TenantContextMissingException::make();
    }

    /**
     * Empresa do contexto atual (carregada sob demanda).
     *
     * @throws TenantContextMissingException quando não há contexto
     */
    public function company(): Company
    {
        $id = $this->id();

        if ($this->company?->getKey() === $id) {
            return $this->company;
        }

        return $this->company = Company::findOrFail($id);
    }

    /**
     * Executa o callback tendo $company como contexto, restaurando o contexto
     * anterior ao final — inclusive quando o callback lança exceção.
     *
     * Chamadas aninhadas são suportadas.
     *
     * @template TReturn
     *
     * @param  callable(self): TReturn  $callback
     * @return TReturn
     */
    public function runAs(Company|int $company, callable $callback): mixed
    {
        $previousId = $this->companyId;
        $previousCompany = $this->company;
        $previousBound = $this->bound;

        $this->set($company);

        try {
            return $callback($this);
        } finally {
            $this->companyId = $previousId;
            $this->company = $previousCompany;
            $this->bound = $previousBound;
        }
    }

    /**
     * Resolve o ID da empresa: contexto explícito quando existir, senão o usuário
     * autenticado. O resultado da resolução lazy não é memorizado, pois o usuário
     * pode ser autenticado depois desta chamada dentro da mesma requisição.
     */
    private function resolveId(): ?int
    {
        if ($this->bound) {
            return $this->companyId;
        }

        $user = Auth::user();

        if (! $user instanceof User || $user->is_super_admin || $user->company_id === null) {
            return null;
        }

        return (int) $user->company_id;
    }
}
