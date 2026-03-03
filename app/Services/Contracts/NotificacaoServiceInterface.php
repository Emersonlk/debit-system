<?php

namespace App\Services\Contracts;

interface NotificacaoServiceInterface
{
    public function notificarPromissoriasProximasVencimento(int $dias = 3, bool $forcar = false): array;

    public function notificarPromissoriasVencidas(): array;

    public function atualizarStatusVencidas(): int;
}
