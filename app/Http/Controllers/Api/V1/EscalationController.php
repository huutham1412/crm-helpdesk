<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TicketEscalation;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Escalation Controller - Quản lý lịch sử escalation (Admin only)
 */
class EscalationController extends Controller
{
    /**
     * List all escalations with pagination
     */
    public function index(Request $request): JsonResponse
    {
        $query = TicketEscalation::with(['ticket.user', 'ticket.category', 'ticket.assignedTo']);

        // Filters
        if ($request->has('level')) {
            $query->where('escalation_level', $request->level);
        }

        if ($request->has('resolved')) {
            if ($request->boolean('resolved')) {
                $query->where('is_resolved', true);
            } else {
                $query->unresolved();
            }
        }

        if ($request->has('ticket_id')) {
            $query->where('ticket_id', $request->ticket_id);
        }

        $escalations = $query->orderBy('escalated_at', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'data' => $escalations,
        ]);
    }

    /**
     * Get single escalation details
     */
    public function show(string $id): JsonResponse
    {
        $escalation = TicketEscalation::with(['ticket.user', 'ticket.category', 'ticket.assignedTo', 'ticket.messages'])
            ->find($id);

        if (!$escalation) {
            return response()->json([
                'success' => false,
                'message' => 'Escalation not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $escalation,
        ]);
    }

    /**
     * Get escalation statistics
     */
    public function statistics(): JsonResponse
    {
        $total = TicketEscalation::count();
        $unresolved = TicketEscalation::unresolved()->count();
        $warnings = TicketEscalation::warning()->count();
        $escalated = TicketEscalation::escalated()->count();

        // Top tickets with most escalations
        $topEscalatedTickets = TicketEscalation::select('ticket_id')
            ->selectRaw('COUNT(*) as escalation_count')
            ->groupBy('ticket_id')
            ->orderBy('escalation_count', 'desc')
            ->limit(5)
            ->with('ticket')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $total,
                'unresolved' => $unresolved,
                'warnings' => $warnings,
                'escalated' => $escalated,
                'top_tickets' => $topEscalatedTickets,
            ],
        ]);
    }
}
