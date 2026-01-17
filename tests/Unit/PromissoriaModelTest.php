<?php

namespace Tests\Unit;

use App\Enums\PromissoriaStatus;
use App\Models\Promissoria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromissoriaModelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Testa método estaProximaVencimento() retorna true quando está próxima
     */
    public function test_esta_proxima_vencimento_returns_true_when_near_due(): void
    {
        $promissoria = new Promissoria([
            'data_vencimento' => now()->addDays(2),
            'status' => PromissoriaStatus::PENDENTE,
        ]);

        $this->assertTrue($promissoria->estaProximaVencimento(3));
    }

    /**
     * Testa método estaProximaVencimento() retorna false quando está longe do vencimento
     */
    public function test_esta_proxima_vencimento_returns_false_when_far_from_due(): void
    {
        $promissoria = new Promissoria([
            'data_vencimento' => now()->addDays(10),
            'status' => PromissoriaStatus::PENDENTE,
        ]);

        $this->assertFalse($promissoria->estaProximaVencimento(3));
    }

    /**
     * Testa método estaProximaVencimento() retorna false quando já está vencida
     */
    public function test_esta_proxima_vencimento_returns_false_when_overdue(): void
    {
        $promissoria = new Promissoria([
            'data_vencimento' => now()->subDays(1),
            'status' => PromissoriaStatus::PENDENTE,
        ]);

        $this->assertFalse($promissoria->estaProximaVencimento(3));
    }

    /**
     * Testa método estaProximaVencimento() retorna false quando status não é pendente
     */
    public function test_esta_proxima_vencimento_returns_false_when_status_not_pendente(): void
    {
        $promissoria = new Promissoria([
            'data_vencimento' => now()->addDays(2),
            'status' => PromissoriaStatus::PAGA,
        ]);

        $this->assertFalse($promissoria->estaProximaVencimento(3));
    }

    /**
     * Testa método estaVencida() retorna true quando está vencida
     */
    public function test_esta_vencida_returns_true_when_overdue(): void
    {
        $promissoria = new Promissoria([
            'data_vencimento' => now()->subDays(1),
            'status' => PromissoriaStatus::PENDENTE,
        ]);

        $this->assertTrue($promissoria->estaVencida());
    }

    /**
     * Testa método estaVencida() retorna false quando não está vencida
     */
    public function test_esta_vencida_returns_false_when_not_overdue(): void
    {
        $promissoria = new Promissoria([
            'data_vencimento' => now()->addDays(1),
            'status' => PromissoriaStatus::PENDENTE,
        ]);

        $this->assertFalse($promissoria->estaVencida());
    }

    /**
     * Testa método estaVencida() retorna false quando status não é pendente
     */
    public function test_esta_vencida_returns_false_when_status_not_pendente(): void
    {
        $promissoria = new Promissoria([
            'data_vencimento' => now()->subDays(1),
            'status' => PromissoriaStatus::PAGA,
        ]);

        $this->assertFalse($promissoria->estaVencida());
    }
}
