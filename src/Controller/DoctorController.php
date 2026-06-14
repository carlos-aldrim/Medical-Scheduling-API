<?php

namespace App\Controller;

use App\DTO\Doctor\CreateDoctorDTO;
use App\Entity\User;
use App\Http\ApiResponse;
use App\Repository\DoctorRepository;
use App\UseCase\Doctor\CreateDoctorUseCase;
use App\UseCase\Doctor\DeactivateDoctorUseCase;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/doctors')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class DoctorController extends AbstractController
{
    private const GROUPS        = ['doctor', 'doctor_with_specialty', 'specialty'];
    private const GROUPS_SIMPLE = ['doctor'];

    public function __construct(
        private CreateDoctorUseCase $createDoctorUseCase,
        private DeactivateDoctorUseCase $deactivateDoctorUseCase,
        private DoctorRepository $doctorRepository,
        private SerializerInterface $serializer,
    ) {}

    #[Route('', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $doctors = $this->doctorRepository->findAll();
        $data = json_decode($this->serializer->serialize($doctors, 'json', ['groups' => self::GROUPS]), true);

        return ApiResponse::collection($data, count($data));
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $doctor = $this->doctorRepository->find($id);
        if (!$doctor) {
            return ApiResponse::notFound('Doctor not found');
        }

        $data = json_decode($this->serializer->serialize($doctor, 'json', ['groups' => self::GROUPS]), true);

        return ApiResponse::ok($data);
    }

    #[Route('', methods: ['POST'])]
    #[IsGranted(User::ROLE_ADMIN)]
    public function create(
        #[MapRequestPayload] CreateDoctorDTO $dto
    ): JsonResponse {
        $doctor = $this->createDoctorUseCase->execute($dto);
        $data = json_decode($this->serializer->serialize($doctor, 'json', ['groups' => self::GROUPS]), true);

        return ApiResponse::created($data);
    }

    #[Route('/{id}/deactivate', methods: ['PATCH'])]
    #[IsGranted(User::ROLE_ADMIN)]
    public function deactivate(string $id): JsonResponse
    {
        $doctor = $this->deactivateDoctorUseCase->execute($id);
        $data = json_decode($this->serializer->serialize($doctor, 'json', ['groups' => self::GROUPS_SIMPLE]), true);

        return ApiResponse::ok($data);
    }
}
