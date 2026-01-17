<?php

namespace App\Enums;

enum PromissoriaStatus: string
{
    case PENDENTE = 'pendente';
    case PAGA = 'paga';
    case VENCIDA = 'vencida';

    /**
     * Array com todos os valores possíveis do enum
     */
    private const VALORES = ['pendente', 'paga', 'vencida'];

    /**
     * Retorna todos os valores possíveis do enum
     * 
     * @return array<string>
     */
    public static function valores(): array
    {
        return self::VALORES;
    }

    /**
     * Retorna os valores como string separada por vírgula (útil para validação)
     */
    public static function valoresString(): string
    {
        return implode(',', self::valores());
    }

    /**
     * Verifica se um valor é válido
     */
    public static function isValid(string $value): bool
    {
        return in_array($value, self::valores(), true);
    }

    /**
     * Retorna o valor padrão
     */
    public static function padrao(): self
    {
        return self::PENDENTE;
    }
}
