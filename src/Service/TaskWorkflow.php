<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Task;
use App\Entity\User;
use App\Enum\TaskStatus;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class TaskWorkflow
{
    /**
     * Statuses a worker may set through PATCH /api/tasks/{id}/status.
     *
     * @var array<string, list<TaskStatus>>
     */
    private const WORKER_TRANSITIONS = [
        TaskStatus::Pending->value => [TaskStatus::InProgress],
        TaskStatus::InProgress->value => [TaskStatus::Paused, TaskStatus::Finished],
        TaskStatus::Paused->value => [TaskStatus::InProgress],
        TaskStatus::Rejected->value => [TaskStatus::InProgress],
    ];

    public function changeStatus(Task $task, TaskStatus $newStatus, User $actor): TaskStatus
    {
        $this->assertCanOperate($task, $actor);

        if (\in_array($newStatus, [TaskStatus::Validated, TaskStatus::Rejected], true)) {
            throw new UnprocessableEntityHttpException('Use the validate or reject endpoint to review a finished task.');
        }

        $previous = $task->getStatus();
        $allowed = self::WORKER_TRANSITIONS[$previous->value] ?? [];

        if (!\in_array($newStatus, $allowed, true)) {
            throw new UnprocessableEntityHttpException(\sprintf(
                'Invalid status transition from "%s" to "%s". Allowed: %s.',
                $previous->value,
                $newStatus->value,
                [] === $allowed ? 'none' : implode(', ', array_map(static fn (TaskStatus $s): string => $s->value, $allowed)),
            ));
        }

        $task->setStatus($newStatus);

        if (TaskStatus::InProgress === $newStatus) {
            $task->setRejectionReason(null);
        }

        return $previous;
    }

    public function validate(Task $task, User $admin): void
    {
        if (!$admin->hasRole(User::ROLE_ADMIN)) {
            throw new AccessDeniedHttpException('Only an administrator can validate a task.');
        }

        if (TaskStatus::Finished !== $task->getStatus()) {
            throw new UnprocessableEntityHttpException('Only tasks with the "finished" status can be validated.');
        }

        $task->setStatus(TaskStatus::Validated);
        $task->setReviewedBy($admin);
        $task->setReviewedAt(new \DateTimeImmutable());
        $task->setRejectionReason(null);
    }

    public function reject(Task $task, User $admin, string $reason): void
    {
        if (!$admin->hasRole(User::ROLE_ADMIN)) {
            throw new AccessDeniedHttpException('Only an administrator can reject a task.');
        }

        if (TaskStatus::Finished !== $task->getStatus()) {
            throw new UnprocessableEntityHttpException('Only tasks with the "finished" status can be rejected.');
        }

        $task->setStatus(TaskStatus::Rejected);
        $task->setReviewedBy($admin);
        $task->setReviewedAt(new \DateTimeImmutable());
        $task->setRejectionReason($reason);
    }

    private function assertCanOperate(Task $task, User $actor): void
    {
        if ($actor->hasRole(User::ROLE_ADMIN)) {
            return;
        }

        if ($task->getAssignedTo()?->getId() !== $actor->getId()) {
            throw new AccessDeniedHttpException('This task is not assigned to you.');
        }
    }
}
