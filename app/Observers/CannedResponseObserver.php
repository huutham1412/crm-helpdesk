<?php

namespace App\Observers;

use App\Models\CannedResponse;
use App\Services\ActivityLogService;

class CannedResponseObserver
{
    public function __construct(protected ActivityLogService $logger)
    {
    }

    public function created(CannedResponse $response): void
    {
        $this->logger->log(
            'canned_response.created',
            $response,
            "Tạo trả lời mẫu: {$response->name}",
            [],
            'info',
            ['canned_response']
        );
    }

    public function updated(CannedResponse $response): void
    {
        $changes = [];
        if ($response->isDirty('name')) {
            $changes['name'] = [
                'old' => $response->getOriginal('name'),
                'new' => $response->name
            ];
        }

        $this->logger->log(
            'canned_response.updated',
            $response,
            "Cập nhật trả lời mẫu: {$response->name}",
            $changes,
            'info',
            ['canned_response']
        );
    }

    public function deleted(CannedResponse $response): void
    {
        $this->logger->log(
            'canned_response.deleted',
            $response,
            "Xóa trả lời mẫu: {$response->name}",
            [],
            'warning',
            ['canned_response']
        );
    }
}
