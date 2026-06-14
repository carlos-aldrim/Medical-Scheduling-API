<?php

namespace App\Controller;

use App\DTO\Patient\CreatePatientDTO;
use App\Entity\User;
use App\Http\ApiResponse;
use App\Repository\PatientRepository;
use App\UseCase\Patient\CreatePatientUseCase;
use App\UseCase\Patient\DeactivatePatientUseCase;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/patients')]
#[IsGranted(User::ROLE_RECEPTIONIST)]
class PatientController extends AbstractController
{
    public function __construct(
        private CreatePatientUseCase $createPatientUseCase,
        private DeactivatePatientUseCase $deactivatePatientUseCase,
        private PatientRepository $patientRepository,
        private SerializerInterface $serializer,
    ) {}

    #[Route('', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $patients = $this->patientRepository->findAll();
        $data = json_decode($this->serializer->serialize($patients, 'json', ['groups' => ['patient']]), true);

        return ApiResponse::collection($data, count($data));
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $patient = $this->patientRepository->find($id);
        if (!$patient) {
            return ApiResponse::notFound('Patient not found');
        }

        $data = json_decode($this->serializer->serialize($patient, 'json', ['groups' => ['patient']]), true);

        return ApiResponse::ok($data);
    }

    #[Route('', methods: ['POST'])]
    public function create(
        #[MapRequestPayload] CreatePatientDTO $dto
    ): JsonResponse {
        $patient = $this->createPatientUseCase->execute($dto);
        $data = json_decode($this->serializer->serialize($patient, 'json', ['groups' => ['patient']]), true);

        return ApiResponse::created($data);
    }

    #[Route('/{id}/deactivate', methods: ['PATCH'])]
    public function deactivate(string $id): JsonResponse
    {
        $patient = $this->deactivatePatientUseCase->execute($id);
        $data = json_decode($this->serializer->serialize($patient, 'json', ['groups' => ['patient']]), true);

        return ApiResponse::ok($data);
    }
}
