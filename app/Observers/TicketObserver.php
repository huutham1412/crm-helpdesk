<?php

namespace App\Observers;

use App\Models\Ticket;
use App\Services\ActivityLogService;

class TicketObserver
{
    public function __construct(protected ActivityLogService $logger)
    {
    }

    public function created(Ticket $ticket): void
    {
        $this->logger->log(
            'ticket.created',
            $ticket,
            "Tạo ticket {$ticket->ticket_number} bởi {$ticket->user->name}",
            [],
            'info',
            ['ticket']
        );
    }

    public function updated(Ticket $ticket): void
    {
        $changes = $this->getTrackedChanges($ticket);

        if (empty($changes)) {
            return;
        }

        // Log specific actions for important field changes
        if (isset($changes['status'])) {
            $this->logger->log(
                'ticket.status_changed',
                $ticket,
                "Ticket {$ticket->ticket_number}: trạng thái thay đổi từ {$changes['status']['old']} sang {$changes['status']['new']}",
                ['status' => $changes['status']],
                'info',
                ['ticket', 'status']
            );
        }

        if (isset($changes['assigned_to'])) {
            $action = !empty($changes['assigned_to']['new']) ? 'ticket.assigned' : 'ticket.unassigned';
            $assignee = !empty($changes['assigned_to']['new']) ? $ticket->assignedTo?->name : 'bỏ gán';
            $this->logger->log(
                $action,
                $ticket,
                "Ticket {$ticket->ticket_number}: gán cho {$assignee}",
                ['assigned_to' => $changes['assigned_to']],
                'info',
                ['ticket', 'assignment']
            );
        }

        if (isset($changes['priority'])) {
            $this->logger->log(
                'ticket.updated',
                $ticket,
                "Ticket {$ticket->ticket_number}: độ ưu tiên thay đổi",
                ['priority' => $changes['priority']],
                'info',
                ['ticket']
            );
        }

        // Log general update with all changes
        $this->logger->logUpdated($ticket, $changes);
    }

    public function deleted(Ticket $ticket): void
    {
        $this->logger->log(
            'ticket.deleted',
            $ticket,
            "Xóa ticket {$ticket->ticket_number}",
            [],
            'warning',
            ['ticket']
        );
    }

    protected function getTrackedChanges(Ticket $ticket): array
    {
        $changes = [];
        $trackedFields = ['status', 'priority', 'assigned_to', 'category_id', 'subject'];

        foreach ($trackedFields as $field) {
            if ($ticket->isDirty($field)) {
                $old = $ticket->getOriginal($field);
                $new = $ticket->$field;

                if ($field === 'assigned_to') {
                    $oldUser = \App\Models\User::find($old);
                    $newUser = $ticket->assignedTo;
                    $old = $oldUser?->name;
                    $new = $newUser?->name;
                }

                if ($field === 'category_id') {
                    $oldCategory = \App\Models\Category::find($old);
                    $newCategory = $ticket->category;
                    $old = $oldCategory?->name;
                    $new = $newCategory?->name;
                }

                $changes[$field] = ['old' => $old, 'new' => $new];
            }
        }

        return $changes;
    }
}
