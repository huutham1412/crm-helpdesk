<?php

namespace App\Events;

use App\Models\Ticket;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $ticket;

    /**
     * Create a new event instance.
     */
    public function __construct(Ticket $ticket)
    {
        $this->ticket = $ticket;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('tickets'),
            new PrivateChannel('tickets.' . $this->ticket->id),
            new PrivateChannel('user.' . $this->ticket->user_id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'ticket.updated';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        $this->ticket->load('assignedTo', 'category');

        return [
            'ticket' => [
                'id' => $this->ticket->id,
                'ticket_number' => $this->ticket->ticket_number,
                'subject' => $this->ticket->subject,
                'status' => $this->ticket->status,
                'priority' => $this->ticket->priority,
                'assigned_to' => $this->ticket->assigned_to ? [
                    'id' => $this->ticket->assignedTo->id,
                    'name' => $this->ticket->assignedTo->name,
                ] : null,
                'category' => $this->ticket->category ? [
                    'id' => $this->ticket->category->id,
                    'name' => $this->ticket->category->name,
                ] : null,
                'updated_at' => $this->ticket->updated_at->toISOString(),
            ],
        ];
    }
}
