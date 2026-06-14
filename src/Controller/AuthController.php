<?php

namespace App\Controller;

use App\DTO\Auth\RegisterDTO;
use App\Entity\User;
use App\Http\ApiResponse;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/auth')]
#[OA\Tag(name: 'Auth')]
class AuthController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
        private UserRepository $userRepository,
        private SerializerInterface $serializer,
    ) {}

    #[Route('/register', methods: ['POST'])]
    #[IsGranted(User::ROLE_ADMIN)]
    #[OA\Post(
        summary: 'Register a new user (admin only)',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(ref: new Model(type: RegisterDTO::class)),
        ),
        responses: [
            new OA\Response(response: 201, description: 'User created'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden, requires ROLE_ADMIN'),
            new OA\Response(response: 409, description: 'E-mail already registered'),
            new OA\Response(response: 422, description: 'Validation error'),
        ],
    )]
    public function register(
        #[MapRequestPayload] RegisterDTO $dto,
    ): JsonResponse {
        if ($this->userRepository->findOneBy(['email' => $dto->email])) {
            return ApiResponse::conflict('E-mail is already registered');
        }

        $user = new User();
        $user->setName($dto->name);
        $user->setEmail($dto->email);
        $user->setRoles([$dto->role]);
        $user->setPassword($this->hasher->hashPassword($user, $dto->password));

        $this->em->persist($user);
        $this->em->flush();

        $data = json_decode($this->serializer->serialize($user, 'json', ['groups' => ['user']]), true);

        return ApiResponse::created($data);
    }

    #[Route('/me', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[OA\Get(
        summary: 'Get the current authenticated user profile',
        responses: [
            new OA\Response(response: 200, description: 'Current user profile'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ],
    )]
    public function me(): JsonResponse
    {
        $data = json_decode($this->serializer->serialize($this->getUser(), 'json', ['groups' => ['user']]), true);

        return ApiResponse::ok($data);
    }
}
