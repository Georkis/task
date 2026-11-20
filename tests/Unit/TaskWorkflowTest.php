<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Task;
use App\Entity\User;
use App\Enum\TaskStatus;
use App\Service\TaskWorkflow;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class TaskWorkflowTest extends TestCase
{
    private TaskWorkflow $workflow;

    protected function setUp(): void
    {
        $this->workflow = new TaskWorkflow();
    }

    public function testWorkerCanMoveThroughTheHappyPath(): void
    {
        $worker = $this->user(10, [User::ROLE_WORKER]);
        $admin = $this->user(1, [User::ROLE_ADMIN]);
        $task = $this->task($worker, $admin);

        self::assertSame(TaskStatus::Pending, $this->workflow->changeStatus($task, TaskStatus::InProgress, $worker));
        self::assertSame(TaskStatus::InProgress, $task->getStatus());

        $this->workflow->changeStatus($task, TaskStatus::Paused, $worker);
        self::assertSame(TaskStatus::Paused, $task->getStatus());

        $this->workflow->changeStatus($task, TaskStatus::InProgress, $worker);
        $this->workflow->changeStatus($task, TaskStatus::Finished, $worker);
        self::assertSame(TaskStatus::Finished, $task->getStatus());

        $this->workflow->validate($task, $admin);

        self::assertSame(TaskStatus::Validated, $task->getStatus());
        self::assertSame($admin, $task->getReviewedBy());
        self::assertNotNull($task->getReviewedAt());
    }

    public function testWorkerCannotSkipStatuses(): void
    {
        $worker = $this->user(10, [User::ROLE_WORKER]);
        $task = $this->task($worker, $this->user(1, [User::ROLE_ADMIN]));

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->workflow->changeStatus($task, TaskStatus::Finished, $worker);
    }

    public function testWorkerCannotUpdateAnotherWorkerTask(): void
    {
        $assignedWorker = $this->user(10, [User::ROLE_WORKER]);
        $otherWorker = $this->user(11, [User::ROLE_WORKER]);
        $task = $this->task($assignedWorker, $this->user(1, [User::ROLE_ADMIN]));

        $this->expectException(AccessDeniedHttpException::class);

        $this->workflow->changeStatus($task, TaskStatus::InProgress, $otherWorker);
    }

    public function testWorkerCannotValidate(): void
    {
        $worker = $this->user(10, [User::ROLE_WORKER]);
        $task = $this->task($worker, $this->user(1, [User::ROLE_ADMIN]));
        $task->setStatus(TaskStatus::Finished);

        $this->expectException(AccessDeniedHttpException::class);

        $this->workflow->validate($task, $worker);
    }

    public function testOnlyFinishedTasksCanBeValidated(): void
    {
        $worker = $this->user(10, [User::ROLE_WORKER]);
        $admin = $this->user(1, [User::ROLE_ADMIN]);
        $task = $this->task($worker, $admin);

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->workflow->validate($task, $admin);
    }

    public function testRejectionAndReopenClearsTheReason(): void
    {
        $worker = $this->user(10, [User::ROLE_WORKER]);
        $admin = $this->user(1, [User::ROLE_ADMIN]);
        $task = $this->task($worker, $admin);
        $task->setStatus(TaskStatus::Finished);

        $this->workflow->reject($task, $admin, 'Missing pictures.');

        self::assertSame(TaskStatus::Rejected, $task->getStatus());
        self::assertSame('Missing pictures.', $task->getRejectionReason());
        self::assertSame($admin, $task->getReviewedBy());

        $this->workflow->changeStatus($task, TaskStatus::InProgress, $worker);

        self::assertSame(TaskStatus::InProgress, $task->getStatus());
        self::assertNull($task->getRejectionReason());
    }

    /**
     * @param list<string> $roles
     */
    private function user(int $id, array $roles): User
    {
        $user = (new User())
            ->setEmail(\sprintf('user%d@example.com', $id))
            ->setName(\sprintf('User %d', $id))
            ->setRoles($roles);

        $property = new \ReflectionProperty(User::class, 'id');
        $property->setValue($user, $id);

        return $user;
    }

    private function task(User $worker, User $admin): Task
    {
        return (new Task())
            ->setTitle('Test task')
            ->setDescription('Test description')
            ->setStartAt(new \DateTimeImmutable('2026-01-01 09:00:00'))
            ->setEndAt(new \DateTimeImmutable('2026-01-01 17:00:00'))
            ->setAssignedTo($worker)
            ->setCreatedBy($admin);
    }
}
