<?php

namespace Tests\Unit;

use App\Rules\CpfValido;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class CpfValidoRuleTest extends TestCase
{
    /**
     * Testa validação de CPF válido
     */
    public function test_validates_valid_cpf(): void
    {
        $rule = new CpfValido();

        $validator = Validator::make(
            ['cpf' => '11144477735'],
            ['cpf' => [$rule]]
        );

        $this->assertTrue($validator->passes());
    }

    /**
     * Testa validação de CPF inválido com dígitos diferentes
     */
    public function test_validates_invalid_cpf_with_different_digits(): void
    {
        $rule = new CpfValido();

        $validator = Validator::make(
            ['cpf' => '11111111111'],
            ['cpf' => [$rule]]
        );

        $this->assertFalse($validator->passes());
        $this->assertStringContainsString('CPF inválido', $validator->errors()->first('cpf'));
    }

    /**
     * Testa validação de CPF com menos de 11 dígitos
     */
    public function test_validates_cpf_with_less_than_11_digits(): void
    {
        $rule = new CpfValido();

        $validator = Validator::make(
            ['cpf' => '123456789'],
            ['cpf' => [$rule]]
        );

        $this->assertFalse($validator->passes());
        $this->assertStringContainsString('11 dígitos', $validator->errors()->first('cpf'));
    }

    /**
     * Testa validação de CPF com formatação (aceita apenas números)
     */
    public function test_validates_cpf_with_formatting(): void
    {
        $rule = new CpfValido();

        // CPF com formatação (111.444.777-35)
        $validator = Validator::make(
            ['cpf' => '111.444.777-35'],
            ['cpf' => [$rule]]
        );

        // A regra remove formatação, então deve validar
        $this->assertTrue($validator->passes());
    }

    /**
     * Testa validação de CPF com caracteres especiais
     */
    public function test_validates_cpf_strips_special_characters(): void
    {
        $rule = new CpfValido();

        // CPF com vários caracteres especiais
        $validator = Validator::make(
            ['cpf' => '111.444.777-35'],
            ['cpf' => [$rule]]
        );

        $this->assertTrue($validator->passes());
    }

    /**
     * Testa validação de CPF inválido com dígitos verificadores incorretos
     */
    public function test_validates_invalid_cpf_with_wrong_check_digits(): void
    {
        $rule = new CpfValido();

        // CPF válido com último dígito alterado
        $validator = Validator::make(
            ['cpf' => '11144477734'], // Último dígito incorreto
            ['cpf' => [$rule]]
        );

        $this->assertFalse($validator->passes());
    }
}
