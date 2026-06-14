<?php

namespace App\Controller;

use App\DTO\Patient\CreatePatientDTO;
use App\Entity\User;
use App\Http\ApiResponse;
use App\Repository\PatientRepository;
use App\UseCase\Patient\CreatePatientUseCase;
use App\UseCase\Patient\DeactivatePatientUseCase;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/patients')]
#[IsGranted(User::ROLE_RECEPTIONIST)]
#[OA\Tag(name: 'Patients')]
class PatientController extends AbstractController
{
    public function __construct(
        private CreatePatientUseCase $createPatientUseCase,
        private DeactivatePatientUseCase $deactivatePatientUseCase,
        private PatientRepository $patientRepository,
        private SerializerInterface $serializer,
    ) {}

    #[Route('', methods: ['GET'])]
    #[OA\Get(
        summary: 'List all patients',
        responses: [
            new OA\Response(response: 200, description: 'List of patients'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ],
    )]
    public function index(): JsonResponse
    {
        $patients = $this->patientRepository->findAll();
        $data = json_decode($this->serializer->serialize($patients, 'json', ['groups' => ['patient']]), true);

        return ApiResponse::collection($data, count($data));
    }

    #[Route('/{id}', methods: ['GET'])]
    #[OA\Get(
        summary: 'Get a patient by ID',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Patient found'),
            new OA\Response(response: 404, description: 'Patient not found'),
        ],
    )]
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
    #[OA\Post(
        summary: 'Create a new patient',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(ref: new Model(type: CreatePatientDTO::class)),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Patient created'),
            new OA\Response(response: 422, description: 'Validation error'),
        ],
    )]
    public function create(
        #[MapRequestPayload] CreatePatientDTO $dto
    ): JsonResponse {
        $patient = $this->createPatientUseCase->execute($dto);
        $data = json_decode($this->serializer->serialize($patient, 'json', ['groups' => ['patient']]), true);

        return ApiResponse::created($data);
    }

    #[Route('/{id}/deactivate', methods: ['PATCH'])]
    #[OA\Patch(
        summary: 'Deactivate a patient',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Patient deactivated'),
            new OA\Response(response: 404, description: 'Patient not found'),
        ],
    )]
    public function deactivate(string $id): JsonResponse
    {
        $patient = $this->deactivatePatientUseCase->execute($id);
        $data = json_decode($this->serializer->serialize($patient, 'json', ['groups' => ['patient']]), true);

        return ApiResponse::ok($data);
    }
}
