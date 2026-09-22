<?php

namespace Database\Seeders\Concerns;

use RuntimeException;

/**
 * Impede que seeders de demonstração rodem fora do ambiente local.
 *
 * O entrypoint dos containers já checa APP_ENV antes de chamar `db:seed`, mas ele
 * não é o único caminho: um `php artisan db:seed` manual, um deploy com outra
 * imagem ou um script de operação chegam direto ao seeder. Como estes seeders
 * criam usuários de senha conhecida e clientes fictícios, a proteção fica também
 * aqui, onde nenhum caminho a contorna.
 *
 * Seeders legítimos em produção — RolePermissionSeeder e CompanySeeder — não usam
 * este trait e continuam acessíveis por `db:seed --class=...`.
 */
trait ApenasEmAmbienteLocal
{
    protected function exigirAmbienteLocal(): void
    {
        if (app()->environment('local')) {
            return;
        }

        throw new RuntimeException(sprintf(
            '%s cria dados de demonstração e credenciais conhecidas: só roda com '
            . 'APP_ENV=local (ambiente atual: %s).',
            static::class,
            app()->environment()
        ));
    }
}
