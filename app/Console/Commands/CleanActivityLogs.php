<?php

namespace App\Console\Commands;

use App\Services\ActivityLogService;
use Illuminate\Console\Command;

class CleanActivityLogs extends Command
{
    protected $signature = 'activity-logs:clean {--days= : Retention days override}';
    protected $description = 'Xóa nhật ký hoạt động cũ dựa trên chính sách lưu trữ';

    public function handle(ActivityLogService $service): int
    {
        $days = $this->option('days') ?? config('activity-log.retention_days', 90);

        $this->info("Đang xóa nhật ký cũ hơn {$days} ngày...");

        $deleted = $service->cleanOldLogs();

        $this->info("Đã xóa {$deleted} bản ghi.");

        return self::SUCCESS;
    }
}
