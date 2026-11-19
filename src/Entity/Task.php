<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Controller\TaskRejectController;
use App\Controller\TaskStatusController;
use App\Controller\TaskValidateController;
use App\Dto\TaskRejectRequest;
use App\Dto\TaskStatusRequest;
use App\Enum\TaskStatus;
use App\Repository\TaskRepository;
use App\State\TaskCreateProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: TaskRepository::class)]
#[ORM\Table(name: 'tasks')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'Task',
    security: "is_granted('ROLE_WORKER')",
    operations: [
        new GetCollection(security: "is_granted('ROLE_WORKER')"),
        new Get(security: "is_granted('TASK_VIEW', object)"),
        new Post(
            security: "is_granted('ROLE_ADMIN')",
            processor: TaskCreateProcessor::class,
        ),
        new Put(security: "is_granted('ROLE_ADMIN')"),
        new Patch(security: "is_granted('ROLE_ADMIN')"),
        new Delete(security: "is_granted('ROLE_ADMIN')"),
        new Post(
            uriTemplate: '/tasks/{id}/status',
            controller: TaskStatusController::class,
            input: TaskStatusRequest::class,
            output: Task::class,
            name: 'task_status',
            security: "is_granted('TASK_UPDATE_STATUS', object)",
            read: true,
            deserialize: false,
            validate: false,
            write: false,
        ),
        new Post(
            uriTemplate: '/tasks/{id}/validate',
            controller: TaskValidateController::class,
            output: Task::class,
            name: 'task_validate',
            security: "is_granted('TASK_VALIDATE', object)",
            read: true,
            deserialize: false,
            validate: false,
            write: false,
        ),
        new Post(
            uriTemplate: '/tasks/{id}/reject',
            controller: TaskRejectController::class,
            input: TaskRejectRequest::class,
            output: Task::class,
            name: 'task_reject',
            security: "is_granted('TASK_REJECT', object)",
            read: true,
            deserialize: false,
            validate: false,
            write: false,
        ),
    ],
    normalizationContext: ['groups' => ['task:read']],
    denormalizationContext: ['groups' => ['task:write']],
)]
class Task
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['task:read'])]
    #[ApiProperty(writable: false)]
    private ?int $id = null;

    #[ORM\Column(length: 160)]
    #[Groups(['task:read', 'task:write'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 160)]
    private string $title = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['task:read', 'task:write'])]
    #[Assert\Length(max: 5000)]
    private ?string $description = null;

    #[ORM\Column(length: 20, enumType: TaskStatus::class)]
    #[Groups(['task:read'])]
    #[ApiProperty(writable: false)]
    private TaskStatus $status = TaskStatus::Pending;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['task:read', 'task:write'])]
    #[Assert\NotNull]
    private \DateTimeImmutable $startAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['task:read', 'task:write'])]
    #[Assert\NotNull]
    private \DateTimeImmutable $endAt;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    #[Groups(['task:read', 'task:write'])]
    #[Assert\NotNull(message: 'You must assign the task to a worker.')]
    private ?User $assignedTo = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    #[Groups(['task:read'])]
    #[ApiProperty(writable: false)]
    private ?User $createdBy = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['task:read'])]
    #[ApiProperty(writable: false)]
    private ?User $reviewedBy = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['task:read'])]
    #[ApiProperty(writable: false)]
    private ?\DateTimeImmutable $reviewedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['task:read'])]
    #[ApiProperty(writable: false)]
    private ?string $rejectionReason = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['task:read'])]
    #[ApiProperty(writable: false)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['task:read'])]
    #[ApiProperty(writable: false)]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[Assert\Callback]
    public function validateTimeRangeAndAssignee(ExecutionContextInterface $context): void
    {
        if (isset($this->startAt, $this->endAt) && $this->endAt <= $this->startAt) {
            $context->buildViolation('The end date must be after the start date.')
                ->atPath('endAt')
                ->addViolation();
        }

        if (null !== $this->assignedTo && !$this->assignedTo->hasRole(User::ROLE_WORKER)) {
            $context->buildViolation('Tasks can only be assigned to a user with the ROLE_WORKER role.')
                ->atPath('assignedTo')
                ->addViolation();
        }
    }

    #[ORM\PreUpdate]
    public function touchUpdatedAt(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getStatus(): TaskStatus
    {
        return $this->status;
    }

    public function setStatus(TaskStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getStartAt(): \DateTimeImmutable
    {
        return $this->startAt;
    }

    public function setStartAt(\DateTimeImmutable $startAt): static
    {
        $this->startAt = $startAt;

        return $this;
    }

    public function getEndAt(): \DateTimeImmutable
    {
        return $this->endAt;
    }

    public function setEndAt(\DateTimeImmutable $endAt): static
    {
        $this->endAt = $endAt;

        return $this;
    }

    public function getAssignedTo(): ?User
    {
        return $this->assignedTo;
    }

    public function setAssignedTo(User $assignedTo): static
    {
        $this->assignedTo = $assignedTo;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getReviewedBy(): ?User
    {
        return $this->reviewedBy;
    }

    public function setReviewedBy(?User $reviewedBy): static
    {
        $this->reviewedBy = $reviewedBy;

        return $this;
    }

    public function getReviewedAt(): ?\DateTimeImmutable
    {
        return $this->reviewedAt;
    }

    public function setReviewedAt(?\DateTimeImmutable $reviewedAt): static
    {
        $this->reviewedAt = $reviewedAt;

        return $this;
    }

    public function getRejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function setRejectionReason(?string $rejectionReason): static
    {
        $this->rejectionReason = $rejectionReason;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
