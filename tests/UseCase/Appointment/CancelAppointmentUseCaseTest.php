<?php

namespace App\Tests\UseCase\Appointment;

use App\Entity\Appointment;
use App\Repository\AppointmentRepository;
use App\UseCase\Appointment\CancelAppointmentUseCase;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CancelAppointmentUseCaseTest extends TestCase
{
    private function makeAppointment(string $status = Appointment::STATUS_SCHEDULED): Appointment
    {
        $appointment = new Appointment();

        // Força o status via reflection para simular estados diferentes
        $reflection = new \ReflectionClass($appointment);
        $prop = $reflection->getProperty('status');
        $prop->setAccessible(true);
        $prop->setValue($appointment, $status);

        return $appointment;
    }

    public function test_should_cancel_scheduled_appointment(): void
    {
        $appointment = $this->makeAppointment(Appointment::STATUS_SCHEDULED);

        $repo = $this->createMock(AppointmentRepository::class);
        $repo->method('find')->willReturn($appointment);
        $repo->expects($this->once())->method('save');

        $useCase = new CancelAppointmentUseCase($repo);
        $result = $useCase->execute('some-uuid');

        $this->assertSame(Appointment::STATUS_CANCELLED, $result->getStatus());
    }

    public function test_should_throw_when_appointment_not_found(): void
    {
        $repo = $this->createMock(AppointmentRepository::class);
        $repo->method('find')->willReturn(null);

        $useCase = new CancelAppointmentUseCase($repo);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Appointment not found');

        $useCase->execute('non-existent-uuid');
    }

    public function test_should_throw_when_appointment_already_cancelled(): void
    {
        $appointment = $this->makeAppointment(Appointment::STATUS_CANCELLED);

        $repo = $this->createMock(AppointmentRepository::class);
        $repo->method('find')->willReturn($appointment);

        $useCase = new CancelAppointmentUseCase($repo);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Appointment is already cancelled');

        $useCase->execute('some-uuid');
    }

    public function test_should_throw_when_appointment_is_completed(): void
    {
        $appointment = $this->makeAppointment(Appointment::STATUS_COMPLETED);

        $repo = $this->createMock(AppointmentRepository::class);
        $repo->method('find')->willReturn($appointment);

        $useCase = new CancelAppointmentUseCase($repo);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Cannot cancel a completed appointment');

        $useCase->execute('some-uuid');
    }

    public function test_should_not_save_when_appointment_not_found(): void
    {
        $repo = $this->createMock(AppointmentRepository::class);
        $repo->method('find')->willReturn(null);
        $repo->expects($this->never())->method('save');

        $useCase = new CancelAppointmentUseCase($repo);

        $this->expectException(NotFoundHttpException::class);
        $useCase->execute('some-uuid');
    }
}
