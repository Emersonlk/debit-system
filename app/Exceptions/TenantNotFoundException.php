<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Lançada quando um job enfileirado carrega uma empresa que não existe mais.
 *
 * É diferente de TenantContextMissingException: ali não havia contexto nenhum;
 * aqui o contexto foi informado, mas aponta para uma empresa removida. Distinguir
 * os dois importa na operação — um indica bug de código, o outro dado órfão.
 */
class TenantNotFoundException extends RuntimeException
{
    public static function forId(int $companyId): self
    {
        return new self(
            "A empresa #{$companyId} associada a este job não existe mais. "
            . 'O job não é processado sem um tenant válido.'
        );
    }
}
