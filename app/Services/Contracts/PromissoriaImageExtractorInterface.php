<?php

namespace App\Services\Contracts;

use Illuminate\Http\UploadedFile;

interface PromissoriaImageExtractorInterface
{
    /**
     * Extrai nome do cliente, data de vencimento e valor de uma imagem de nota promissória.
     *
     * @return array{nome_cliente: string, data_vencimento: string|null, valor: float|null, cpf: string|null, confianca: string}
     */
    public function extrair(UploadedFile $imagem): array;
}
