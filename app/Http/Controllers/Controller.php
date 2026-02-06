<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

abstract class Controller
{
    use AuthorizesRequests;

    protected function responseError(string $message, int $code = 500, ?string $detail = null): JsonResponse
    {
        $payload = [
            'success' => false,
            'status_code' => $code,
            'message' => $message,
        ];
        if ($detail !== null) {
            $payload['error'] = $detail;
        }
        return response()->json($payload, $code);
    }
}
