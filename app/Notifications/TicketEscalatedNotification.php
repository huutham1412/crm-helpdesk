<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class TicketEscalatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Ticket $ticket)
    {
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'ticket_id' => $this->ticket->id,
            'type' => 'ticket_escalated',
            'title' => "Ticket {$this->ticket->ticket_number} cần xử lý!",
            'message' => "Ticket đã quá hạn {$this->ticket->getEscalationThresholdMinutes()} phút và chưa được phản hồi.",
            'data' => [
                'ticket_number' => $this->ticket->ticket_number,
                'subject' => $this->ticket->subject,
                'priority' => $this->ticket->priority,
                'escalation_threshold' => $this->ticket->getEscalationThresholdMinutes(),
                'minutes_elapsed' => $this->ticket->getMinutesSinceResponseStart(),
            ],
        ];
    }
}
