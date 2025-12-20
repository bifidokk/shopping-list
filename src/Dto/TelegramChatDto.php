<?php

declare(strict_types=1);

namespace App\Dto;

class TelegramChatDto
{
    public int $id;
    public string $type; // "private", "group", "supergroup", "channel"
    public ?string $title = null;
    public ?string $username = null;
    public ?string $first_name = null;
    public ?string $last_name = null;
}
