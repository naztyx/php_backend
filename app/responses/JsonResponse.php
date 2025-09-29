<?php
// app/responses/JsonResponse.php - Custom response handler for JSON outputs.

namespace App\responses;

use Psr\Http\Message\ResponseInterface as Response;

class JsonResponse {
    public static function is200Response(Response $response, $data, int $status = 200): Response {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }

    public static function is400Response(Response $response, $message, int $status = 400): Response {
        // return self::success($response, ['error' => $message], $status);
        $response->getBody()->write(json_encode(['error' => $message]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }
}