<?php

namespace App\Console\Commands;

use App\Jobs\CheckTicketEscalation;
use App\Models\Ticket;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Command kiểm tra SLA escalation cho các ticket
 *
 * Chạy mỗi phút để check các ticket quá hạn phản hồi
 * Usage: php artisan sla:check
 */
class CheckEscalations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sla:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Kiểm tra SLA escalation cho các ticket chưa được phản hồi';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Checking SLA escalations...');

        // Chỉ check các ticket open và chưa escalate
        $tickets = Ticket::where('status', 'open')
            ->where('is_escalated', false)
            ->get();

        $checkedCount = 0;
        $dispatchedCount = 0;

        foreach ($tickets as $ticket) {
            $checkedCount++;

            // Dispatch job để check ticket
            dispatch(new CheckTicketEscalation($ticket));
            $dispatchedCount++;

            $this->line("Checked: {$ticket->ticket_number} ({$ticket->priority})");
        }

        $this->info("SLA check completed: {$checkedCount} tickets checked, {$dispatchedCount} jobs dispatched.");

        Log::info("SLA escalation check completed", [
            'checked' => $checkedCount,
            'dispatched' => $dispatchedCount,
        ]);

        return Command::SUCCESS;
    }
}
