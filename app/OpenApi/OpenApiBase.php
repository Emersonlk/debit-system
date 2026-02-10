<?php

namespace App\OpenApi;

use OpenApi\Attributes as OAT;

// Define a URL base do servidor usando constante do L5-Swagger
if (!defined('L5_SWAGGER_CONST_HOST')) {
    define('L5_SWAGGER_CONST_HOST', config('l5-swagger.defaults.constants.L5_SWAGGER_CONST_HOST', 'http://localhost:8000'));
}

#[OAT\OpenApi(
    openapi: '3.0.0',
    info: new OAT\Info(
        title: 'Debit System API',
        version: '1.0',
        description: 'API do sistema de débitos (clientes e promissórias). Autenticação via Sanctum (Bearer token).'
    ),
    servers: [
        new OAT\Server(url: L5_SWAGGER_CONST_HOST . '/api', description: 'API'),
    ],
    tags: [
        new OAT\Tag(name: 'Auth', description: 'Login e logout'),
        new OAT\Tag(name: 'Clientes', description: 'CRUD de clientes'),
        new OAT\Tag(name: 'Promissórias', description: 'CRUD e ações de promissórias'),
    ]
)]
class OpenApiBase
{
}
