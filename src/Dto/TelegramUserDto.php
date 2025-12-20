<?php

declare(strict_types=1);

namespace App\Dto;

class TelegramUserDto
{
    public int $id;
    public bool $is_bot;
    public string $first_name;
    public ?string $last_name = null;
    public ?string $username = null;
    public ?string $language_code = null;
}
