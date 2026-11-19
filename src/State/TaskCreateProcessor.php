<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Task;
use App\Entity\User;
use App\Enum\TaskStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProcessorInterface<Task, Task>
 */
final readonly class TaskCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Task
    {
        if (!$data instanceof Task) {
            throw new \LogicException('Expected a Task instance.');
        }

        $currentUser = $this->security->getUser();

        if (!$currentUser instanceof User || !$currentUser->hasRole(User::ROLE_ADMIN)) {
            throw new AccessDeniedHttpException('Only an administrator can create tasks.');
        }

        $assignee = $data->getAssignedTo();

        if (null === $assignee || !$assignee->hasRole(User::ROLE_WORKER)) {
            throw new UnprocessableEntityHttpException('Tasks can only be assigned to a user with the ROLE_WORKER role.');
        }

        $data->setCreatedBy($currentUser);
        $data->setStatus(TaskStatus::Pending);

        $this->entityManager->persist($data);
        $this->entityManager->flush();

        return $data;
    }
}
