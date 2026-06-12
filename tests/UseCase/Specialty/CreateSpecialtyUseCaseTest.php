<?php

namespace App\Tests\UseCase\Specialty;

use App\DTO\Specialty\CreateSpecialtyDTO;
use App\Entity\Specialty;
use App\Repository\SpecialtyRepository;
use App\UseCase\Specialty\CreateSpecialtyUseCase;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class CreateSpecialtyUseCaseTest extends TestCase
{
    private function makeDTO(string $name = 'Cardiology'): CreateSpecialtyDTO
    {
        $dto = new CreateSpecialtyDTO();
        $dto->name = $name;
        $dto->description = 'Heart and cardiovascular specialist';

        return $dto;
    }

    public function test_should_create_specialty_successfully(): void
    {
        $repo = $this->createMock(SpecialtyRepository::class);
        $repo->method('findOneBy')->willReturn(null);
        $repo->expects($this->once())->method('save');

        $useCase = new CreateSpecialtyUseCase($repo);
        $specialty = $useCase->execute($this->makeDTO());

        $this->assertInstanceOf(Specialty::class, $specialty);
        $this->assertSame('Cardiology', $specialty->getName());
        $this->assertSame('Heart and cardiovascular specialist', $specialty->getDescription());
    }

    public function test_should_throw_when_specialty_already_exists(): void
    {
        $existing = new Specialty();

        $repo = $this->createMock(SpecialtyRepository::class);
        $repo->method('findOneBy')->willReturn($existing);

        $useCase = new CreateSpecialtyUseCase($repo);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage("Specialty 'Cardiology' already exists");

        $useCase->execute($this->makeDTO('Cardiology'));
    }

    public function test_should_not_save_when_specialty_already_exists(): void
    {
        $repo = $this->createMock(SpecialtyRepository::class);
        $repo->method('findOneBy')->willReturn(new Specialty());
        $repo->expects($this->never())->method('save');

        $useCase = new CreateSpecialtyUseCase($repo);

        $this->expectException(BadRequestHttpException::class);
        $useCase->execute($this->makeDTO());
    }
}
