<?php

namespace App\Entity;

use App\Repository\PatientRepository;
use App\ValueObject\Cpf;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: PatientRepository::class)]
class Patient
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['patient'])]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['patient'])]
    private ?string $name = null;

    #[ORM\Column(length: 11, unique: true)]
    #[Groups(['patient'])]
    private ?string $cpf = null;

    #[ORM\Column(type: 'date')]
    #[Groups(['patient'])]
    private ?\DateTimeInterface $birthDate = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['patient'])]
    private ?string $phone = null;

    #[ORM\Column]
    #[Groups(['patient'])]
    private bool $isActive = true;

    #[ORM\OneToMany(targetEntity: Appointment::class, mappedBy: 'patient')]
    private Collection $appointments;

    public function __construct()
    {
        $this->appointments = new ArrayCollection();
    }

    public function getId(): ?Uuid { return $this->id; }

    public function getName(): ?string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }

    public function getCpf(): ?string { return $this->cpf; }
    public function setCpf(string $cpf): static { $this->cpf = (new Cpf($cpf))->value(); return $this; }

    public function getBirthDate(): ?\DateTimeInterface { return $this->birthDate; }
    public function setBirthDate(\DateTimeInterface $birthDate): static { $this->birthDate = $birthDate; return $this; }

    public function getPhone(): ?string { return $this->phone; }
    public function setPhone(?string $phone): static { $this->phone = $phone; return $this; }

    public function isActive(): bool { return $this->isActive; }
    public function setActive(bool $isActive): static { $this->isActive = $isActive; return $this; }

    public function getAppointments(): Collection { return $this->appointments; }
}
