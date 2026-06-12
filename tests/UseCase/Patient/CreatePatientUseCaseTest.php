<?php

namespace App\Tests\UseCase\Patient;

use App\DTO\Patient\CreatePatientDTO;
use App\Entity\Patient;
use App\Repository\PatientRepository;
use App\UseCase\Patient\CreatePatientUseCase;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class CreatePatientUseCaseTest extends TestCase
{
    private function makeDTO(string $cpf = '52998224725'): CreatePatientDTO
    {
        $dto = new CreatePatientDTO();
        $dto->name = 'John Doe';
        $dto->cpf = $cpf;
        $dto->birthDate = '1990-05-15';
        $dto->phone = '85999990000';

        return $dto;
    }

    public function test_should_create_patient_successfully(): void
    {
        $repo = $this->createMock(PatientRepository::class);
        $repo->method('findOneBy')->willReturn(null);
        $repo->expects($this->once())->method('save');

        $useCase = new CreatePatientUseCase($repo);
        $patient = $useCase->execute($this->makeDTO());

        $this->assertInstanceOf(Patient::class, $patient);
        $this->assertSame('John Doe', $patient->getName());
        $this->assertSame('52998224725', $patient->getCpf());
        $this->assertSame('85999990000', $patient->getPhone());
        $this->assertTrue($patient->isActive());
    }

    public function test_should_create_patient_without_phone(): void
    {
        $repo = $this->createMock(PatientRepository::class);
        $repo->method('findOneBy')->willReturn(null);

        $useCase = new CreatePatientUseCase($repo);

        $dto = $this->makeDTO();
        $dto->phone = null;

        $patient = $useCase->execute($dto);

        $this->assertNull($patient->getPhone());
    }

    public function test_should_set_birth_date_correctly(): void
    {
        $repo = $this->createMock(PatientRepository::class);
        $repo->method('findOneBy')->willReturn(null);

        $useCase = new CreatePatientUseCase($repo);
        $patient = $useCase->execute($this->makeDTO());

        $this->assertSame('1990-05-15', $patient->getBirthDate()->format('Y-m-d'));
    }

    public function test_should_throw_when_cpf_already_registered(): void
    {
        $existing = new Patient();

        $repo = $this->createMock(PatientRepository::class);
        $repo->method('findOneBy')->willReturn($existing);

        $useCase = new CreatePatientUseCase($repo);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage("CPF '52998224725' is already registered");

        $useCase->execute($this->makeDTO('52998224725'));
    }

    public function test_should_not_save_when_cpf_already_exists(): void
    {
        $repo = $this->createMock(PatientRepository::class);
        $repo->method('findOneBy')->willReturn(new Patient());
        $repo->expects($this->never())->method('save');

        $useCase = new CreatePatientUseCase($repo);

        $this->expectException(BadRequestHttpException::class);
        $useCase->execute($this->makeDTO());
    }
}
