<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CannedResponse;
use App\Models\Ticket;
use App\Repositories\CannedResponseRepository;
use App\Repositories\TicketRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CannedResponseController extends Controller
{
    protected CannedResponseRepository $cannedResponseRepo;
    protected TicketRepository $ticketRepo;

    public function __construct(
        CannedResponseRepository $cannedResponseRepo,
        TicketRepository $ticketRepo
    ) {
        $this->cannedResponseRepo = $cannedResponseRepo;
        $this->ticketRepo = $ticketRepo;
    }

    /**
     * List all canned responses with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 20);
        $filters = [
            'is_active' => $request->get('is_active'),
            'category_id' => $request->get('category_id'),
            'search' => $request->get('search'),
            'user_id' => $request->get('user_id'),
        ];

        $paginator = $this->cannedResponseRepo->paginateWithFilters($perPage, $filters);

        return response()->json([
            'success' => true,
            'data' => $paginator->items(),
            'pagination' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }

    /**
     * Get all active canned responses (for dropdown).
     */
    public function getAllActive(Request $request): JsonResponse
    {
        $filters = [
            'category_id' => $request->get('category_id'),
            'search' => $request->get('search'),
        ];

        $responses = $this->cannedResponseRepo->getAllActive($filters);

        return response()->json([
            'success' => true,
            'data' => $responses,
        ]);
    }

    /**
     * Get popular canned responses.
     */
    public function popular(Request $request): JsonResponse
    {
        $limit = min($request->get('limit', 10), 50);
        $responses = $this->cannedResponseRepo->getPopular($limit);

        return response()->json([
            'success' => true,
            'data' => $responses,
        ]);
    }

    /**
     * Get single canned response.
     */
    public function show(int $id): JsonResponse
    {
        $response = $this->cannedResponseRepo->findByIdWithRelations($id);

        if (!$response) {
            return response()->json([
                'success' => false,
                'message' => 'Canned response not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $response,
        ]);
    }

    /**
     * Create new canned response.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'category_id' => 'nullable|exists:categories,id',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $data = $validator->validated();
        $data['user_id'] = $request->user()->id;

        $response = $this->cannedResponseRepo->create($data);

        return response()->json([
            'success' => true,
            'message' => 'Canned response created successfully',
            'data' => $response->load(['user', 'category']),
        ], 201);
    }

    /**
     * Update canned response.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'content' => 'sometimes|required|string',
            'category_id' => 'nullable|exists:categories,id',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $response = $this->cannedResponseRepo->update($id, $validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Canned response updated successfully',
            'data' => $response->load(['user', 'category']),
        ]);
    }

    /**
     * Delete canned response.
     */
    public function destroy(int $id): JsonResponse
    {
        $this->cannedResponseRepo->delete($id);

        return response()->json([
            'success' => true,
            'message' => 'Canned response deleted successfully',
        ]);
    }

    /**
     * Preview canned response with variables replaced.
     */
    public function preview(Request $request, int $id): JsonResponse
    {
        $response = $this->cannedResponseRepo->find($id);

        if (!$response) {
            return response()->json([
                'success' => false,
                'message' => 'Canned response not found',
            ], 404);
        }

        $ticketId = $request->get('ticket_id');
        $data = [];

        if ($ticketId) {
            $ticket = $this->ticketRepo->find($ticketId);
            if ($ticket) {
                $data = [
                    'customer_name' => $ticket->user->name ?? '',
                    'ticket_number' => $ticket->ticket_number,
                    'category' => $ticket->category->name ?? '',
                    'subject' => $ticket->subject ?? '',
                ];
            }
        }

        $data['cskh_name'] = $request->user()->name;

        $renderedContent = $response->renderContent($data);

        return response()->json([
            'success' => true,
            'data' => [
                'original_content' => $response->content,
                'rendered_content' => $renderedContent,
                'variables' => $response->variables ?? [],
            ],
        ]);
    }

    /**
     * Increment usage count.
     */
    public function incrementUsage(int $id): JsonResponse
    {
        $response = $this->cannedResponseRepo->find($id);

        if (!$response) {
            return response()->json([
                'success' => false,
                'message' => 'Canned response not found',
            ], 404);
        }

        $this->cannedResponseRepo->incrementUsage($id);

        return response()->json([
            'success' => true,
            'message' => 'Usage count incremented',
        ]);
    }
}
