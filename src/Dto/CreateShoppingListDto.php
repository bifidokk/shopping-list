<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class CreateShoppingListDto
{
    #[Assert\NotBlank(message: 'Name is required')]
    #[Assert\Length(max: 255)]
    public string $name;

    #[Assert\Length(max: 5000)]
    public ?string $description = null;
}
