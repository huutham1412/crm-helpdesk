<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Activity Log Configuration
    |--------------------------------------------------------------------------
    */

    'enabled' => env('ACTIVITY_LOG_ENABLED', true),

    'retention_days' => env('ACTIVITY_LOG_RETENTION_DAYS', 90),

    'ignored_actions' => [],

    'only_actions' => [],

    'log_levels' => [
        'login_failed' => 'warning',
        'user.deleted' => 'warning',
        'ticket.deleted' => 'warning',
        'escalation.created' => 'warning',
    ],

    'actions' => [
        'login' => 'Đăng nhập',
        'logout' => 'Đăng xuất',
        'login_failed' => 'Đăng nhập thất bại',
        'password_changed' => 'Đổi mật khẩu',
        'user.created' => 'Tạo người dùng',
        'user.updated' => 'Cập nhật người dùng',
        'user.deleted' => 'Xóa người dùng',
        'user.roles_changed' => 'Thay đổi vai trò',
        'ticket.created' => 'Tạo ticket',
        'ticket.updated' => 'Cập nhật ticket',
        'ticket.deleted' => 'Xóa ticket',
        'ticket.status_changed' => 'Thay đổi trạng thái',
        'ticket.assigned' => 'Gán ticket',
        'ticket.unassigned' => 'Bỏ gán ticket',
        'message.created' => 'Gửi tin nhắn',
        'message.deleted' => 'Xóa tin nhắn',
        'role.created' => 'Tạo vai trò',
        'role.updated' => 'Cập nhật vai trò',
        'role.deleted' => 'Xóa vai trò',
        'category.created' => 'Tạo danh mục',
        'category.updated' => 'Cập nhật danh mục',
        'category.deleted' => 'Xóa danh mục',
        'canned_response.created' => 'Tạo trả lời mẫu',
        'canned_response.updated' => 'Cập nhật trả lời mẫu',
        'canned_response.deleted' => 'Xóa trả lời mẫu',
        'escalation.created' => 'Tạo cảnh báo',
        'escalation.resolved' => 'Giải quyết cảnh báo',
        'settings.changed' => 'Thay đổi cài đặt',
        'export.performed' => 'Xuất dữ liệu',
    ],

    'field_labels' => [
        'status' => 'Trạng thái',
        'priority' => 'Độ ưu tiên',
        'assigned_to' => 'Người xử lý',
        'category_id' => 'Danh mục',
        'name' => 'Tên',
        'email' => 'Email',
        'phone' => 'SĐT',
        'is_active' => 'Trạng thái hoạt động',
        'subject' => 'Tiêu đề',
        'description' => 'Mô tả',
    ],

    'export' => [
        'csv_enabled' => true,
        'pdf_enabled' => true,
        'max_export_rows' => 10000,
    ],
];
