<?php

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
        $exceptions->render(function (Throwable $e, $request) {

            if ($e instanceof Illuminate\Auth\AuthenticationException) {
                return response()->json([
                    'message' => 'Token não enviado ou inválido.'
                ], 401);
            }

            if ($e instanceof Symfony\Component\Routing\Exception\RouteNotFoundException) {
                return response()->json([
                    'message' => 'Acesso negado — rota exige token.'
                ], 401);
            }
        });
    })->create();
