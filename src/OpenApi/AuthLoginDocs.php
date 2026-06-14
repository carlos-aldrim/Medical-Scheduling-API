<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Auth')]
#[OA\Post(
    path: '/auth/login',
    summary: 'Authenticate and obtain a JWT token',
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['email', 'password'],
            properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'admin@test.dev'),
                new OA\Property(property: 'password', type: 'string', format: 'password', example: 'Admin@123'),
            ],
        ),
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Authentication successful',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'token', type: 'string'),
                ],
            ),
        ),
        new OA\Response(response: 401, description: 'Invalid credentials'),
    ],
)]
final class AuthLoginDocs
{
}
