<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class ShareListDto
{
    #[Assert\NotBlank(message: 'Telegram username is required')]
    #[Assert\Length(min: 5, max: 32)]
    #[Assert\Regex(pattern: '/^@?[a-zA-Z0-9_]{5,32}$/', message: 'Invalid Telegram username format')]
    public string $telegramUsername;
}
