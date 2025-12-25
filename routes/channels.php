<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\Ticket;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// Admin/CSKH can listen to all tickets channel
Broadcast::channel('tickets', function ($user) {
    return $user->hasAnyRole(['Admin', 'CSKH']);
});

// Users can access their own tickets, CSKH/Admin can access all
Broadcast::channel('tickets.{id}', function ($user, $id) {
    $ticket = Ticket::find($id);

    if (!$ticket) {
        return false;
    }

    return $user->id === $ticket->user_id
        || $user->hasAnyRole(['Admin', 'CSKH']);
});

// User-specific notifications
Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Dashboard updates for CSKH/Admin
Broadcast::channel('dashboard', function ($user) {
    return $user->hasAnyRole(['Admin', 'CSKH']);
});
