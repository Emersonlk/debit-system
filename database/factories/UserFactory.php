<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            // Usuário normal pertence sempre a uma empresa; sem ela as rotas da API
            // respondem 403 (middleware tenant).
            'company_id' => Company::factory(),
            'is_super_admin' => false,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Usuário sem empresa — estado inconsistente, usado para testar o fail-closed.
     */
    public function semEmpresa(): static
    {
        return $this->state(fn (array $attributes) => [
            'company_id' => null,
        ]);
    }

    /**
     * Super Admin do SaaS: sem empresa e fora do contexto de tenant.
     */
    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'company_id' => null,
            'is_super_admin' => true,
        ]);
    }
}
