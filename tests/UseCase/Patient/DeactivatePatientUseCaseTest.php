<?php

namespace App\Tests\UseCase\Patient;

use App\Entity\Patient;
use App\Repository\PatientRepository;
use App\UseCase\Patient\DeactivatePatientUseCase;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DeactivatePatientUseCaseTest extends TestCase
{
    private function makePatient(bool $active = true): Patient
    {
        $patient = new Patient();
        $patient->setName('John Doe');
        $patient->setCpf('52998224725');
        $patient->setBirthDate(new \DateTime('1990-01-01'));
        $patient->setActive($active);

        return $patient;
    }

    public function test_should_deactivate_active_patient(): void
    {
        $patient = $this->makePatient(active: true);

        $repo = $this->createMock(PatientRepository::class);
        $repo->method('find')->willReturn($patient);
        $repo->expects($this->once())->method('save');

        $useCase = new DeactivatePatientUseCase($repo);
        $result = $useCase->execute('some-uuid');

        $this->assertFalse($result->isActive());
    }

    public function test_should_throw_when_patient_not_found(): void
    {
        $repo = $this->createMock(PatientRepository::class);
        $repo->method('find')->willReturn(null);

        $useCase = new DeactivatePatientUseCase($repo);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Patient not found');

        $useCase->execute('non-existent-uuid');
    }

    public function test_should_throw_when_patient_already_inactive(): void
    {
        $patient = $this->makePatient(active: false);

        $repo = $this->createMock(PatientRepository::class);
        $repo->method('find')->willReturn($patient);

        $useCase = new DeactivatePatientUseCase($repo);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Patient is already inactive');

        $useCase->execute('some-uuid');
    }

    public function test_should_not_save_when_patient_not_found(): void
    {
        $repo = $this->createMock(PatientRepository::class);
        $repo->method('find')->willReturn(null);
        $repo->expects($this->never())->method('save');

        $useCase = new DeactivatePatientUseCase($repo);

        $this->expectException(NotFoundHttpException::class);
        $useCase->execute('some-uuid');
    }
}
