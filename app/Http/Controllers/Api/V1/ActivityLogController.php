<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Repositories\ActivityLogRepository;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ActivityLogController extends Controller
{
    public function __construct(
        protected ActivityLogRepository $repository,
        protected ActivityLogService $service
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $filters = [
            'user_id' => $request->user_id,
            'action' => $request->action,
            'actions' => $request->actions ? explode(',', $request->actions) : null,
            'subject_type' => $request->subject_type,
            'subject_id' => $request->subject_id,
            'log_level' => $request->log_level,
            'tags' => $request->tags ? explode(',', $request->tags) : null,
            'search' => $request->search,
            'date_from' => $request->date_from,
            'date_to' => $request->date_to,
        ];

        $perPage = min($request->per_page ?? 50, 100);

        $logs = $this->repository->paginate($perPage, $filters);

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $log = $this->repository->withDetails($id);

        if (!$log) {
            return response()->json([
                'success' => false,
                'message' => 'Activity log not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $log,
        ]);
    }

    public function subjectHistory(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'subject_type' => 'required|string',
            'subject_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $history = $this->repository->getSubjectHistory(
            $request->subject_type,
            $request->subject_id
        );

        return response()->json([
            'success' => true,
            'data' => $history,
        ]);
    }

    public function statistics(Request $request): JsonResponse
    {
        $stats = $this->repository->getStatistics(
            $request->date_from,
            $request->date_to
        );

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    public function export(Request $request): BinaryFileResponse|JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'format' => 'required|in:csv',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $filters = [
            'date_from' => $request->date_from,
            'date_to' => $request->date_to,
            'action' => $request->action,
            'user_id' => $request->user_id,
        ];

        return $this->repository->export($filters);
    }

    public function clean(Request $request): JsonResponse
    {
        $deleted = $this->service->cleanOldLogs();

        return response()->json([
            'success' => true,
            'message' => "Đã xóa {$deleted} bản ghi cũ",
            'data' => [
                'deleted_count' => $deleted,
            ],
        ]);
    }

    public function options(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'actions' => config('activity-log.actions'),
                'log_levels' => [
                    'emergency' => 'Khẩn cấp',
                    'alert' => 'Cảnh báo',
                    'critical' => 'Nghiêm trọng',
                    'error' => 'Lỗi',
                    'warning' => 'Cảnh báo',
                    'notice' => 'Thông báo',
                    'info' => 'Thông tin',
                    'debug' => 'Debug',
                ],
                'tags' => [
                    'authentication' => 'Xác thực',
                    'ticket' => 'Ticket',
                    'user' => 'Người dùng',
                    'message' => 'Tin nhắn',
                    'role' => 'Vai trò',
                    'category' => 'Danh mục',
                    'system' => 'Hệ thống',
                    'security' => 'Bảo mật',
                ],
            ],
        ]);
    }
}
