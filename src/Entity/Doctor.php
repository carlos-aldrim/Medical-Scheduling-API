<?php

namespace App\Entity;

use App\Repository\DoctorRepository;
use App\ValueObject\Crm;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: DoctorRepository::class)]
class Doctor
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['doctor'])]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['doctor'])]
    private ?string $name = null;

    #[ORM\Column(length: 10, unique: true)]
    #[Groups(['doctor'])]
    private ?string $crm = null;

    #[ORM\Column]
    #[Groups(['doctor'])]
    private int $maxAppointmentsPerDay = 10;

    #[ORM\Column]
    #[Groups(['doctor'])]
    private bool $isActive = true;

    #[ORM\ManyToOne(targetEntity: Specialty::class, inversedBy: 'doctors')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['doctor_with_specialty'])]
    private ?Specialty $specialty = null;

    #[ORM\OneToMany(targetEntity: Appointment::class, mappedBy: 'doctor')]
    private Collection $appointments;

    public function __construct()
    {
        $this->appointments = new ArrayCollection();
    }

    public function getId(): ?Uuid { return $this->id; }

    public function getName(): ?string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }

    public function getCrm(): ?string { return $this->crm; }
    public function setCrm(string $crm): static { $this->crm = (new Crm($crm))->formatted(); return $this; }

    public function getMaxAppointmentsPerDay(): int { return $this->maxAppointmentsPerDay; }
    public function setMaxAppointmentsPerDay(int $max): static { $this->maxAppointmentsPerDay = $max; return $this; }

    public function isActive(): bool { return $this->isActive; }
    public function setActive(bool $isActive): static { $this->isActive = $isActive; return $this; }

    public function getSpecialty(): ?Specialty { return $this->specialty; }
    public function setSpecialty(?Specialty $specialty): static { $this->specialty = $specialty; return $this; }

    public function getAppointments(): Collection { return $this->appointments; }
}
