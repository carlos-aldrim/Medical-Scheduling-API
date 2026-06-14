<?php

namespace App\Tests\Api;

use App\Enum\UserRole;

class AuthControllerTest extends ApiTestCase
{
    public function test_login_returns_token_for_valid_credentials(): void
    {
        $this->createUser('doctor@test.dev', 'Secret@123', UserRole::Doctor);

        $this->jsonRequest('POST', '/auth/login', [
            'email'    => 'doctor@test.dev',
            'password' => 'Secret@123',
        ]);

        self::assertSame(200, $this->responseStatus());

        $body = $this->responseJson();
        self::assertArrayHasKey('token', $body);
        self::assertNotEmpty($body['token']);
    }

    public function test_login_returns_401_for_wrong_password(): void
    {
        $this->createUser('user@test.dev', 'Correct@123', UserRole::Receptionist);

        $this->jsonRequest('POST', '/auth/login', [
            'email'    => 'user@test.dev',
            'password' => 'Wrong@Password',
        ]);

        self::assertSame(401, $this->responseStatus());
    }

    public function test_login_returns_401_for_unknown_email(): void
    {
        $this->jsonRequest('POST', '/auth/login', [
            'email'    => 'nobody@test.dev',
            'password' => 'Any@Pass123',
        ]);

        self::assertSame(401, $this->responseStatus());
    }

    public function test_me_returns_current_user_profile(): void
    {
        $this->createUser('me@test.dev', 'Me@Pass123', UserRole::Receptionist);
        $token = $this->getToken('me@test.dev', 'Me@Pass123');

        $this->jsonRequest('GET', '/auth/me', token: $token);

        self::assertSame(200, $this->responseStatus());

        $body = $this->responseJson();
        self::assertTrue($body['success']);
        self::assertSame('me@test.dev', $body['data']['email']);
    }

    public function test_me_returns_401_without_token(): void
    {
        $this->jsonRequest('GET', '/auth/me');

        self::assertSame(401, $this->responseStatus());
    }

    public function test_admin_can_register_new_user(): void
    {
        $this->createAdminUser();
        $token = $this->getToken('admin@test.dev', 'Admin@123');

        $this->jsonRequest('POST', '/auth/register', [
            'name'     => 'New Receptionist',
            'email'    => 'newreception@test.dev',
            'password' => 'NewPass@123',
            'role'     => 'ROLE_RECEPTIONIST',
        ], $token);

        self::assertSame(201, $this->responseStatus());

        $body = $this->responseJson();
        self::assertTrue($body['success']);
        self::assertSame('newreception@test.dev', $body['data']['email']);
    }

    public function test_register_returns_409_on_duplicate_email(): void
    {
        $this->createAdminUser();
        $this->createUser('existing@test.dev', 'Pass@123', UserRole::Receptionist);
        $token = $this->getToken('admin@test.dev', 'Admin@123');

        $this->jsonRequest('POST', '/auth/register', [
            'name'     => 'Duplicate',
            'email'    => 'existing@test.dev',
            'password' => 'AnotherPass@123',
            'role'     => 'ROLE_RECEPTIONIST',
        ], $token);

        self::assertSame(409, $this->responseStatus());

        $body = $this->responseJson();
        self::assertFalse($body['success']);
        self::assertSame('CONFLICT', $body['error']['code']);
    }

    public function test_register_returns_403_for_non_admin(): void
    {
        $this->createReceptionistUser();
        $token = $this->getToken('reception@test.dev', 'Reception@123');

        $this->jsonRequest('POST', '/auth/register', [
            'name'     => 'Sneaky User',
            'email'    => 'sneaky@test.dev',
            'password' => 'Sneaky@Pass1',
            'role'     => 'ROLE_RECEPTIONIST',
        ], $token);

        self::assertSame(403, $this->responseStatus());
    }

    public function test_register_returns_422_on_invalid_payload(): void
    {
        $this->createAdminUser();
        $token = $this->getToken('admin@test.dev', 'Admin@123');

        $this->jsonRequest('POST', '/auth/register', [], $token);

        self::assertSame(422, $this->responseStatus());

        $body = $this->responseJson();
        self::assertFalse($body['success']);
        self::assertSame('VALIDATION_ERROR', $body['error']['code']);
        self::assertNotEmpty($body['error']['errors']);
    }
}
