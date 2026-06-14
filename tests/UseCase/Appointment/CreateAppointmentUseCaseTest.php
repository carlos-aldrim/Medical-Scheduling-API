<?php

namespace App\Tests\UseCase\Appointment;

use App\DTO\Appointment\CreateAppointmentDTO;
use App\Entity\Appointment;
use App\Entity\Doctor;
use App\Entity\Patient;
use App\Entity\Specialty;
use App\Enum\AppointmentStatus;
use App\Repository\AppointmentRepository;
use App\Repository\DoctorRepository;
use App\Repository\PatientRepository;
use App\UseCase\Appointment\CreateAppointmentUseCase;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class CreateAppointmentUseCaseTest extends TestCase
{
    private function makeDoctor(bool $active = true, int $maxPerDay = 10): Doctor
    {
        $specialty = new Specialty();
        $specialty->setName('Cardiology');
        $specialty->setDescription('Heart specialist');

        $doctor = new Doctor();
        $doctor->setName('Dr. House');
        $doctor->setCrm('CRM12345');
        $doctor->setActive($active);
        $doctor->setMaxAppointmentsPerDay($maxPerDay);
        $doctor->setSpecialty($specialty);

        return $doctor;
    }

    private function makePatient(bool $active = true): Patient
    {
        $patient = new Patient();
        $patient->setName('John Doe');
        $patient->setCpf('52998224725');
        $patient->setBirthDate(new \DateTime('1990-01-01'));
        $patient->setActive($active);

        return $patient;
    }

    private function makeDTO(?string $scheduledAt = null): CreateAppointmentDTO
    {
        $dto = new CreateAppointmentDTO();
        $dto->doctorId   = 'e1b2c3d4-0000-0000-0000-000000000001';
        $dto->patientId  = 'e1b2c3d4-0000-0000-0000-000000000002';
        $dto->scheduledAt = $scheduledAt ?? (new \DateTime('+1 day'))->format('Y-m-d H:i:s');
        $dto->notes       = null;

        return $dto;
    }

    private function makeUseCase(
        AppointmentRepository $appointmentRepo,
        DoctorRepository      $doctorRepo,
        PatientRepository     $patientRepo,
    ): CreateAppointmentUseCase {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('wrapInTransaction')->willReturnCallback(fn(callable $cb) => $cb());

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')->willReturn(new Envelope(new \stdClass()));

        return new CreateAppointmentUseCase(
            $appointmentRepo,
            $doctorRepo,
            $patientRepo,
            $em,
            $bus,
        );
    }

    public function test_should_create_appointment_successfully(): void
    {
        $doctor  = $this->makeDoctor();
        $patient = $this->makePatient();

        $doctorRepo = $this->createMock(DoctorRepository::class);
        $doctorRepo->method('find')->willReturn($doctor);

        $patientRepo = $this->createMock(PatientRepository::class);
        $patientRepo->method('find')->willReturn($patient);

        $appointmentRepo = $this->createMock(AppointmentRepository::class);
        $appointmentRepo->method('hasConflict')->willReturn(false);
        $appointmentRepo->method('countByDoctorAndDate')->willReturn(0);
        $appointmentRepo->expects($this->once())->method('save');

        $appointment = $this->makeUseCase($appointmentRepo, $doctorRepo, $patientRepo)
            ->execute($this->makeDTO());

        $this->assertInstanceOf(Appointment::class, $appointment);
        $this->assertSame(AppointmentStatus::Scheduled, $appointment->getStatus());
        $this->assertSame($doctor, $appointment->getDoctor());
        $this->assertSame($patient, $appointment->getPatient());
    }

    public function test_should_throw_when_doctor_not_found(): void
    {
        $doctorRepo = $this->createMock(DoctorRepository::class);
        $doctorRepo->method('find')->willReturn(null);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Doctor not found');

        $this->makeUseCase(
            $this->createMock(AppointmentRepository::class),
            $doctorRepo,
            $this->createMock(PatientRepository::class),
        )->execute($this->makeDTO());
    }

    public function test_should_throw_when_doctor_is_inactive(): void
    {
        $doctorRepo = $this->createMock(DoctorRepository::class);
        $doctorRepo->method('find')->willReturn($this->makeDoctor(active: false));

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Doctor is not active');

        $this->makeUseCase(
            $this->createMock(AppointmentRepository::class),
            $doctorRepo,
            $this->createMock(PatientRepository::class),
        )->execute($this->makeDTO());
    }

    public function test_should_throw_when_patient_not_found(): void
    {
        $doctorRepo = $this->createMock(DoctorRepository::class);
        $doctorRepo->method('find')->willReturn($this->makeDoctor());

        $patientRepo = $this->createMock(PatientRepository::class);
        $patientRepo->method('find')->willReturn(null);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Patient not found');

        $this->makeUseCase(
            $this->createMock(AppointmentRepository::class),
            $doctorRepo,
            $patientRepo,
        )->execute($this->makeDTO());
    }

    public function test_should_throw_when_patient_is_inactive(): void
    {
        $doctorRepo = $this->createMock(DoctorRepository::class);
        $doctorRepo->method('find')->willReturn($this->makeDoctor());

        $patientRepo = $this->createMock(PatientRepository::class);
        $patientRepo->method('find')->willReturn($this->makePatient(active: false));

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Patient is not active');

        $this->makeUseCase(
            $this->createMock(AppointmentRepository::class),
            $doctorRepo,
            $patientRepo,
        )->execute($this->makeDTO());
    }

    public function test_should_throw_when_scheduling_in_the_past(): void
    {
        $doctorRepo = $this->createMock(DoctorRepository::class);
        $doctorRepo->method('find')->willReturn($this->makeDoctor());

        $patientRepo = $this->createMock(PatientRepository::class);
        $patientRepo->method('find')->willReturn($this->makePatient());

        $pastDate = (new \DateTime('-1 day'))->format('Y-m-d H:i:s');

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Cannot schedule appointment in the past');

        $this->makeUseCase(
            $this->createMock(AppointmentRepository::class),
            $doctorRepo,
            $patientRepo,
        )->execute($this->makeDTO($pastDate));
    }

    public function test_should_throw_when_time_conflict_exists(): void
    {
        $doctorRepo = $this->createMock(DoctorRepository::class);
        $doctorRepo->method('find')->willReturn($this->makeDoctor());

        $patientRepo = $this->createMock(PatientRepository::class);
        $patientRepo->method('find')->willReturn($this->makePatient());

        $appointmentRepo = $this->createMock(AppointmentRepository::class);
        $appointmentRepo->method('hasConflict')->willReturn(true);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Doctor already has an appointment in this time slot');

        $this->makeUseCase($appointmentRepo, $doctorRepo, $patientRepo)
            ->execute($this->makeDTO());
    }

    public function test_should_throw_when_daily_limit_reached(): void
    {
        $doctor = $this->makeDoctor(maxPerDay: 3);

        $doctorRepo = $this->createMock(DoctorRepository::class);
        $doctorRepo->method('find')->willReturn($doctor);

        $patientRepo = $this->createMock(PatientRepository::class);
        $patientRepo->method('find')->willReturn($this->makePatient());

        $appointmentRepo = $this->createMock(AppointmentRepository::class);
        $appointmentRepo->method('hasConflict')->willReturn(false);
        $appointmentRepo->method('countByDoctorAndDate')->willReturn(3);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Doctor has reached the maximum of 3 appointments for this day');

        $this->makeUseCase($appointmentRepo, $doctorRepo, $patientRepo)
            ->execute($this->makeDTO());
    }

    public function test_should_persist_notes_when_provided(): void
    {
        $doctorRepo = $this->createMock(DoctorRepository::class);
        $doctorRepo->method('find')->willReturn($this->makeDoctor());

        $patientRepo = $this->createMock(PatientRepository::class);
        $patientRepo->method('find')->willReturn($this->makePatient());

        $appointmentRepo = $this->createMock(AppointmentRepository::class);
        $appointmentRepo->method('hasConflict')->willReturn(false);
        $appointmentRepo->method('countByDoctorAndDate')->willReturn(0);

        $dto = $this->makeDTO();
        $dto->notes = 'Patient has allergies';

        $appointment = $this->makeUseCase($appointmentRepo, $doctorRepo, $patientRepo)
            ->execute($dto);

        $this->assertSame('Patient has allergies', $appointment->getNotes());
    }
}
