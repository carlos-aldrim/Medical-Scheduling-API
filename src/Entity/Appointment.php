<?php

namespace App\Entity;

use App\Repository\AppointmentRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: AppointmentRepository::class)]
class Appointment
{
    const STATUS_SCHEDULED = 'scheduled';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['appointment'])]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Doctor::class, inversedBy: 'appointments')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['appointment_with_relations'])]
    private ?Doctor $doctor = null;

    #[ORM\ManyToOne(targetEntity: Patient::class, inversedBy: 'appointments')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['appointment_with_relations'])]
    private ?Patient $patient = null;

    #[ORM\Column(type: 'datetime')]
    #[Groups(['appointment'])]
    private ?\DateTimeInterface $scheduledAt = null;

    #[ORM\Column(length: 20)]
    #[Groups(['appointment'])]
    private string $status = self::STATUS_SCHEDULED;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['appointment'])]
    private ?string $notes = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['appointment'])]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid { return $this->id; }

    public function getDoctor(): ?Doctor { return $this->doctor; }
    public function setDoctor(?Doctor $doctor): static { $this->doctor = $doctor; return $this; }

    public function getPatient(): ?Patient { return $this->patient; }
    public function setPatient(?Patient $patient): static { $this->patient = $patient; return $this; }

    public function getScheduledAt(): ?\DateTimeInterface { return $this->scheduledAt; }
    public function setScheduledAt(\DateTimeInterface $scheduledAt): static { $this->scheduledAt = $scheduledAt; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): static { $this->notes = $notes; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function cancel(): static { $this->status = self::STATUS_CANCELLED; return $this; }
    public function complete(): static { $this->status = self::STATUS_COMPLETED; return $this; }
}
