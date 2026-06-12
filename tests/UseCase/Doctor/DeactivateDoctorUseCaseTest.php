<?php

namespace App\Tests\UseCase\Doctor;

use App\Entity\Doctor;
use App\Repository\DoctorRepository;
use App\UseCase\Doctor\DeactivateDoctorUseCase;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DeactivateDoctorUseCaseTest extends TestCase
{
    private function makeDoctor(bool $active = true): Doctor
    {
        $doctor = new Doctor();
        $doctor->setName('Dr. House');
        $doctor->setCrm('CRM12345');
        $doctor->setActive($active);

        return $doctor;
    }

    public function test_should_deactivate_active_doctor(): void
    {
        $doctor = $this->makeDoctor(active: true);

        $repo = $this->createMock(DoctorRepository::class);
        $repo->method('find')->willReturn($doctor);
        $repo->expects($this->once())->method('save');

        $useCase = new DeactivateDoctorUseCase($repo);
        $result = $useCase->execute('some-uuid');

        $this->assertFalse($result->isActive());
    }

    public function test_should_throw_when_doctor_not_found(): void
    {
        $repo = $this->createMock(DoctorRepository::class);
        $repo->method('find')->willReturn(null);

        $useCase = new DeactivateDoctorUseCase($repo);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Doctor not found');

        $useCase->execute('non-existent-uuid');
    }

    public function test_should_throw_when_doctor_already_inactive(): void
    {
        $doctor = $this->makeDoctor(active: false);

        $repo = $this->createMock(DoctorRepository::class);
        $repo->method('find')->willReturn($doctor);

        $useCase = new DeactivateDoctorUseCase($repo);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Doctor is already inactive');

        $useCase->execute('some-uuid');
    }

    public function test_should_not_save_when_doctor_not_found(): void
    {
        $repo = $this->createMock(DoctorRepository::class);
        $repo->method('find')->willReturn(null);
        $repo->expects($this->never())->method('save');

        $useCase = new DeactivateDoctorUseCase($repo);

        $this->expectException(NotFoundHttpException::class);
        $useCase->execute('some-uuid');
    }
}
