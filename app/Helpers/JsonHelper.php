<?php

namespace App\Helpers;

use Illuminate\Http\Response;

class JsonHelper
{
    /**
     * Sanitiza recursivamente todas as strings para UTF-8 válido.
     */
    public static function sanitizeForJson(mixed $data): mixed
    {
        if (is_array($data)) {
            return array_map([self::class, 'sanitizeForJson'], $data);
        }
        if (is_string($data)) {
            $result = @iconv('UTF-8', 'UTF-8//IGNORE', $data);
            return $result !== false ? $result : '';
        }
        if (is_object($data)) {
            $arr = (array) $data;
            return (object) array_map([self::class, 'sanitizeForJson'], $arr);
        }
        return $data;
    }

    /**
     * Retorna uma Response JSON que ignora caracteres UTF-8 inválidos.
     * Contorna o erro "Malformed UTF-8" do Laravel JsonResponse.
     */
    public static function response(mixed $data, int $status = 200): Response
    {
        $data = self::sanitizeForJson($data);
        $options = JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE;
        $json = json_encode($data, $options);

        if ($json === false) {
            $json = json_encode([
                'success' => false,
                'message' => 'Erro ao processar resposta.',
            ], $options);
            $status = 500;
        }

        return new Response($json, $status, [
            'Content-Type' => 'application/json; charset=UTF-8',
        ]);
    }
}
