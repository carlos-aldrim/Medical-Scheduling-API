<?php

namespace App\Controller;

use App\DTO\Appointment\CreateAppointmentDTO;
use App\UseCase\Appointment\CancelAppointmentUseCase;
use App\UseCase\Appointment\CreateAppointmentUseCase;
use App\Repository\AppointmentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/appointments')]
class AppointmentController extends AbstractController
{
    public function __construct(
        private CreateAppointmentUseCase $createAppointmentUseCase,
        private CancelAppointmentUseCase $cancelAppointmentUseCase,
        private AppointmentRepository $appointmentRepository,
    ) {}

    #[Route('', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $appointments = $this->appointmentRepository->findAll();
        return $this->json($appointments, 200, [], [
            'groups' => ['appointment', 'appointment_with_relations', 'doctor', 'patient']
        ]);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $appointment = $this->appointmentRepository->find($id);
        if (!$appointment) {
            return $this->json(['message' => 'Appointment not found'], 404);
        }
        return $this->json($appointment, 200, [], [
            'groups' => ['appointment', 'appointment_with_relations', 'doctor', 'patient']
        ]);
    }

    #[Route('', methods: ['POST'])]
    public function create(
        #[MapRequestPayload] CreateAppointmentDTO $dto
    ): JsonResponse {
        $appointment = $this->createAppointmentUseCase->execute($dto);
        return $this->json($appointment, 201, [], [
            'groups' => ['appointment', 'appointment_with_relations', 'doctor', 'patient']
        ]);
    }

    #[Route('/{id}/cancel', methods: ['PATCH'])]
    public function cancel(string $id): JsonResponse
    {
        $appointment = $this->cancelAppointmentUseCase->execute($id);
        return $this->json($appointment, 200, [], ['groups' => ['appointment']]);
    }

    #[Route('/patient/{patientId}', methods: ['GET'])]
    public function byPatient(string $patientId): JsonResponse
    {
        $appointments = $this->appointmentRepository->findByPatient($patientId);
        return $this->json($appointments, 200, [], [
            'groups' => ['appointment', 'appointment_with_relations', 'doctor', 'patient']
        ]);
    }
}
