<?php

namespace App\Http;

use Symfony\Component\HttpFoundation\JsonResponse;

final class ApiResponse
{
    public static function ok(mixed $data, array $context = []): JsonResponse
    {
        return new JsonResponse(
            self::successPayload($data),
            200,
            [],
            json: false
        );
    }

    public static function created(mixed $data, array $context = []): JsonResponse
    {
        return new JsonResponse(
            self::successPayload($data),
            201,
            [],
            json: false
        );
    }

    public static function collection(array $data, int $total = null): JsonResponse
    {
        $payload = ['success' => true, 'data' => $data];

        if ($total !== null) {
            $payload['meta'] = ['total' => $total];
        }

        return new JsonResponse($payload, 200, [], json: false);
    }

    public static function error(string $message, int $status, string $code = null): JsonResponse
    {
        return new JsonResponse([
            'success' => false,
            'error'   => [
                'code'    => $code ?? self::defaultCode($status),
                'message' => $message,
            ],
        ], $status);
    }

    public static function notFound(string $message): JsonResponse
    {
        return self::error($message, 404, 'NOT_FOUND');
    }

    public static function conflict(string $message): JsonResponse
    {
        return self::error($message, 409, 'CONFLICT');
    }

    public static function unprocessable(string $message): JsonResponse
    {
        return self::error($message, 422, 'UNPROCESSABLE');
    }

    public static function badRequest(string $message): JsonResponse
    {
        return self::error($message, 400, 'BAD_REQUEST');
    }

    private static function successPayload(mixed $data): array
    {
        return ['success' => true, 'data' => $data];
    }

    private static function defaultCode(int $status): string
    {
        return match ($status) {
            400     => 'BAD_REQUEST',
            401     => 'UNAUTHORIZED',
            403     => 'FORBIDDEN',
            404     => 'NOT_FOUND',
            409     => 'CONFLICT',
            422     => 'UNPROCESSABLE',
            500     => 'INTERNAL_SERVER_ERROR',
            default => 'ERROR',
        };
    }
}
