<?php

namespace App\Events;

use App\Models\Rating;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketRated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Rating $rating;

    /**
     * Create a new event instance.
     */
    public function __construct(Rating $rating)
    {
        $this->rating = $rating;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('ratings.' . $this->rating->rated_user_id),
            new Channel('tickets.' . $this->rating->ticket_id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'ticket.rated';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'rating_id' => $this->rating->id,
            'ticket_id' => $this->rating->ticket_id,
            'ticket_number' => $this->rating->ticket->ticket_number,
            'rating' => (float) number_format($this->rating->rating, 1),
            'comment' => $this->rating->comment,
            'user' => [
                'id' => $this->rating->user->id,
                'name' => $this->rating->user->name,
            ],
            'created_at' => $this->rating->created_at,
        ];
    }
}
