<?php

namespace App\Controller;

use App\DTO\Specialty\CreateSpecialtyDTO;
use App\Http\ApiResponse;
use App\Repository\SpecialtyRepository;
use App\UseCase\Specialty\CreateSpecialtyUseCase;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/specialties')]
#[OA\Tag(name: 'Specialties')]
class SpecialtyController extends AbstractController
{
    public function __construct(
        private CreateSpecialtyUseCase $createSpecialtyUseCase,
        private SpecialtyRepository $specialtyRepository,
        private SerializerInterface $serializer,
    ) {}

    #[Route('', methods: ['GET'])]
    #[OA\Get(
        summary: 'List all specialties',
        responses: [
            new OA\Response(response: 200, description: 'List of specialties'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ],
    )]
    public function index(): JsonResponse
    {
        $specialties = $this->specialtyRepository->findAll();
        $data = json_decode($this->serializer->serialize($specialties, 'json', ['groups' => ['specialty']]), true);

        return ApiResponse::collection($data, count($data));
    }

    #[Route('/{id}', methods: ['GET'])]
    #[OA\Get(
        summary: 'Get a specialty by ID',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Specialty found'),
            new OA\Response(response: 404, description: 'Specialty not found'),
        ],
    )]
    public function show(string $id): JsonResponse
    {
        $specialty = $this->specialtyRepository->find($id);
        if (!$specialty) {
            return ApiResponse::notFound('Specialty not found');
        }

        $data = json_decode($this->serializer->serialize($specialty, 'json', ['groups' => ['specialty']]), true);

        return ApiResponse::ok($data);
    }

    #[Route('', methods: ['POST'])]
    #[OA\Post(
        summary: 'Create a new specialty (admin only)',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(ref: new Model(type: CreateSpecialtyDTO::class)),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Specialty created'),
            new OA\Response(response: 403, description: 'Forbidden, requires ROLE_ADMIN'),
            new OA\Response(response: 422, description: 'Validation error'),
        ],
    )]
    public function create(
        #[MapRequestPayload] CreateSpecialtyDTO $dto
    ): JsonResponse {
        $specialty = $this->createSpecialtyUseCase->execute($dto);
        $data = json_decode($this->serializer->serialize($specialty, 'json', ['groups' => ['specialty']]), true);

        return ApiResponse::created($data);
    }
}
