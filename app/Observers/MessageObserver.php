<?php

namespace App\Observers;

use App\Models\Message;
use App\Services\ActivityLogService;

class MessageObserver
{
    public function __construct(protected ActivityLogService $logger)
    {
    }

    public function created(Message $message): void
    {
        $ticket = $message->ticket;
        $sender = $message->user->name;

        $this->logger->log(
            'message.created',
            $message,
            "Tin nhắn mới từ {$sender} trong ticket {$ticket->ticket_number}",
            [],
            'info',
            ['message']
        );
    }

    public function deleted(Message $message): void
    {
        $ticket = $message->ticket;

        $this->logger->log(
            'message.deleted',
            $message,
            "Xóa tin nhắn trong ticket {$ticket->ticket_number}",
            [],
            'warning',
            ['message']
        );
    }
}
