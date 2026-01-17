<?php

namespace Database\Factories;

use App\Enums\PromissoriaStatus;
use App\Models\Cliente;
use App\Models\Promissoria;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Promissoria>
 */
class PromissoriaFactory extends Factory
{
    protected $model = Promissoria::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cliente_id' => Cliente::factory(),
            'valor' => fake()->randomFloat(2, 100, 10000),
            'data_vencimento' => fake()->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
            'status' => PromissoriaStatus::PENDENTE->value,
            'observacoes' => fake()->optional()->sentence(),
            'notificado' => false,
            'data_pagamento' => null,
        ];
    }

    /**
     * Indica que a promissória está paga
     */
    public function paga(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PromissoriaStatus::PAGA->value,
            'data_pagamento' => now(),
        ]);
    }

    /**
     * Indica que a promissória está vencida
     */
    public function vencida(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PromissoriaStatus::VENCIDA->value,
            'data_vencimento' => fake()->dateTimeBetween('-30 days', '-1 day')->format('Y-m-d'),
        ]);
    }

    /**
     * Indica que a promissória está próxima do vencimento
     */
    public function proximaVencimento(int $dias = 3): static
    {
        return $this->state(fn (array $attributes) => [
            'data_vencimento' => now()->addDays($dias)->format('Y-m-d'),
            'status' => PromissoriaStatus::PENDENTE->value,
        ]);
    }
}
