<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class TaskRejectRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 2000)]
        #[Groups(['task:write'])]
        public string $reason = '',
    ) {
    }
}
