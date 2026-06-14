<?php

namespace App\Tests\Api;

class HealthControllerTest extends ApiTestCase
{
    public function test_returns_200_with_ok_status(): void
    {
        $this->jsonRequest('GET', '/health');

        self::assertSame(200, $this->responseStatus());

        $body = $this->responseJson();
        self::assertSame('ok', $body['status']);
        self::assertArrayHasKey('checks', $body);
    }

    public function test_database_check_is_present_and_ok(): void
    {
        $this->jsonRequest('GET', '/health');

        $body = $this->responseJson();

        self::assertArrayHasKey('database', $body['checks']);
        self::assertSame('ok', $body['checks']['database']['status']);
    }

    public function test_accessible_without_authentication(): void
    {
        $this->browser->request('GET', '/health');

        self::assertSame(200, $this->responseStatus());
    }

    public function test_echoes_correlation_id_header_from_request(): void
    {
        $correlationId = 'test-correlation-' . uniqid('', true);

        $this->jsonRequest('GET', '/health', headers: ['X-Correlation-Id' => $correlationId]);

        $response = $this->browser->getResponse();
        self::assertSame(
            $correlationId,
            $response->headers->get('X-Correlation-Id'),
            'Response must echo back the X-Correlation-Id sent in the request',
        );
    }

    public function test_generates_correlation_id_when_none_provided(): void
    {
        $this->jsonRequest('GET', '/health');

        $response = $this->browser->getResponse();
        $returnedId = $response->headers->get('X-Correlation-Id');

        self::assertNotEmpty($returnedId, 'Response must contain a generated X-Correlation-Id');
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $returnedId,
        );
    }
}
