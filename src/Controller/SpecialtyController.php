<?php

namespace App\Controller;

use App\DTO\Specialty\CreateSpecialtyDTO;
use App\Http\ApiResponse;
use App\Repository\SpecialtyRepository;
use App\UseCase\Specialty\CreateSpecialtyUseCase;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/specialties')]
class SpecialtyController extends AbstractController
{
    public function __construct(
        private CreateSpecialtyUseCase $createSpecialtyUseCase,
        private SpecialtyRepository $specialtyRepository,
        private SerializerInterface $serializer,
    ) {}

    #[Route('', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $specialties = $this->specialtyRepository->findAll();
        $data = json_decode($this->serializer->serialize($specialties, 'json', ['groups' => ['specialty']]), true);

        return ApiResponse::collection($data, count($data));
    }

    #[Route('/{id}', methods: ['GET'])]
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
    public function create(
        #[MapRequestPayload] CreateSpecialtyDTO $dto
    ): JsonResponse {
        $specialty = $this->createSpecialtyUseCase->execute($dto);
        $data = json_decode($this->serializer->serialize($specialty, 'json', ['groups' => ['specialty']]), true);

        return ApiResponse::created($data);
    }
}
