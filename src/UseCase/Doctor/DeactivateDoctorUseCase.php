<?php

namespace App\UseCase\Doctor;

use App\Entity\Doctor;
use App\Repository\DoctorRepository;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DeactivateDoctorUseCase
{
    public function __construct(
        private DoctorRepository $doctorRepository,
    ) {}

    public function execute(string $id): Doctor
    {
        $doctor = $this->doctorRepository->find($id);
        if (!$doctor) {
            throw new NotFoundHttpException('Doctor not found');
        }

        if (!$doctor->isActive()) {
            throw new BadRequestHttpException('Doctor is already inactive');
        }

        $doctor->setActive(false);
        $this->doctorRepository->save($doctor);

        return $doctor;
    }
}
