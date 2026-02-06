<?php

namespace Tests\Unit;

use App\Enums\PromissoriaStatus;
use PHPUnit\Framework\TestCase;

class PromissoriaStatusTest extends TestCase
{
    /**
     * Testa os valores do enum
     */
    public function test_enum_has_correct_values(): void
    {
        $this->assertEquals('pendente', PromissoriaStatus::PENDENTE->value);
        $this->assertEquals('paga', PromissoriaStatus::PAGA->value);
        $this->assertEquals('vencida', PromissoriaStatus::VENCIDA->value);
        $this->assertEquals('cancelada', PromissoriaStatus::CANCELADA->value);
    }

    /**
     * Testa método valores()
     */
    public function test_valores_returns_all_values(): void
    {
        $valores = PromissoriaStatus::valores();

        $this->assertIsArray($valores);
        $this->assertCount(4, $valores);
        $this->assertContains('pendente', $valores);
        $this->assertContains('paga', $valores);
        $this->assertContains('vencida', $valores);
        $this->assertContains('cancelada', $valores);
    }

    /**
     * Testa método valoresString()
     */
    public function test_valores_string_returns_comma_separated(): void
    {
        $valoresString = PromissoriaStatus::valoresString();

        $this->assertIsString($valoresString);
        $this->assertStringContainsString('pendente', $valoresString);
        $this->assertStringContainsString('paga', $valoresString);
        $this->assertStringContainsString('vencida', $valoresString);
        $this->assertStringContainsString('cancelada', $valoresString);
    }

    /**
     * Testa método isValid() com valores válidos
     */
    public function test_is_valid_returns_true_for_valid_values(): void
    {
        $this->assertTrue(PromissoriaStatus::isValid('pendente'));
        $this->assertTrue(PromissoriaStatus::isValid('paga'));
        $this->assertTrue(PromissoriaStatus::isValid('vencida'));
        $this->assertTrue(PromissoriaStatus::isValid('cancelada'));
    }

    /**
     * Testa método isValid() com valores inválidos
     */
    public function test_is_valid_returns_false_for_invalid_values(): void
    {
        $this->assertFalse(PromissoriaStatus::isValid('invalido'));
        $this->assertFalse(PromissoriaStatus::isValid(''));
        $this->assertFalse(PromissoriaStatus::isValid('PENDENTE'));
        $this->assertFalse(PromissoriaStatus::isValid('Paga'));
    }

    /**
     * Testa método padrao()
     */
    public function test_padrao_returns_pendente(): void
    {
        $padrao = PromissoriaStatus::padrao();

        $this->assertInstanceOf(PromissoriaStatus::class, $padrao);
        $this->assertEquals(PromissoriaStatus::PENDENTE, $padrao);
        $this->assertEquals('pendente', $padrao->value);
    }
}
