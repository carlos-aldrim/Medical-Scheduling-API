<?php

namespace App\Tests\Api;

class DoctorControllerTest extends ApiTestCase
{
    public function test_list_returns_all_doctors(): void
    {
        $specialty = $this->createSpecialty();
        $this->createDoctor($specialty, 'Dr. Alpha', 'CRM-SP-10001');
        $this->createDoctor($specialty, 'Dr. Beta',  'CRM-SP-10002');

        $this->createReceptionistUser();
        $token = $this->getToken('reception@test.dev', 'Reception@123');

        $this->jsonRequest('GET', '/doctors', token: $token);

        self::assertSame(200, $this->responseStatus());

        $body = $this->responseJson();
        self::assertTrue($body['success']);
        self::assertCount(2, $body['data']);
    }

    public function test_show_returns_doctor_by_id(): void
    {
        $specialty = $this->createSpecialty();
        $doctor    = $this->createDoctor($specialty, 'Dr. Gamma', 'CRM-SP-20001');

        $this->createReceptionistUser();
        $token = $this->getToken('reception@test.dev', 'Reception@123');

        $this->jsonRequest('GET', '/doctors/' . $doctor->getId(), token: $token);

        self::assertSame(200, $this->responseStatus());

        $body = $this->responseJson();
        self::assertTrue($body['success']);
        self::assertSame('Dr. Gamma', $body['data']['name']);
    }

    public function test_show_returns_404_for_unknown_id(): void
    {
        $this->createReceptionistUser();
        $token = $this->getToken('reception@test.dev', 'Reception@123');

        $this->jsonRequest(
            'GET',
            '/doctors/00000000-0000-4000-8000-000000000000',
            token: $token,
        );

        self::assertSame(404, $this->responseStatus());

        $body = $this->responseJson();
        self::assertFalse($body['success']);
        self::assertSame('NOT_FOUND', $body['error']['code']);
    }

    public function test_admin_can_create_doctor(): void
    {
        $specialty = $this->createSpecialty('Neurology', 'Brain specialist');
        $this->createAdminUser();
        $token = $this->getToken('admin@test.dev', 'Admin@123');

        $this->jsonRequest('POST', '/doctors', [
            'name'                  => 'Dr. House',
            'crm'                   => 'CRM-SP-99999',
            'specialtyId'           => (string) $specialty->getId(),
            'maxAppointmentsPerDay' => 8,
        ], $token);

        self::assertSame(201, $this->responseStatus());

        $body = $this->responseJson();
        self::assertTrue($body['success']);
        self::assertSame('Dr. House', $body['data']['name']);
        self::assertArrayHasKey('id', $body['data']);
    }

    public function test_create_doctor_returns_422_on_missing_fields(): void
    {
        $this->createAdminUser();
        $token = $this->getToken('admin@test.dev', 'Admin@123');

        $this->jsonRequest('POST', '/doctors', [], $token);

        self::assertSame(422, $this->responseStatus());

        $body = $this->responseJson();
        self::assertFalse($body['success']);
        self::assertSame('VALIDATION_ERROR', $body['error']['code']);

        $fields = array_column($body['error']['errors'], 'field');
        self::assertContains('name', $fields);
        self::assertContains('crm', $fields);
        self::assertContains('specialtyId', $fields);
    }

    public function test_create_doctor_returns_403_for_receptionist(): void
    {
        $specialty = $this->createSpecialty();
        $this->createReceptionistUser();
        $token = $this->getToken('reception@test.dev', 'Reception@123');

        $this->jsonRequest('POST', '/doctors', [
            'name'                  => 'Dr. Unauthorized',
            'crm'                   => 'CRM-SP-55555',
            'specialtyId'           => (string) $specialty->getId(),
            'maxAppointmentsPerDay' => 5,
        ], $token);

        self::assertSame(403, $this->responseStatus());
    }

    public function test_admin_can_deactivate_doctor(): void
    {
        $specialty = $this->createSpecialty();
        $doctor    = $this->createDoctor($specialty, 'Dr. Active', 'CRM-SP-30001');
        $this->createAdminUser();
        $token = $this->getToken('admin@test.dev', 'Admin@123');

        $this->jsonRequest(
            'PATCH',
            '/doctors/' . $doctor->getId() . '/deactivate',
            token: $token,
        );

        self::assertSame(200, $this->responseStatus());

        $body = $this->responseJson();
        self::assertTrue($body['success']);
        self::assertFalse($body['data']['isActive']);
    }

    public function test_deactivate_returns_404_for_unknown_doctor(): void
    {
        $this->createAdminUser();
        $token = $this->getToken('admin@test.dev', 'Admin@123');

        $this->jsonRequest(
            'PATCH',
            '/doctors/00000000-0000-4000-8000-000000000000/deactivate',
            token: $token,
        );

        self::assertSame(404, $this->responseStatus());
    }
}
