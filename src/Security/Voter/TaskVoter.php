<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Task;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, Task>
 */
final class TaskVoter extends Voter
{
    public const VIEW = 'TASK_VIEW';
    public const UPDATE_STATUS = 'TASK_UPDATE_STATUS';
    public const VALIDATE = 'TASK_VALIDATE';
    public const REJECT = 'TASK_REJECT';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Task
            && \in_array($attribute, [self::VIEW, self::UPDATE_STATUS, self::VALIDATE, self::REJECT], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        if ($user->hasRole(User::ROLE_ADMIN)) {
            return true;
        }

        if (!\in_array($attribute, [self::VIEW, self::UPDATE_STATUS], true)) {
            return false;
        }

        return $subject->getAssignedTo()?->getId() === $user->getId();
    }
}
