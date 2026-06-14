<?php

namespace App\Controller;

use App\DTO\Appointment\CreateAppointmentDTO;
use App\Entity\User;
use App\Http\ApiResponse;
use App\UseCase\Appointment\CancelAppointmentUseCase;
use App\UseCase\Appointment\CreateAppointmentUseCase;
use App\Repository\AppointmentRepository;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/appointments')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
#[OA\Tag(name: 'Appointments')]
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
    #[OA\Get(
        summary: 'List appointments (paginated, filterable)',
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'doctorId', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'patientId', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 20)),
            new OA\Parameter(name: 'offset', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 0)),
            new OA\Parameter(name: 'orderBy', in: 'query', required: false, schema: new OA\Schema(type: 'string', default: 'scheduledAt')),
            new OA\Parameter(name: 'order', in: 'query', required: false, schema: new OA\Schema(type: 'string', default: 'ASC')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Paginated list of appointments'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ],
    )]
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
    #[OA\Get(
        summary: 'List appointments for a given patient',
        parameters: [
            new OA\Parameter(name: 'patientId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'List of appointments for the patient'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ],
    )]
    public function byPatient(string $patientId): JsonResponse
    {
        $appointments = $this->appointmentRepository->findByPatient($patientId);
        $data = json_decode($this->serializer->serialize($appointments, 'json', ['groups' => self::GROUPS]), true);

        return ApiResponse::collection($data, count($data));
    }

    #[Route('/{id}', methods: ['GET'])]
    #[IsGranted(User::ROLE_RECEPTIONIST)]
    #[OA\Get(
        summary: 'Get an appointment by ID',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Appointment found'),
            new OA\Response(response: 404, description: 'Appointment not found'),
        ],
    )]
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
    #[OA\Post(
        summary: 'Create a new appointment',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(ref: new Model(type: CreateAppointmentDTO::class)),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Appointment created'),
            new OA\Response(response: 400, description: 'Scheduled date is in the past'),
            new OA\Response(response: 404, description: 'Doctor or patient not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ],
    )]
    public function create(
        #[MapRequestPayload] CreateAppointmentDTO $dto
    ): JsonResponse {
        $appointment = $this->createAppointmentUseCase->execute($dto);
        $data = json_decode($this->serializer->serialize($appointment, 'json', ['groups' => self::GROUPS]), true);

        return ApiResponse::created($data);
    }

    #[Route('/{id}/cancel', methods: ['PATCH'])]
    #[IsGranted(User::ROLE_RECEPTIONIST)]
    #[OA\Patch(
        summary: 'Cancel an appointment',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Appointment cancelled'),
            new OA\Response(response: 404, description: 'Appointment not found'),
        ],
    )]
    public function cancel(string $id): JsonResponse
    {
        $appointment = $this->cancelAppointmentUseCase->execute($id);
        $data = json_decode($this->serializer->serialize($appointment, 'json', ['groups' => ['appointment']]), true);

        return ApiResponse::ok($data);
    }
}
