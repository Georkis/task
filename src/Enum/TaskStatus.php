<?php

declare(strict_types=1);

namespace App\Enum;

enum TaskStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Paused = 'paused';
    case Finished = 'finished';
    case Validated = 'validated';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::InProgress => 'In progress',
            self::Paused => 'Paused',
            self::Finished => 'Finished',
            self::Validated => 'Validated',
            self::Rejected => 'Rejected',
        };
    }

    public function isOpen(): bool
    {
        return \in_array($this, [self::Pending, self::InProgress, self::Paused, self::Rejected], true);
    }
}
