<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Testa se a aplicação está rodando corretamente
     * Verifica a rota de health check
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        // Testa a rota de health check do Laravel
        $response = $this->get('/up');

        $response->assertStatus(200);
    }
}
