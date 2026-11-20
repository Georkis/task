<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\TaskStatusRequest;
use App\Entity\User;
use App\Repository\TaskRepository;
use App\Service\TaskNotifier;
use App\Service\TaskWorkflow;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[AsController]
final class TaskStatusController extends AbstractController
{
    public function __construct(
        private TaskRepository $tasks,
        private TaskWorkflow $workflow,
        private EntityManagerInterface $entityManager,
        private Security $security,
        private TaskNotifier $notifier,
    ) {
    }

    public function __invoke(int $id, #[MapRequestPayload] TaskStatusRequest $payload): JsonResponse
    {
        $task = $this->tasks->find($id) ?? throw new NotFoundHttpException('Task not found.');

        $actor = $this->security->getUser();

        if (!$actor instanceof User) {
            throw new AccessDeniedHttpException();
        }

        $previousStatus = $this->workflow->changeStatus($task, $payload->status, $actor);
        $this->entityManager->flush();

        $this->notifier->statusChanged($task, $previousStatus, $actor);

        return $this->json($task, context: ['groups' => ['task:read']]);
    }
}
