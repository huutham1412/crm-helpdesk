<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SLA Response Time Configuration
    |--------------------------------------------------------------------------
    |
    | Thời gian phản hồi tối đa theo từng mức độ ưu tiên (tính bằng phút).
    | Sau khi quá thời gian này, ticket sẽ được cảnh báo.
    |
    */

    'response_time' => [
        'urgent' => 5,   // 5 phút
        'high' => 15,    // 15 phút
        'medium' => 30,  // 30 phút
        'low' => 60,     // 60 phút
    ],

    /*
    |--------------------------------------------------------------------------
    | Escalation Multiplier
    |--------------------------------------------------------------------------
    |
    | Hệ số nhân để tính thời gian escalate lên Admin.
    | Ví dụ: 1.5 = 150% thời gian ban đầu (tăng thêm 50%)
    |
    | Medium (30 phút):
    |   - Warning sau 30 phút
    |   - Escalate Admin sau 30 x 1.5 = 45 phút
    |
    */
    'escalation_multiplier' => 1.5,

    /*
    |--------------------------------------------------------------------------
    | Check Interval
    |--------------------------------------------------------------------------
    |
    | Khoảng thời gian giữa các lần check (tính bằng phút).
    | Mặc định: 1 phút
    |
    */
    'check_interval' => 1,

    /*
    |--------------------------------------------------------------------------
    | Escalation Levels
    |--------------------------------------------------------------------------
    |
    | Các mức độ cảnh báo trong hệ thống escalation
    |
    */
    'levels' => [
        'warning' => 'Cảnh báo đầu tiên - Gửi Telegram',
        'escalated' => 'Escalate lên Admin - Gửi notification cho Admin',
    ],
];
