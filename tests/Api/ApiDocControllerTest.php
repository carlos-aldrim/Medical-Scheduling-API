<?php

namespace App\Tests\Api;

class ApiDocControllerTest extends ApiTestCase
{
    public function test_swagger_json_is_accessible_without_authentication(): void
    {
        $this->browser->request('GET', '/api/doc.json');

        self::assertSame(200, $this->responseStatus());

        $body = $this->responseJson();
        self::assertArrayHasKey('openapi', $body);
        self::assertArrayHasKey('paths', $body);
        self::assertArrayHasKey('/health', $body['paths']);
        self::assertArrayHasKey('/appointments', $body['paths']);
    }

    public function test_swagger_ui_is_accessible_without_authentication(): void
    {
        $this->browser->request('GET', '/api/doc');

        self::assertSame(200, $this->responseStatus());
    }
}
