<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class CreateItemDto
{
    #[Assert\NotBlank(message: 'Name is required')]
    #[Assert\Length(max: 255)]
    public string $name;

    #[Assert\PositiveOrZero]
    public ?int $quantity = null;

    #[Assert\Length(max: 50)]
    public ?string $unit = null;

    #[Assert\Length(max: 5000)]
    public ?string $notes = null;

    public bool $isDone = false;
}
