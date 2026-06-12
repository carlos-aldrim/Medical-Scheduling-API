<?php

namespace App\UseCase\Doctor;

use App\DTO\Doctor\CreateDoctorDTO;
use App\Entity\Doctor;
use App\Repository\DoctorRepository;
use App\Repository\SpecialtyRepository;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CreateDoctorUseCase
{
    public function __construct(
        private DoctorRepository $doctorRepository,
        private SpecialtyRepository $specialtyRepository,
    ) {}

    public function execute(CreateDoctorDTO $dto): Doctor
    {
        $specialty = $this->specialtyRepository->find($dto->specialtyId);
        if (!$specialty) {
            throw new NotFoundHttpException('Specialty not found');
        }

        $existing = $this->doctorRepository->findOneBy(['crm' => $dto->crm]);
        if ($existing) {
            throw new BadRequestHttpException("CRM '{$dto->crm}' is already registered");
        }

        $doctor = new Doctor();
        $doctor->setName($dto->name);
        $doctor->setCrm($dto->crm);
        $doctor->setSpecialty($specialty);
        $doctor->setMaxAppointmentsPerDay($dto->maxAppointmentsPerDay);

        $this->doctorRepository->save($doctor);

        return $doctor;
    }
}
