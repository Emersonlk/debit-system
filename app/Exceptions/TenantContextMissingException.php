<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Lançada quando uma operação que depende de multi-tenancy é executada sem que
 * exista uma empresa no contexto atual.
 *
 * A ausência de contexto nunca deve ser interpretada como "todas as empresas":
 * falhar aqui é intencional (fail-closed).
 */
class TenantContextMissingException extends RuntimeException
{
    public static function make(): self
    {
        return new self(
            'Nenhuma empresa definida no contexto atual. Operações multi-tenant exigem contexto '
            . 'explícito: em requisições HTTP ele vem do usuário autenticado (middleware tenant); '
            . 'fora delas (comandos, jobs, seeders) use CurrentCompany::runAs().'
        );
    }
}
