<?php

namespace App\UseCase\Patient;

use App\DTO\Patient\CreatePatientDTO;
use App\Entity\Patient;
use App\Repository\PatientRepository;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class CreatePatientUseCase
{
    public function __construct(
        private PatientRepository $patientRepository,
    ) {}

    public function execute(CreatePatientDTO $dto): Patient
    {
        $existing = $this->patientRepository->findOneBy(['cpf' => $dto->cpf]);
        if ($existing) {
            throw new BadRequestHttpException("CPF '{$dto->cpf}' is already registered");
        }

        $patient = new Patient();
        $patient->setName($dto->name);
        $patient->setCpf($dto->cpf);
        $patient->setBirthDate(new \DateTime($dto->birthDate));

        if ($dto->phone) {
            $patient->setPhone($dto->phone);
        }

        $this->patientRepository->save($patient);

        return $patient;
    }
}
