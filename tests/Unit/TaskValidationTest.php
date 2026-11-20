<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Task;
use App\Entity\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

final class TaskValidationTest extends TestCase
{
    public function testInvalidTimeRangeIsReported(): void
    {
        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        $worker = (new User())
            ->setEmail('worker@example.com')
            ->setName('Worker')
            ->setRoles([User::ROLE_WORKER]);

        $task = (new Task())
            ->setTitle('Task')
            ->setStartAt(new \DateTimeImmutable('2026-01-01 17:00:00'))
            ->setEndAt(new \DateTimeImmutable('2026-01-01 09:00:00'))
            ->setAssignedTo($worker);

        $violations = $validator->validate($task);

        self::assertGreaterThan(0, $violations->count());

        $paths = [];
        foreach ($violations as $violation) {
            $paths[] = $violation->getPropertyPath();
        }

        self::assertContains('endAt', $paths);
    }

    public function testOnlyWorkersCanBeAssigned(): void
    {
        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        $admin = (new User())
            ->setEmail('admin@example.com')
            ->setName('Admin')
            ->setRoles([User::ROLE_ADMIN]);

        $task = (new Task())
            ->setTitle('Task')
            ->setStartAt(new \DateTimeImmutable('2026-01-01 09:00:00'))
            ->setEndAt(new \DateTimeImmutable('2026-01-01 17:00:00'))
            ->setAssignedTo($admin);

        $violations = $validator->validate($task);

        $paths = [];
        foreach ($violations as $violation) {
            $paths[] = $violation->getPropertyPath();
        }

        self::assertContains('assignedTo', $paths);
    }
}
