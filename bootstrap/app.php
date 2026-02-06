<?php

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function ($middleware) {
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class => false
        ]);
    })
    ->withExceptions(function ($exceptions) {
        $exceptions->render(function (\Throwable $e, $request) {
            // Apenas para rotas da API
            if ($request->is('api/*')) {
                // Model não encontrado (404)
                // Laravel converte ModelNotFoundException para NotFoundHttpException no route model binding
                $modelNotFoundException = null;
                
                if ($e instanceof ModelNotFoundException) {
                    $modelNotFoundException = $e;
                } elseif ($e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
                    $previous = $e->getPrevious();
                    if ($previous instanceof ModelNotFoundException) {
                        $modelNotFoundException = $previous;
                    }
                }
                
                // Se ainda não encontrou, verifica se a mensagem indica ModelNotFoundException
                if (!$modelNotFoundException && preg_match('/No query results for model \[(.+?)\]/', $e->getMessage(), $matches)) {
                    $modelClass = $matches[1] ?? null;
                    if ($modelClass) {
                        $model = class_basename($modelClass);
                        
                        $message = match($model) {
                            'Cliente' => 'Cliente não encontrado.',
                            'Promissoria' => 'Promissória não encontrada.',
                            'User' => 'Usuário não encontrado.',
                            default => 'Registro não encontrado.',
                        };

                        return response()->json([
                            'success' => false,
                            'status_code' => 404,
                            'message' => $message
                        ], 404);
                    }
                }
                
                if ($modelNotFoundException) {
                    $modelClass = $modelNotFoundException->getModel();
                    // getModel() retorna o nome da classe como string
                    $model = class_basename($modelClass);
                    
                    $message = match($model) {
                        'Cliente' => 'Cliente não encontrado.',
                        'Promissoria' => 'Promissória não encontrada.',
                        'User' => 'Usuário não encontrado.',
                        default => 'Registro não encontrado.',
                    };

                    return response()->json([
                        'success' => false,
                        'status_code' => 404,
                        'message' => $message
                    ], 404);
                }

                // Erro de autenticação (401)
                if ($e instanceof \Illuminate\Auth\AuthenticationException) {
                    return response()->json([
                        'success' => false,
                        'status_code' => 401,
                        'message' => 'Token não enviado ou inválido.'
                    ], 401);
                }

                // Rota não encontrada (404)
                // Só trata se não for ModelNotFoundException envolvida
                if ($e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException 
                    && !($e->getPrevious() instanceof ModelNotFoundException)) {
                    return response()->json([
                        'success' => false,
                        'status_code' => 404,
                        'message' => 'Rota não encontrada.'
                    ], 404);
                }
                
                if ($e instanceof \Symfony\Component\Routing\Exception\RouteNotFoundException) {
                    return response()->json([
                        'success' => false,
                        'status_code' => 404,
                        'message' => 'Rota não encontrada.'
                    ], 404);
                }

                // Erro de validação (422)
                if ($e instanceof \Illuminate\Validation\ValidationException) {
                    return response()->json([
                        'success' => false,
                        'status_code' => 422,
                        'message' => 'Erro de validação',
                        'errors' => $e->errors()
                    ], 422);
                }

                // Erro de autorização (403)
                if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                    return response()->json([
                        'success' => false,
                        'status_code' => 403,
                        'message' => 'Acesso negado. Você não tem permissão para realizar esta ação.'
                    ], 403);
                }

                // Erro genérico (500)
                // Em produção, não mostrar detalhes do erro
                $message = app()->environment('local') 
                    ? $e->getMessage() 
                    : 'Ocorreu um erro interno. Tente novamente mais tarde.';

                return response()->json([
                    'success' => false,
                    'status_code' => 500,
                    'message' => $message
                ], 500);
            }
        });
    })->create();
