<?php

namespace App\UseCase\Patient;

use App\Entity\Patient;
use App\Repository\PatientRepository;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DeactivatePatientUseCase
{
    public function __construct(
        private PatientRepository $patientRepository,
    ) {}

    public function execute(string $id): Patient
    {
        $patient = $this->patientRepository->find($id);
        if (!$patient) {
            throw new NotFoundHttpException('Patient not found');
        }

        if (!$patient->isActive()) {
            throw new BadRequestHttpException('Patient is already inactive');
        }

        $patient->setActive(false);
        $this->patientRepository->save($patient);

        return $patient;
    }
}
