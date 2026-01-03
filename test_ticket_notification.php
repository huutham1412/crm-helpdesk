<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use App\Models\User;
use App\Models\Ticket;
use App\Jobs\SendTelegramNotification;

echo "=== Test Create Ticket & Send Telegram Notification ===\n\n";

// Get first user
$user = User::first();
if (!$user) {
    echo "No user found!\n";
    exit(1);
}

echo "Creating ticket for user: {$user->name}\n";

// Create ticket
$ticket = Ticket::create([
    'user_id' => $user->id,
    'priority' => 'urgent',
    'status' => 'open',
    'subject' => 'Test Ticket ' . now()->format('H:i:s'),
    'description' => 'This is a test ticket to check Telegram notification.',
]);

echo "Ticket created: {$ticket->ticket_number}\n";
echo "Priority: {$ticket->priority}\n\n";

// Dispatch Telegram job
echo "Dispatching Telegram notification job...\n";
SendTelegramNotification::dispatch($ticket, 'urgent_ticket');

echo "Job dispatched!\n";
echo "Jobs in queue: " . DB::table('jobs')->count() . "\n";
