<?php

namespace App\Controller;

use App\DTO\Specialty\CreateSpecialtyDTO;
use App\Repository\SpecialtyRepository;
use App\UseCase\Specialty\CreateSpecialtyUseCase;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/specialties')]
class SpecialtyController extends AbstractController
{
    public function __construct(
        private CreateSpecialtyUseCase $createSpecialtyUseCase,
        private SpecialtyRepository $specialtyRepository,
    ) {}

    #[Route('', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $specialties = $this->specialtyRepository->findAll();
        return $this->json($specialties, 200, [], ['groups' => ['specialty']]);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $specialty = $this->specialtyRepository->find($id);
        if (!$specialty) {
            return $this->json(['message' => 'Specialty not found'], 404);
        }
        return $this->json($specialty, 200, [], ['groups' => ['specialty']]);
    }

    #[Route('', methods: ['POST'])]
    public function create(
        #[MapRequestPayload] CreateSpecialtyDTO $dto
    ): JsonResponse {
        $specialty = $this->createSpecialtyUseCase->execute($dto);
        return $this->json($specialty, 201, [], ['groups' => ['specialty']]);
    }
}
