<?php

namespace App\UseCase\Specialty;

use App\DTO\Specialty\CreateSpecialtyDTO;
use App\Entity\Specialty;
use App\Repository\SpecialtyRepository;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class CreateSpecialtyUseCase
{
    public function __construct(
        private SpecialtyRepository $specialtyRepository,
    ) {}

    public function execute(CreateSpecialtyDTO $dto): Specialty
    {
        $existing = $this->specialtyRepository->findOneBy(['name' => $dto->name]);
        if ($existing) {
            throw new BadRequestHttpException("Specialty '{$dto->name}' already exists");
        }

        $specialty = new Specialty();
        $specialty->setName($dto->name);
        $specialty->setDescription($dto->description);

        $this->specialtyRepository->save($specialty);

        return $specialty;
    }
}
