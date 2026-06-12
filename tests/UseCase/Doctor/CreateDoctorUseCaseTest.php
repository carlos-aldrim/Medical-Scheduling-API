<?php

namespace App\Tests\UseCase\Doctor;

use App\DTO\Doctor\CreateDoctorDTO;
use App\Entity\Doctor;
use App\Entity\Specialty;
use App\Repository\DoctorRepository;
use App\Repository\SpecialtyRepository;
use App\UseCase\Doctor\CreateDoctorUseCase;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CreateDoctorUseCaseTest extends TestCase
{
    private function makeSpecialty(): Specialty
    {
        $specialty = new Specialty();
        $specialty->setName('Cardiology');
        $specialty->setDescription('Heart specialist');

        return $specialty;
    }

    private function makeDTO(string $crm = 'CRM12345'): CreateDoctorDTO
    {
        $dto = new CreateDoctorDTO();
        $dto->name = 'Dr. House';
        $dto->crm = $crm;
        $dto->specialtyId = 'e1b2c3d4-0000-0000-0000-000000000001';
        $dto->maxAppointmentsPerDay = 10;

        return $dto;
    }

    public function test_should_create_doctor_successfully(): void
    {
        $specialty = $this->makeSpecialty();

        $specialtyRepo = $this->createMock(SpecialtyRepository::class);
        $specialtyRepo->method('find')->willReturn($specialty);

        $doctorRepo = $this->createMock(DoctorRepository::class);
        $doctorRepo->method('findOneBy')->willReturn(null);
        $doctorRepo->expects($this->once())->method('save');

        $useCase = new CreateDoctorUseCase($doctorRepo, $specialtyRepo);
        $doctor = $useCase->execute($this->makeDTO());

        $this->assertInstanceOf(Doctor::class, $doctor);
        $this->assertSame('Dr. House', $doctor->getName());
        $this->assertSame('CRM12345', $doctor->getCrm());
        $this->assertSame($specialty, $doctor->getSpecialty());
        $this->assertTrue($doctor->isActive());
        $this->assertSame(10, $doctor->getMaxAppointmentsPerDay());
    }

    public function test_should_throw_when_specialty_not_found(): void
    {
        $specialtyRepo = $this->createMock(SpecialtyRepository::class);
        $specialtyRepo->method('find')->willReturn(null);

        $useCase = new CreateDoctorUseCase(
            $this->createMock(DoctorRepository::class),
            $specialtyRepo,
        );

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Specialty not found');

        $useCase->execute($this->makeDTO());
    }

    public function test_should_throw_when_crm_already_registered(): void
    {
        $specialtyRepo = $this->createMock(SpecialtyRepository::class);
        $specialtyRepo->method('find')->willReturn($this->makeSpecialty());

        $existingDoctor = new Doctor();
        $doctorRepo = $this->createMock(DoctorRepository::class);
        $doctorRepo->method('findOneBy')->willReturn($existingDoctor);

        $useCase = new CreateDoctorUseCase($doctorRepo, $specialtyRepo);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage("CRM 'CRM12345' is already registered");

        $useCase->execute($this->makeDTO('CRM12345'));
    }

    public function test_should_not_save_when_crm_already_exists(): void
    {
        $specialtyRepo = $this->createMock(SpecialtyRepository::class);
        $specialtyRepo->method('find')->willReturn($this->makeSpecialty());

        $doctorRepo = $this->createMock(DoctorRepository::class);
        $doctorRepo->method('findOneBy')->willReturn(new Doctor());
        $doctorRepo->expects($this->never())->method('save');

        $useCase = new CreateDoctorUseCase($doctorRepo, $specialtyRepo);

        $this->expectException(BadRequestHttpException::class);
        $useCase->execute($this->makeDTO());
    }
}
