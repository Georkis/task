<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Task;
use App\Entity\User;
use App\Security\Voter\TaskVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class TaskVoterTest extends TestCase
{
    public function testAdminCanDoEverything(): void
    {
        $admin = $this->user(1, [User::ROLE_ADMIN]);
        $task = $this->task($this->user(10, [User::ROLE_WORKER]), $admin);
        $token = $this->token($admin);

        self::assertSame(Voter::ACCESS_GRANTED, $this->voter()->vote($token, $task, [TaskVoter::VIEW]));
        self::assertSame(Voter::ACCESS_GRANTED, $this->voter()->vote($token, $task, [TaskVoter::UPDATE_STATUS]));
        self::assertSame(Voter::ACCESS_GRANTED, $this->voter()->vote($token, $task, [TaskVoter::VALIDATE]));
        self::assertSame(Voter::ACCESS_GRANTED, $this->voter()->vote($token, $task, [TaskVoter::REJECT]));
    }

    public function testAssignedWorkerCanViewAndUpdateStatus(): void
    {
        $worker = $this->user(10, [User::ROLE_WORKER]);
        $task = $this->task($worker, $this->user(1, [User::ROLE_ADMIN]));
        $token = $this->token($worker);

        self::assertSame(Voter::ACCESS_GRANTED, $this->voter()->vote($token, $task, [TaskVoter::VIEW]));
        self::assertSame(Voter::ACCESS_GRANTED, $this->voter()->vote($token, $task, [TaskVoter::UPDATE_STATUS]));
        self::assertSame(Voter::ACCESS_DENIED, $this->voter()->vote($token, $task, [TaskVoter::VALIDATE]));
        self::assertSame(Voter::ACCESS_DENIED, $this->voter()->vote($token, $task, [TaskVoter::REJECT]));
    }

    public function testAnotherWorkerCannotAccessTheTask(): void
    {
        $task = $this->task($this->user(10, [User::ROLE_WORKER]), $this->user(1, [User::ROLE_ADMIN]));
        $token = $this->token($this->user(11, [User::ROLE_WORKER]));

        self::assertSame(Voter::ACCESS_DENIED, $this->voter()->vote($token, $task, [TaskVoter::VIEW]));
        self::assertSame(Voter::ACCESS_DENIED, $this->voter()->vote($token, $task, [TaskVoter::UPDATE_STATUS]));
    }

    public function testVoterAbstainsOnUnsupportedAttribute(): void
    {
        $admin = $this->user(1, [User::ROLE_ADMIN]);
        $task = $this->task($this->user(10, [User::ROLE_WORKER]), $admin);

        self::assertSame(Voter::ACCESS_ABSTAIN, $this->voter()->vote($this->token($admin), $task, ['SOMETHING_ELSE']));
    }

    private function voter(): TaskVoter
    {
        return new TaskVoter();
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

    private function token(User $user): UsernamePasswordToken
    {
        return new UsernamePasswordToken($user, 'main', $user->getRoles());
    }

    private function task(User $worker, User $admin): Task
    {
        return (new Task())
            ->setTitle('Test task')
            ->setStartAt(new \DateTimeImmutable('2026-01-01 09:00:00'))
            ->setEndAt(new \DateTimeImmutable('2026-01-01 17:00:00'))
            ->setAssignedTo($worker)
            ->setCreatedBy($admin);
    }
}
