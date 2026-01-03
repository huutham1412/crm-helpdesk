<?php

/**
 * Script để lấy Chat ID từ Telegram Bot
 *
 * Cách dùng:
 * 1. Add bot vào channel
 * 2. Gửi một message vào channel (ví dụ: "test")
 * 3. Chạy script này: php get_chat_id.php
 */

$botToken = '7561250649:AAHrhEpzej8ddmxwnOnRK9kXWJglSa-LvhQ';

echo "=== Lấy Chat ID từ Telegram ===\n\n";
echo "1. Add bot vào channel\n";
echo "2. Gửi một message vào channel (bất kỳ内容 gì)\n";
echo "3. Chạy script này để lấy Chat ID\n\n";

echo "Đang lấy updates từ Telegram...\n";

$url = "https://api.telegram.org/bot{$botToken}/getUpdates";

$response = file_get_contents($url);
$data = json_decode($response, true);

if (!$data['ok']) {
    echo "Lỗi: " . ($data['description'] ?? 'Unknown error') . "\n";
    echo "\nNếu chưa có updates, hãy:\n";
    echo "- Gửi message vào channel trước\n";
    echo "- Kiểm tra bot token đúng chưa\n";
    exit(1);
}

if (empty($data['result'])) {
    echo "Chưa có updates nào.\n";
    echo "Hãy gửi một message vào channel và chạy lại script.\n";
    exit(1);
}

echo "Các updates gần đây:\n";
echo str_repeat('-', 80) . "\n";

$foundChatId = null;

foreach ($data['result'] as $update) {
    $timestamp = $update['message']['date'] ?? 0;
    $date = date('Y-m-d H:i:s', $timestamp);

    if (isset($update['message']['chat'])) {
        $chat = $update['message']['chat'];
        $chatId = $chat['id'];
        $chatType = $chat['type'];
        $chatTitle = $chat['title'] ?? $chat['first_name'] ?? $chat['username'] ?? 'Unknown';

        echo "[{$date}] ";
        echo "Chat ID: {$chatId} | ";
        echo "Type: {$chatType} | ";
        echo "Title: {$chatTitle}\n";

        if ($chatType === 'supergroup' || $chatType === 'channel') {
            $foundChatId = $chatId;
        }
    }
}

echo str_repeat('-', 80) . "\n";

if ($foundChatId) {
    echo "\n=== CHAT ID CỦA CHANNEL ===\n";
    echo "Chat ID: {$foundChatId}\n\n";
    echo "Add vào .env:\n";
    echo "TELEGRAM_CHAT_ID={$foundChatId}\n";
} else {
    echo "\nKhông tìm thấy channel/supergroup chat.\n";
    echo "Hãy chắc chắn bot đã được add vào channel.\n";
}
