<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateItemDto
{
    #[Assert\Length(max: 255)]
    public ?string $name = null;

    #[Assert\PositiveOrZero]
    public ?int $quantity = null;

    #[Assert\Length(max: 50)]
    public ?string $unit = null;

    #[Assert\Length(max: 5000)]
    public ?string $notes = null;

    public ?bool $isDone = null;
}
