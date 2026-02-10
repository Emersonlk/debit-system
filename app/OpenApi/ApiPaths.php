<?php

namespace App\OpenApi;

use OpenApi\Attributes as OAT;

/**
 * Documentação das rotas da API (paths são mesclados ao spec pelo L5-Swagger).
 */
class ApiPaths
{
    #[OAT\Post(
        path: '/login',
        summary: 'Login',
        description: 'Autentica com email e senha. Retorna token Bearer e dados do usuário.',
        tags: ['Auth'],
        requestBody: new OAT\RequestBody(
            required: true,
            content: new OAT\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OAT\Property(property: 'email', type: 'string', format: 'email', example: 'test@example.com'),
                    new OAT\Property(property: 'password', type: 'string', example: 'password123'),
                ]
            )
        ),
        responses: [
            new OAT\Response(response: 200, description: 'Login realizado com sucesso'),
            new OAT\Response(response: 401, description: 'Credenciais inválidas'),
            new OAT\Response(response: 422, description: 'Erro de validação'),
        ]
    )]
    public function login(): void
    {
    }

    #[OAT\Post(
        path: '/logout',
        summary: 'Logout',
        description: 'Invalida o token atual (requer autenticação).',
        tags: ['Auth'],
        security: [['sanctum' => []]],
        responses: [
            new OAT\Response(response: 200, description: 'Logout efetuado'),
            new OAT\Response(response: 401, description: 'Não autenticado'),
        ]
    )]
    public function logout(): void
    {
    }

    #[OAT\Get(
        path: '/clientes',
        summary: 'Listar clientes',
        description: 'Lista clientes com paginação. Use o parâmetro search para filtrar por nome.',
        tags: ['Clientes'],
        security: [['sanctum' => []]],
        parameters: [
            new OAT\QueryParameter(name: 'per_page', description: 'Itens por página', required: false, example: 15),
            new OAT\QueryParameter(name: 'search', description: 'Busca por nome (parte do nome)', required: false),
        ],
        responses: [
            new OAT\Response(response: 200, description: 'Lista paginada de clientes'),
            new OAT\Response(response: 401, description: 'Não autenticado'),
        ]
    )]
    public function clientesIndex(): void
    {
    }

    #[OAT\Post(
        path: '/clientes',
        summary: 'Criar cliente',
        description: 'Cria um novo cliente. Pode incluir objeto endereco (rua, numero, bairro, cidade, estado, complemento).',
        tags: ['Clientes'],
        security: [['sanctum' => []]],
        requestBody: new OAT\RequestBody(
            required: true,
            content: new OAT\JsonContent(
                required: ['nome', 'cpf', 'email'],
                properties: [
                    new OAT\Property(property: 'nome', type: 'string', example: 'João Silva'),
                    new OAT\Property(property: 'cpf', type: 'string', example: '11144477735'),
                    new OAT\Property(property: 'email', type: 'string', format: 'email', example: 'joao@example.com'),
                    new OAT\Property(property: 'telefone', type: 'string', example: '11999999999'),
                    new OAT\Property(
                        property: 'endereco',
                        type: 'object',
                        properties: [
                            new OAT\Property(property: 'rua', type: 'string'),
                            new OAT\Property(property: 'numero', type: 'string'),
                            new OAT\Property(property: 'bairro', type: 'string'),
                            new OAT\Property(property: 'cidade', type: 'string'),
                            new OAT\Property(property: 'estado', type: 'string', example: 'SP'),
                            new OAT\Property(property: 'complemento', type: 'string'),
                        ]
                    ),
                ]
            )
        ),
        responses: [
            new OAT\Response(response: 201, description: 'Cliente criado'),
            new OAT\Response(response: 401, description: 'Não autenticado'),
            new OAT\Response(response: 422, description: 'Erro de validação'),
        ]
    )]
    public function clientesStore(): void
    {
    }

    #[OAT\Get(
        path: '/clientes/{cliente}',
        summary: 'Exibir cliente',
        description: 'Retorna um cliente pelo ID.',
        tags: ['Clientes'],
        security: [['sanctum' => []]],
        parameters: [
            new OAT\PathParameter(name: 'cliente', description: 'ID do cliente', required: true, example: 1),
        ],
        responses: [
            new OAT\Response(response: 200, description: 'Cliente encontrado'),
            new OAT\Response(response: 401, description: 'Não autenticado'),
            new OAT\Response(response: 404, description: 'Cliente não encontrado'),
        ]
    )]
    public function clientesShow(): void
    {
    }

    #[OAT\Put(
        path: '/clientes/{cliente}',
        summary: 'Atualizar cliente',
        description: 'Atualiza um cliente. Envie endereco: null para remover o endereço.',
        tags: ['Clientes'],
        security: [['sanctum' => []]],
        parameters: [
            new OAT\PathParameter(name: 'cliente', description: 'ID do cliente', required: true, example: 1),
        ],
        requestBody: new OAT\RequestBody(
            required: false,
            content: new OAT\JsonContent(
                properties: [
                    new OAT\Property(property: 'nome', type: 'string'),
                    new OAT\Property(property: 'cpf', type: 'string'),
                    new OAT\Property(property: 'email', type: 'string', format: 'email'),
                    new OAT\Property(property: 'telefone', type: 'string'),
                    new OAT\Property(property: 'endereco', description: 'Objeto com rua, numero, bairro, cidade, estado, complemento. Use null para remover.'),
                ]
            )
        ),
        responses: [
            new OAT\Response(response: 200, description: 'Cliente atualizado'),
            new OAT\Response(response: 401, description: 'Não autenticado'),
            new OAT\Response(response: 404, description: 'Cliente não encontrado'),
            new OAT\Response(response: 422, description: 'Erro de validação'),
        ]
    )]
    public function clientesUpdate(): void
    {
    }

    #[OAT\Delete(
        path: '/clientes/{cliente}',
        summary: 'Excluir cliente',
        description: 'Remove um cliente.',
        tags: ['Clientes'],
        security: [['sanctum' => []]],
        parameters: [
            new OAT\PathParameter(name: 'cliente', description: 'ID do cliente', required: true, example: 1),
        ],
        responses: [
            new OAT\Response(response: 200, description: 'Cliente removido'),
            new OAT\Response(response: 401, description: 'Não autenticado'),
            new OAT\Response(response: 404, description: 'Cliente não encontrado'),
        ]
    )]
    public function clientesDestroy(): void
    {
    }

    #[OAT\Get(
        path: '/promissorias',
        summary: 'Listar promissórias',
        description: 'Lista promissórias com paginação. Filtros: status, cliente_id, vencidas, proximas_vencimento, dias.',
        tags: ['Promissórias'],
        security: [['sanctum' => []]],
        parameters: [
            new OAT\QueryParameter(name: 'per_page', description: 'Itens por página', required: false),
            new OAT\QueryParameter(name: 'status', description: 'Filtrar por status (pendente, paga, cancelada)', required: false),
            new OAT\QueryParameter(name: 'cliente_id', description: 'Filtrar por ID do cliente', required: false),
            new OAT\QueryParameter(name: 'vencidas', description: 'Apenas vencidas', required: false),
            new OAT\QueryParameter(name: 'proximas_vencimento', description: 'Próximas do vencimento', required: false),
            new OAT\QueryParameter(name: 'dias', description: 'Dias para "próximas do vencimento"', required: false),
        ],
        responses: [
            new OAT\Response(response: 200, description: 'Lista paginada de promissórias'),
            new OAT\Response(response: 401, description: 'Não autenticado'),
        ]
    )]
    public function promissoriasIndex(): void
    {
    }

    #[OAT\Post(
        path: '/promissorias',
        summary: 'Criar promissória',
        description: 'Cria uma nova promissória (cliente_id, valor, data_vencimento obrigatórios).',
        tags: ['Promissórias'],
        security: [['sanctum' => []]],
        requestBody: new OAT\RequestBody(
            required: true,
            content: new OAT\JsonContent(
                required: ['cliente_id', 'valor', 'data_vencimento'],
                properties: [
                    new OAT\Property(property: 'cliente_id', type: 'integer', example: 1),
                    new OAT\Property(property: 'valor', type: 'number', format: 'float', example: 1000.00),
                    new OAT\Property(property: 'data_vencimento', type: 'string', format: 'date', example: '2026-03-01'),
                    new OAT\Property(property: 'descricao', type: 'string'),
                ]
            )
        ),
        responses: [
            new OAT\Response(response: 201, description: 'Promissória criada'),
            new OAT\Response(response: 401, description: 'Não autenticado'),
            new OAT\Response(response: 422, description: 'Erro de validação'),
        ]
    )]
    public function promissoriasStore(): void
    {
    }

    #[OAT\Get(
        path: '/promissorias/{promissoria}',
        summary: 'Exibir promissória',
        description: 'Retorna uma promissória pelo ID.',
        tags: ['Promissórias'],
        security: [['sanctum' => []]],
        parameters: [
            new OAT\PathParameter(name: 'promissoria', description: 'ID da promissória', required: true, example: 1),
        ],
        responses: [
            new OAT\Response(response: 200, description: 'Promissória encontrada'),
            new OAT\Response(response: 401, description: 'Não autenticado'),
            new OAT\Response(response: 404, description: 'Promissória não encontrada'),
        ]
    )]
    public function promissoriasShow(): void
    {
    }

    #[OAT\Put(
        path: '/promissorias/{promissoria}',
        summary: 'Atualizar promissória',
        description: 'Atualiza uma promissória.',
        tags: ['Promissórias'],
        security: [['sanctum' => []]],
        parameters: [
            new OAT\PathParameter(name: 'promissoria', description: 'ID da promissória', required: true, example: 1),
        ],
        requestBody: new OAT\RequestBody(
            required: false,
            content: new OAT\JsonContent(
                properties: [
                    new OAT\Property(property: 'valor', type: 'number'),
                    new OAT\Property(property: 'data_vencimento', type: 'string', format: 'date'),
                    new OAT\Property(property: 'descricao', type: 'string'),
                ]
            )
        ),
        responses: [
            new OAT\Response(response: 200, description: 'Promissória atualizada'),
            new OAT\Response(response: 401, description: 'Não autenticado'),
            new OAT\Response(response: 404, description: 'Promissória não encontrada'),
            new OAT\Response(response: 422, description: 'Erro de validação'),
        ]
    )]
    public function promissoriasUpdate(): void
    {
    }

    #[OAT\Delete(
        path: '/promissorias/{promissoria}',
        summary: 'Excluir promissória',
        description: 'Remove uma promissória.',
        tags: ['Promissórias'],
        security: [['sanctum' => []]],
        parameters: [
            new OAT\PathParameter(name: 'promissoria', description: 'ID da promissória', required: true, example: 1),
        ],
        responses: [
            new OAT\Response(response: 200, description: 'Promissória removida'),
            new OAT\Response(response: 401, description: 'Não autenticado'),
            new OAT\Response(response: 404, description: 'Promissória não encontrada'),
        ]
    )]
    public function promissoriasDestroy(): void
    {
    }

    #[OAT\Post(
        path: '/promissorias/{promissoria}/marcar-como-paga',
        summary: 'Marcar como paga',
        description: 'Marca a promissória como paga.',
        tags: ['Promissórias'],
        security: [['sanctum' => []]],
        parameters: [
            new OAT\PathParameter(name: 'promissoria', description: 'ID da promissória', required: true, example: 1),
        ],
        responses: [
            new OAT\Response(response: 200, description: 'Promissória marcada como paga'),
            new OAT\Response(response: 401, description: 'Não autenticado'),
            new OAT\Response(response: 404, description: 'Promissória não encontrada'),
            new OAT\Response(response: 422, description: 'Já está paga'),
        ]
    )]
    public function promissoriasMarcarComoPaga(): void
    {
    }

    #[OAT\Post(
        path: '/promissorias/{promissoria}/pagamento-parcial',
        summary: 'Registrar pagamento parcial',
        description: 'Registra um pagamento parcial (valor_pago, data_pagamento, observacoes opcional).',
        tags: ['Promissórias'],
        security: [['sanctum' => []]],
        parameters: [
            new OAT\PathParameter(name: 'promissoria', description: 'ID da promissória', required: true, example: 1),
        ],
        requestBody: new OAT\RequestBody(
            required: true,
            content: new OAT\JsonContent(
                required: ['valor_pago', 'data_pagamento'],
                properties: [
                    new OAT\Property(property: 'valor_pago', type: 'number', example: 500.00),
                    new OAT\Property(property: 'data_pagamento', type: 'string', format: 'date', example: '2026-02-05'),
                    new OAT\Property(property: 'observacoes', type: 'string'),
                ]
            )
        ),
        responses: [
            new OAT\Response(response: 201, description: 'Pagamento registrado'),
            new OAT\Response(response: 401, description: 'Não autenticado'),
            new OAT\Response(response: 404, description: 'Promissória não encontrada'),
            new OAT\Response(response: 422, description: 'Validação (valor excedente, já paga, cancelada)'),
        ]
    )]
    public function promissoriasPagamentoParcial(): void
    {
    }

    #[OAT\Post(
        path: '/promissorias/{promissoria}/cancelar',
        summary: 'Cancelar promissória',
        description: 'Cancela a promissória (observacoes opcional).',
        tags: ['Promissórias'],
        security: [['sanctum' => []]],
        parameters: [
            new OAT\PathParameter(name: 'promissoria', description: 'ID da promissória', required: true, example: 1),
        ],
        requestBody: new OAT\RequestBody(
            required: false,
            content: new OAT\JsonContent(properties: [
                new OAT\Property(property: 'observacoes', type: 'string'),
            ])
        ),
        responses: [
            new OAT\Response(response: 200, description: 'Promissória cancelada'),
            new OAT\Response(response: 401, description: 'Não autenticado'),
            new OAT\Response(response: 404, description: 'Promissória não encontrada'),
            new OAT\Response(response: 422, description: 'Já paga ou já cancelada'),
        ]
    )]
    public function promissoriasCancelar(): void
    {
    }

    #[OAT\Get(
        path: '/promissorias/{promissoria}/historico-pagamentos',
        summary: 'Histórico de pagamentos',
        description: 'Retorna o histórico de pagamentos parciais da promissória.',
        tags: ['Promissórias'],
        security: [['sanctum' => []]],
        parameters: [
            new OAT\PathParameter(name: 'promissoria', description: 'ID da promissória', required: true, example: 1),
        ],
        responses: [
            new OAT\Response(response: 200, description: 'Histórico de pagamentos'),
            new OAT\Response(response: 401, description: 'Não autenticado'),
            new OAT\Response(response: 404, description: 'Promissória não encontrada'),
        ]
    )]
    public function promissoriasHistoricoPagamentos(): void
    {
    }

    #[OAT\Get(
        path: '/promissorias/resumo/vencimento',
        summary: 'Resumo próximas do vencimento',
        description: 'Retorna resumo de promissórias próximas do vencimento (parâmetro dias, padrão 3).',
        tags: ['Promissórias'],
        security: [['sanctum' => []]],
        parameters: [
            new OAT\QueryParameter(name: 'dias', description: 'Quantidade de dias', required: false, example: 3),
        ],
        responses: [
            new OAT\Response(response: 200, description: 'Resumo'),
            new OAT\Response(response: 401, description: 'Não autenticado'),
        ]
    )]
    public function promissoriasResumoVencimento(): void
    {
    }
}
