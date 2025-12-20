<?php

declare(strict_types=1);

namespace App\Dto;

class TelegramMessageDto
{
    public int $message_id;
    public ?TelegramUserDto $from = null;
    public ?TelegramChatDto $chat = null;
    public ?string $text = null;
    public ?int $date = null;
}
