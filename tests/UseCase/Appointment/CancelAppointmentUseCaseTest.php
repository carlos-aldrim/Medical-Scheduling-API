<?php

namespace App\Tests\UseCase\Appointment;

use App\Entity\Appointment;
use App\Enum\AppointmentStatus;
use App\Repository\AppointmentRepository;
use App\UseCase\Appointment\CancelAppointmentUseCase;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class CancelAppointmentUseCaseTest extends TestCase
{
    private function makeAppointment(AppointmentStatus $status = AppointmentStatus::Scheduled): Appointment
    {
        $appointment = new Appointment();

        $reflection = new \ReflectionClass($appointment);
        $prop = $reflection->getProperty('status');
        $prop->setAccessible(true);
        $prop->setValue($appointment, $status);

        return $appointment;
    }

    private function makeUseCase(AppointmentRepository $repo): CancelAppointmentUseCase
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')->willReturn(new Envelope(new \stdClass()));

        return new CancelAppointmentUseCase($repo, $bus);
    }

    public function test_should_cancel_scheduled_appointment(): void
    {
        $appointment = $this->makeAppointment(AppointmentStatus::Scheduled);

        $repo = $this->createMock(AppointmentRepository::class);
        $repo->method('find')->willReturn($appointment);
        $repo->expects($this->once())->method('save');

        $result = $this->makeUseCase($repo)->execute('some-uuid');

        $this->assertSame(AppointmentStatus::Cancelled, $result->getStatus());
    }

    public function test_should_throw_when_appointment_not_found(): void
    {
        $repo = $this->createMock(AppointmentRepository::class);
        $repo->method('find')->willReturn(null);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Appointment not found');

        $this->makeUseCase($repo)->execute('non-existent-uuid');
    }

    public function test_should_throw_when_appointment_already_cancelled(): void
    {
        $appointment = $this->makeAppointment(AppointmentStatus::Cancelled);

        $repo = $this->createMock(AppointmentRepository::class);
        $repo->method('find')->willReturn($appointment);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Appointment is already cancelled');

        $this->makeUseCase($repo)->execute('some-uuid');
    }

    public function test_should_throw_when_appointment_is_completed(): void
    {
        $appointment = $this->makeAppointment(AppointmentStatus::Completed);

        $repo = $this->createMock(AppointmentRepository::class);
        $repo->method('find')->willReturn($appointment);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Cannot cancel a completed appointment');

        $this->makeUseCase($repo)->execute('some-uuid');
    }

    public function test_should_not_save_when_appointment_not_found(): void
    {
        $repo = $this->createMock(AppointmentRepository::class);
        $repo->method('find')->willReturn(null);
        $repo->expects($this->never())->method('save');

        $this->expectException(NotFoundHttpException::class);

        $this->makeUseCase($repo)->execute('some-uuid');
    }
}
