<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Broadcast;

class BroadcastController
{
    /**
     * Authenticate broadcasting channels for SPA with Sanctum
     */
    public function authenticate(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $channelName = $request->input('channel_name');
        $socketId = $request->input('socket_id');

        // Check channel authorization
        $authorized = $this->authorizeChannel($user, $channelName);

        if (!$authorized) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        // Use Laravel's built-in broadcast authentication
        // This will generate the correct auth signature format for Reverb
        $auth = Broadcast::auth($request);

        return response()->json($auth);
    }

    /**
     * Check if user can access the channel
     */
    private function authorizeChannel($user, $channelName): bool
    {
        // Public channels
        if (!str_starts_with($channelName, 'private-') && !str_starts_with($channelName, 'presence-')) {
            return true;
        }

        // Private tickets.{id} channel
        if (str_starts_with($channelName, 'private-tickets.')) {
            $ticketId = str_replace('private-tickets.', '', $channelName);
            $ticket = Ticket::find($ticketId);

            if (!$ticket) {
                return false;
            }

            return $user->id === $ticket->user_id || $user->hasAnyRole(['Admin', 'CSKH']);
        }

        // Private user.{id} channel
        if (str_starts_with($channelName, 'private-user.')) {
            $userId = str_replace('private-user.', '', $channelName);
            return (int) $user->id === (int) $userId;
        }

        // Private dashboard channel (CSKH/Admin only)
        if ($channelName === 'private-dashboard') {
            return $user->hasAnyRole(['Admin', 'CSKH']);
        }

        // Public tickets channel (CSKH/Admin only)
        if ($channelName === 'tickets') {
            return $user->hasAnyRole(['Admin', 'CSKH']);
        }

        return false;
    }
}
