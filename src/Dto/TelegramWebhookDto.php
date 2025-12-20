<?php

declare(strict_types=1);

namespace App\Dto;

class TelegramWebhookDto
{
    public int $update_id;
    public ?TelegramMessageDto $message = null;
    public ?TelegramMessageDto $edited_message = null;
}
