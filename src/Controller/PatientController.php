<?php

namespace App\Controller;

use App\DTO\Patient\CreatePatientDTO;
use App\Repository\PatientRepository;
use App\UseCase\Patient\CreatePatientUseCase;
use App\UseCase\Patient\DeactivatePatientUseCase;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/patients')]
class PatientController extends AbstractController
{
    public function __construct(
        private CreatePatientUseCase $createPatientUseCase,
        private DeactivatePatientUseCase $deactivatePatientUseCase,
        private PatientRepository $patientRepository,
    ) {}

    #[Route('', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $patients = $this->patientRepository->findAll();
        return $this->json($patients, 200, [], ['groups' => ['patient']]);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $patient = $this->patientRepository->find($id);
        if (!$patient) {
            return $this->json(['message' => 'Patient not found'], 404);
        }
        return $this->json($patient, 200, [], ['groups' => ['patient']]);
    }

    #[Route('', methods: ['POST'])]
    public function create(
        #[MapRequestPayload] CreatePatientDTO $dto
    ): JsonResponse {
        $patient = $this->createPatientUseCase->execute($dto);
        return $this->json($patient, 201, [], ['groups' => ['patient']]);
    }

    #[Route('/{id}/deactivate', methods: ['PATCH'])]
    public function deactivate(string $id): JsonResponse
    {
        $patient = $this->deactivatePatientUseCase->execute($id);
        return $this->json($patient, 200, [], ['groups' => ['patient']]);
    }
}
