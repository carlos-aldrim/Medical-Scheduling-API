<?php

namespace App\Tests\Api;

use App\Entity\Appointment;
use App\Entity\Doctor;
use App\Entity\Patient;
use App\Enum\AppointmentStatus;

class AppointmentControllerTest extends ApiTestCase
{
    private string $token = '';
    private Doctor $doctor;
    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();

        $specialty     = $this->createSpecialty('Cardiology', 'Heart');
        $this->doctor  = $this->createDoctor($specialty, 'Dr. Heart', 'CRM-SP-40001');
        $this->patient = $this->createPatient('Alice Smith', '52998224725');

        $this->createReceptionistUser();
        $this->token = $this->getToken('reception@test.dev', 'Reception@123');
    }

    public function test_create_appointment_returns_201(): void
    {
        $scheduledAt = (new \DateTime('+2 days'))->format('Y-m-d H:i:s');

        $this->jsonRequest('POST', '/appointments', [
            'doctorId'   => (string) $this->doctor->getId(),
            'patientId'  => (string) $this->patient->getId(),
            'scheduledAt' => $scheduledAt,
            'notes'      => 'Annual check-up',
        ], $this->token);

        self::assertSame(201, $this->responseStatus());

        $body = $this->responseJson();
        self::assertTrue($body['success']);
        self::assertArrayHasKey('id', $body['data']);
        self::assertSame('scheduled', $body['data']['status']);
    }

    public function test_create_appointment_returns_404_for_unknown_doctor(): void
    {
        $this->jsonRequest('POST', '/appointments', [
            'doctorId'    => '00000000-0000-4000-8000-000000000001',
            'patientId'   => (string) $this->patient->getId(),
            'scheduledAt' => (new \DateTime('+2 days'))->format('Y-m-d H:i:s'),
        ], $this->token);

        self::assertSame(404, $this->responseStatus());

        $body = $this->responseJson();
        self::assertFalse($body['success']);
        self::assertStringContainsString('Doctor not found', $body['error']['message']);
    }

    public function test_create_appointment_returns_404_for_unknown_patient(): void
    {
        $this->jsonRequest('POST', '/appointments', [
            'doctorId'    => (string) $this->doctor->getId(),
            'patientId'   => '00000000-0000-4000-8000-000000000002',
            'scheduledAt' => (new \DateTime('+2 days'))->format('Y-m-d H:i:s'),
        ], $this->token);

        self::assertSame(404, $this->responseStatus());

        $body = $this->responseJson();
        self::assertStringContainsString('Patient not found', $body['error']['message']);
    }

    public function test_create_appointment_returns_400_for_past_date(): void
    {
        $this->jsonRequest('POST', '/appointments', [
            'doctorId'    => (string) $this->doctor->getId(),
            'patientId'   => (string) $this->patient->getId(),
            'scheduledAt' => (new \DateTime('-1 day'))->format('Y-m-d H:i:s'),
        ], $this->token);

        self::assertSame(400, $this->responseStatus());

        $body = $this->responseJson();
        self::assertFalse($body['success']);
        self::assertStringContainsString('past', $body['error']['message']);
    }

    public function test_create_appointment_returns_422_for_invalid_payload(): void
    {
        $this->jsonRequest('POST', '/appointments', [], $this->token);

        self::assertSame(422, $this->responseStatus());

        $body = $this->responseJson();
        self::assertFalse($body['success']);
        self::assertSame('VALIDATION_ERROR', $body['error']['code']);

        $fields = array_column($body['error']['errors'], 'field');
        self::assertContains('doctorId', $fields);
        self::assertContains('patientId', $fields);
        self::assertContains('scheduledAt', $fields);
    }

    public function test_create_appointment_returns_422_for_wrong_uuid_format(): void
    {
        $this->jsonRequest('POST', '/appointments', [
            'doctorId'    => 'not-a-uuid',
            'patientId'   => 'also-not-a-uuid',
            'scheduledAt' => (new \DateTime('+2 days'))->format('Y-m-d H:i:s'),
        ], $this->token);

        self::assertSame(422, $this->responseStatus());

        $body = $this->responseJson();
        $fields = array_column($body['error']['errors'], 'field');
        self::assertContains('doctorId', $fields);
        self::assertContains('patientId', $fields);
    }

    public function test_create_appointment_returns_401_without_token(): void
    {
        $this->jsonRequest('POST', '/appointments', [
            'doctorId'    => (string) $this->doctor->getId(),
            'patientId'   => (string) $this->patient->getId(),
            'scheduledAt' => (new \DateTime('+2 days'))->format('Y-m-d H:i:s'),
        ]);

        self::assertSame(401, $this->responseStatus());
    }

    public function test_show_returns_appointment_by_id(): void
    {
        $appointment = $this->createAppointmentFixture('+3 days');

        $this->jsonRequest('GET', '/appointments/' . $appointment->getId(), token: $this->token);

        self::assertSame(200, $this->responseStatus());

        $body = $this->responseJson();
        self::assertTrue($body['success']);
        self::assertSame((string) $appointment->getId(), $body['data']['id']);
    }

    public function test_show_returns_404_for_unknown_appointment(): void
    {
        $this->jsonRequest(
            'GET',
            '/appointments/00000000-0000-4000-8000-000000000099',
            token: $this->token,
        );

        self::assertSame(404, $this->responseStatus());

        $body = $this->responseJson();
        self::assertSame('NOT_FOUND', $body['error']['code']);
    }

    public function test_list_returns_paginated_appointments(): void
    {
        $this->createAppointmentFixture('+1 day');
        $this->createAppointmentFixture('+2 days');

        $this->jsonRequest('GET', '/appointments', token: $this->token);

        self::assertSame(200, $this->responseStatus());

        $body = $this->responseJson();
        self::assertTrue($body['success']);
        self::assertCount(2, $body['data']);
        self::assertArrayHasKey('meta', $body);
        self::assertSame(2, $body['meta']['total']);
    }

    public function test_cancel_appointment_returns_200_with_cancelled_status(): void
    {
        $appointment = $this->createAppointmentFixture('+5 days');

        $this->jsonRequest(
            'PATCH',
            '/appointments/' . $appointment->getId() . '/cancel',
            token: $this->token,
        );

        self::assertSame(200, $this->responseStatus());

        $body = $this->responseJson();
        self::assertTrue($body['success']);
        self::assertSame('cancelled', $body['data']['status']);
    }

    public function test_cancel_appointment_returns_404_for_unknown_id(): void
    {
        $this->jsonRequest(
            'PATCH',
            '/appointments/00000000-0000-4000-8000-000000000099/cancel',
            token: $this->token,
        );

        self::assertSame(404, $this->responseStatus());
    }

    private function createAppointmentFixture(string $scheduledAtModifier): Appointment
    {
        $appointment = new Appointment();
        $appointment->setDoctor($this->doctor);
        $appointment->setPatient($this->patient);
        $appointment->setScheduledAt(new \DateTimeImmutable($scheduledAtModifier));

        $this->em->persist($appointment);
        $this->em->flush();

        return $appointment;
    }
}
