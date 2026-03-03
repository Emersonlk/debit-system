<?php

namespace App\Services\Dashboard;

class SaldoRestanteSql
{
    public const SQL = "CASE WHEN promissorias.valor_original IS NOT NULL THEN promissorias.valor ELSE GREATEST(0, promissorias.valor - COALESCE((SELECT SUM(valor_pago) FROM historico_pagamentos WHERE promissoria_id = promissorias.id), 0)) END";
}
