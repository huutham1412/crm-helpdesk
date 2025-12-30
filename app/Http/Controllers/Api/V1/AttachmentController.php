<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Ticket;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AttachmentController extends Controller
{
    public function __construct(
        private FileUploadService $uploadService
    ) {}

    /**
     * Upload attachment to ticket.
     */
    public function upload(Request $request, $ticketId): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:20480', // Max 20MB (laravel validation)
        ]);

        $ticket = Ticket::find($ticketId);

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found',
            ], 404);
        }

        $user = $request->user();

        // Authorization check: only ticket owner or CSKH can upload
        if (!$user->isCsKH() && $ticket->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        try {
            $result = $this->uploadService->upload(
                $request->file('file'),
                $ticketId,
                $user->id
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Delete attachment.
     */
    public function destroy($id): JsonResponse
    {
        $attachment = Attachment::with('ticket')->find($id);

        if (!$attachment) {
            return response()->json([
                'success' => false,
                'message' => 'Attachment not found',
            ], 404);
        }

        $user = request()->user();

        // Authorization: only uploader or admin can delete
        if ($attachment->user_id !== $user->id && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        try {
            $this->uploadService->delete($attachment);

            return response()->json([
                'success' => true,
                'message' => 'Attachment deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete attachment',
            ], 500);
        }
    }

    /**
     * Get attachment info.
     */
    public function show($id): JsonResponse
    {
        $attachment = Attachment::with('user')->find($id);

        if (!$attachment) {
            return response()->json([
                'success' => false,
                'message' => 'Attachment not found',
            ], 404);
        }

        $user = request()->user();

        // Authorization: only ticket participants can view
        if (!$user->isCsKH() && $attachment->ticket->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $attachment->id,
                'filename' => $attachment->filename,
                'url' => $attachment->url,
                'size' => $attachment->getReadableSize(),
                'type' => $attachment->file_type,
                'extension' => $attachment->file_extension,
                'is_image' => $attachment->isImage(),
                'uploaded_by' => $attachment->user->name,
                'created_at' => $attachment->created_at,
            ],
        ]);
    }

    /**
     * Get all attachments for a ticket.
     */
    public function index(Request $request, $ticketId): JsonResponse
    {
        $ticket = Ticket::find($ticketId);

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found',
            ], 404);
        }

        $user = $request->user();

        // Authorization check
        if (!$user->isCsKH() && $ticket->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $attachments = Attachment::with('user')
            ->forTicket($ticketId)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $attachments->map(function ($attachment) {
                return [
                    'id' => $attachment->id,
                    'filename' => $attachment->filename,
                    'url' => $attachment->url,
                    'size' => $attachment->getReadableSize(),
                    'type' => $attachment->file_type,
                    'extension' => $attachment->file_extension,
                    'is_image' => $attachment->isImage(),
                    'uploaded_by' => $attachment->user->name,
                    'created_at' => $attachment->created_at,
                ];
            }),
        ]);
    }
}
