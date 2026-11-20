<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\TaskStatus;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class TaskStatusRequest
{
    public function __construct(
        #[Assert\NotNull]
        #[Groups(['task:write'])]
        public TaskStatus $status,
    ) {
    }
}
