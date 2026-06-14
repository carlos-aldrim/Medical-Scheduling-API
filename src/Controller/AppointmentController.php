<?php

namespace App\Controller;

use App\DTO\Appointment\CreateAppointmentDTO;
use App\Entity\User;
use App\Http\ApiResponse;
use App\UseCase\Appointment\CancelAppointmentUseCase;
use App\UseCase\Appointment\CreateAppointmentUseCase;
use App\Repository\AppointmentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/appointments')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class AppointmentController extends AbstractController
{
    private const GROUPS = ['appointment', 'appointment_with_relations', 'doctor', 'patient'];

    public function __construct(
        private CreateAppointmentUseCase $createAppointmentUseCase,
        private CancelAppointmentUseCase $cancelAppointmentUseCase,
        private AppointmentRepository    $appointmentRepository,
        private SerializerInterface      $serializer,
    ) {}

    #[Route('', methods: ['GET'])]
    #[IsGranted(User::ROLE_RECEPTIONIST)]
    public function index(Request $request): JsonResponse
    {
        $filters = array_filter([
            'status'    => $request->query->get('status'),
            'date'      => $request->query->get('date'),
            'doctorId'  => $request->query->get('doctorId'),
            'patientId' => $request->query->get('patientId'),
        ]);

        $limit   = (int) $request->query->get('limit',   20);
        $offset  = (int) $request->query->get('offset',  0);
        $orderBy = (string) $request->query->get('orderBy', 'scheduledAt');
        $order   = (string) $request->query->get('order',   'ASC');

        $result = $this->appointmentRepository->findWithFilters(
            $filters,
            $limit,
            $offset,
            $orderBy,
            $order,
        );

        $data = json_decode(
            $this->serializer->serialize($result['data'], 'json', ['groups' => self::GROUPS]),
            true,
        );

        $payload = [
            'success' => true,
            'data'    => $data,
            'meta'    => [
                'total'   => $result['total'],
                'limit'   => $result['limit'],
                'offset'  => $result['offset'],
                'hasMore' => ($result['offset'] + count($data)) < $result['total'],
            ],
        ];

        return new JsonResponse($payload, 200, [], json: false);
    }

    #[Route('/patient/{patientId}', methods: ['GET'])]
    #[IsGranted(User::ROLE_RECEPTIONIST)]
    public function byPatient(string $patientId): JsonResponse
    {
        $appointments = $this->appointmentRepository->findByPatient($patientId);
        $data = json_decode($this->serializer->serialize($appointments, 'json', ['groups' => self::GROUPS]), true);

        return ApiResponse::collection($data, count($data));
    }

    #[Route('/{id}', methods: ['GET'])]
    #[IsGranted(User::ROLE_RECEPTIONIST)]
    public function show(string $id): JsonResponse
    {
        $appointment = $this->appointmentRepository->find($id);
        if (!$appointment) {
            return ApiResponse::notFound('Appointment not found');
        }

        $data = json_decode($this->serializer->serialize($appointment, 'json', ['groups' => self::GROUPS]), true);

        return ApiResponse::ok($data);
    }

    #[Route('', methods: ['POST'])]
    #[IsGranted(User::ROLE_RECEPTIONIST)]
    public function create(
        #[MapRequestPayload] CreateAppointmentDTO $dto
    ): JsonResponse {
        $appointment = $this->createAppointmentUseCase->execute($dto);
        $data = json_decode($this->serializer->serialize($appointment, 'json', ['groups' => self::GROUPS]), true);

        return ApiResponse::created($data);
    }

    #[Route('/{id}/cancel', methods: ['PATCH'])]
    #[IsGranted(User::ROLE_RECEPTIONIST)]
    public function cancel(string $id): JsonResponse
    {
        $appointment = $this->cancelAppointmentUseCase->execute($id);
        $data = json_decode($this->serializer->serialize($appointment, 'json', ['groups' => ['appointment']]), true);

        return ApiResponse::ok($data);
    }
}
