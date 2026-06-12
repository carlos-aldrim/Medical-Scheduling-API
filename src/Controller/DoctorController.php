<?php

namespace App\Controller;

use App\DTO\Doctor\CreateDoctorDTO;
use App\Repository\DoctorRepository;
use App\UseCase\Doctor\CreateDoctorUseCase;
use App\UseCase\Doctor\DeactivateDoctorUseCase;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/doctors')]
class DoctorController extends AbstractController
{
    public function __construct(
        private CreateDoctorUseCase $createDoctorUseCase,
        private DeactivateDoctorUseCase $deactivateDoctorUseCase,
        private DoctorRepository $doctorRepository,
    ) {}

    #[Route('', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $doctors = $this->doctorRepository->findAll();
        return $this->json($doctors, 200, [], [
            'groups' => ['doctor', 'doctor_with_specialty', 'specialty']
        ]);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $doctor = $this->doctorRepository->find($id);
        if (!$doctor) {
            return $this->json(['message' => 'Doctor not found'], 404);
        }
        return $this->json($doctor, 200, [], [
            'groups' => ['doctor', 'doctor_with_specialty', 'specialty']
        ]);
    }

    #[Route('', methods: ['POST'])]
    public function create(
        #[MapRequestPayload] CreateDoctorDTO $dto
    ): JsonResponse {
        $doctor = $this->createDoctorUseCase->execute($dto);
        return $this->json($doctor, 201, [], [
            'groups' => ['doctor', 'doctor_with_specialty', 'specialty']
        ]);
    }

    #[Route('/{id}/deactivate', methods: ['PATCH'])]
    public function deactivate(string $id): JsonResponse
    {
        $doctor = $this->deactivateDoctorUseCase->execute($id);
        return $this->json($doctor, 200, [], ['groups' => ['doctor']]);
    }
}
