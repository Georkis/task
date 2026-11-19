<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Task;
use App\Entity\User;
use App\Repository\TaskRepository;
use App\Service\TaskNotifier;
use App\Service\TaskWorkflow;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[AsController]
final class TaskValidateController extends AbstractController
{
    public function __construct(
        private TaskRepository $tasks,
        private TaskWorkflow $workflow,
        private EntityManagerInterface $entityManager,
        private Security $security,
        private TaskNotifier $notifier,
    ) {
    }

    public function __invoke(int $id): JsonResponse
    {
        $task = $this->tasks->find($id) ?? throw new NotFoundHttpException('Task not found.');

        $admin = $this->security->getUser();

        if (!$admin instanceof User) {
            throw new AccessDeniedHttpException();
        }

        $this->workflow->validate($task, $admin);
        $this->entityManager->flush();

        $this->notifier->validated($task);

        return $this->json($task, context: ['groups' => ['task:read']]);
    }
}
