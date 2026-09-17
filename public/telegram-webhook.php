<?php
/**
 * KouPrey Telegram Webhook Handler
 * Receives incoming customer messages from Telegram Bot and Telegram Business Personal Account
 * Responds automatically with configured greeting, photos, and Mini App / Channel inline buttons.
 */

// Disable error display in production webhook output, log to error_log
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../app/Config/database.php';
require_once __DIR__ . '/../app/Config/settings.php';
require_once __DIR__ . '/../app/Config/telegram_helper.php';

// Quick exit for GET requests (for browser diagnostics)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode([
        'status' => 'online',
        'service' => 'KouPrey Telegram Auto-Responder Webhook',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

$input = file_get_contents('php://input');
if (empty($input)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Empty request body']);
    exit;
}

$update = json_decode($input, true);
if (!$update) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
    exit;
}

// Ensure database log table exists
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS telegram_customer_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            chat_id VARCHAR(100) NOT NULL,
            customer_name VARCHAR(255) NULL,
            customer_username VARCHAR(255) NULL,
            message_type VARCHAR(50) DEFAULT 'direct_message',
            received_text TEXT NULL,
            replied_status VARCHAR(50) DEFAULT 'replied',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_chat (chat_id),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
} catch (Exception $e) {
    error_log("Failed to create telegram_customer_logs table: " . $e->getMessage());
}

// Fetch all telegram settings from DB
function getTelegramSettingMap($pdo) {
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE category = 'telegram' OR setting_key LIKE 'telegram_%'");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    return $rows ?: [];
}

$cfg = getTelegramSettingMap($pdo);
$botToken = trim($cfg['telegram_bot_token'] ?? '');

if (empty($botToken)) {
    // Bot token not configured yet
    echo json_encode(['ok' => true, 'notice' => 'Bot token not configured']);
    exit;
}

// Helper to log customer message
function logCustomerMessage($pdo, $chatId, $name, $username, $type, $text, $status) {
    try {
        $stmt = $pdo->prepare("INSERT INTO telegram_customer_logs (chat_id, customer_name, customer_username, message_type, received_text, replied_status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            (string)$chatId,
            mb_substr((string)$name, 0, 255, 'UTF-8'),
            mb_substr((string)$username, 0, 255, 'UTF-8'),
            (string)$type,
            mb_substr((string)$text, 0, 1000, 'UTF-8'),
            (string)$status
        ]);
    } catch (Exception $e) {
        error_log("Error logging customer telegram message: " . $e->getMessage());
    }
}

// Extract update payload
$businessMessage = $update['business_message'] ?? null;
$directMessage = $update['message'] ?? null;
$businessConnection = $update['business_connection'] ?? null;

// Handle Telegram Business connection status updates
if ($businessConnection) {
    $connId = $businessConnection['id'] ?? '';
    $user = $businessConnection['user'] ?? [];
    $isEnabled = !empty($businessConnection['is_enabled']);
    error_log("Telegram Business Connection updated: ID={$connId}, User=" . ($user['first_name'] ?? '') . ", Enabled=" . ($isEnabled ? 'YES' : 'NO'));
    echo json_encode(['ok' => true, 'business_connection' => $connId]);
    exit;
}

// Check which message arrived
$msg = $businessMessage ?: $directMessage;
if (!$msg) {
    echo json_encode(['ok' => true, 'info' => 'No message to handle']);
    exit;
}

$isBusiness = !empty($businessMessage);
$businessConnectionId = $businessMessage['business_connection_id'] ?? null;
$chat = $msg['chat'] ?? [];
$chatId = $chat['id'] ?? null;
$chatType = $chat['type'] ?? 'private';
$from = $msg['from'] ?? [];
$customerName = trim(($from['first_name'] ?? '') . ' ' . ($from['last_name'] ?? ''));
$customerUsername = $from['username'] ?? '';
$text = trim($msg['text'] ?? ($msg['caption'] ?? ''));

// We only auto-reply in private customer chats
if ($chatType !== 'private') {
    echo json_encode(['ok' => true, 'info' => 'Group message ignored for auto-reply']);
    exit;
}

// If it's a Business Message, check if Business auto-reply is enabled
if ($isBusiness) {
    $businessEnabled = ($cfg['telegram_autoreply_business_enabled'] ?? '1') === '1';
    if (!$businessEnabled) {
        echo json_encode(['ok' => true, 'info' => 'Business auto-reply is disabled']);
        exit;
    }
} else {
    $directEnabled = ($cfg['telegram_autoreply_enabled'] ?? '1') === '1';
    if (!$directEnabled) {
        echo json_encode(['ok' => true, 'info' => 'Direct auto-reply is disabled']);
        exit;
    }
}

// Avoid infinite loop: do not reply if the message was sent by a bot
if (!empty($from['is_bot'])) {
    echo json_encode(['ok' => true, 'info' => 'Ignored message from bot']);
    exit;
}

// Check if this is the customer's FIRST message (within 24 hours) or /start command
// /start always triggers the greeting menu, while normal messages only auto-reply once per 24h
$isStartCommand = (strtolower(trim($text)) === '/start' || strpos(strtolower(trim($text)), '/start ') === 0);

if (!$isStartCommand) {
    try {
        $lastReplyStmt = $pdo->prepare("SELECT created_at FROM telegram_customer_logs WHERE chat_id = ? AND replied_status = 'replied' ORDER BY id DESC LIMIT 1");
        $lastReplyStmt->execute([(string)$chatId]);
        $lastReplyTime = $lastReplyStmt->fetchColumn();

        if ($lastReplyTime) {
            $secondsSince = time() - strtotime($lastReplyTime);
            // If customer received a greeting less than 24 hours ago, do not auto-reply again
            if ($secondsSince < 86400) {
                logCustomerMessage($pdo, $chatId, $customerName, $customerUsername, $isBusiness ? 'business_message' : 'direct_message', $text, 'skipped_already_replied');
                echo json_encode(['ok' => true, 'info' => 'Customer was already greeted on their first message within 24h']);
                exit;
            }
        }
    } catch (Exception $e) {
        error_log("Error checking previous customer replies: " . $e->getMessage());
    }
}

// Build Greeting Message Text
$greetingTemplate = trim($cfg['telegram_autoreply_message'] ?? '');
if (empty($greetingTemplate)) {
    $greetingTemplate = "<b>សួស្តី {name}! សូមស្វាគមន៍មកកាន់ GoGo Brand ✨</b>\n\n"
        . "យើងខ្ញុំមានលក់ផលិតផលគ្រឿងបន្ថែមរស់ជាតិភេសជ្ជៈ, ស៊ីរ៉ូ (Syrup) និងម្សៅ (Powder) គុណភាពខ្ពស់។\n\n"
        . "👉 សូមជ្រើសរើសជម្រើសខាងក្រោមដើម្បីមើលផលិតផល ឬទាក់ទងមកកាន់យើងខ្ញុំ៖";
}

// Personalize template tags
$replacements = [
    '{name}' => !empty($customerName) ? htmlspecialchars($customerName, ENT_QUOTES, 'UTF-8') : 'អតិថិជន',
    '{username}' => !empty($customerUsername) ? '@' . $customerUsername : '',
    '{time}' => date('H:i')
];
$responseText = str_replace(array_keys($replacements), array_values($replacements), $greetingTemplate);

// Build Inline Buttons
$btnMiniappText = trim($cfg['telegram_autoreply_btn_miniapp_text'] ?? '🛍️ បើកមើលទំនិញ (Open Mini App)');
$btnMiniappUrl  = trim($cfg['telegram_autoreply_btn_miniapp_url'] ?? 'https://t.me/gogobrand_bot');

$btnChannelText = trim($cfg['telegram_autoreply_btn_channel_text'] ?? '📢 ចូលរួម Telegram Channel');
$btnChannelUrl  = trim($cfg['telegram_autoreply_btn_channel_url'] ?? 'https://t.me/gogobrand98');

$btnSupportText = trim($cfg['telegram_autoreply_btn_support_text'] ?? '💬 ទាក់ទងផ្ទាល់ / កម្ម៉ង់');
$btnSupportUrl  = trim($cfg['telegram_autoreply_btn_support_url'] ?? 'https://t.me/Bos_Sauveli98');

$buttonRows = [];

// Row 1: Mini App button
if (!empty($btnMiniappUrl)) {
    // For business_message in Personal Chat, Telegram API requires standard URL buttons,
    // whereas for direct bot chat, web_app is fully supported.
    if ($isBusiness) {
        $buttonRows[] = [
            ['text' => $btnMiniappText, 'url' => $btnMiniappUrl]
        ];
    } else {
        $buttonRows[] = [
            ['text' => $btnMiniappText, 'web_app' => $btnMiniappUrl]
        ];
    }
}

// Row 2: Channel & Support
$row2 = [];
if (!empty($btnChannelUrl)) {
    $row2[] = ['text' => $btnChannelText, 'url' => $btnChannelUrl];
}
if (!empty($btnSupportUrl)) {
    $row2[] = ['text' => $btnSupportText, 'url' => $btnSupportUrl];
}
if (!empty($row2)) {
    $buttonRows[] = $row2;
}

$inlineKeyboard = buildTelegramInlineKeyboard($buttonRows);

$options = [
    'reply_markup' => $inlineKeyboard,
    'parse_mode' => 'HTML'
];

if ($isBusiness && !empty($businessConnectionId)) {
    $options['business_connection_id'] = $businessConnectionId;
}

// Optional photo attachment
$photoUrl = trim($cfg['telegram_autoreply_photo'] ?? '');

$sendResult = null;
if (!empty($photoUrl)) {
    // If photo is relative URL on server, convert to absolute or file path
    if (strpos($photoUrl, 'http') !== 0) {
        $localPath = __DIR__ . '/' . ltrim($photoUrl, '/');
        if (file_exists($localPath)) {
            $photoUrl = $localPath;
        } else {
            $photoUrl = 'https://www.kouprey.asia/' . ltrim($photoUrl, '/');
        }
    }
    $sendResult = sendTelegramPhoto($botToken, $chatId, $photoUrl, $responseText, $options);
} else {
    $sendResult = sendTelegramMessage($botToken, $chatId, $responseText, $options);
}

// Log status
$status = !empty($sendResult['success']) ? 'replied' : ('failed: ' . ($sendResult['error'] ?? 'unknown'));
logCustomerMessage($pdo, $chatId, $customerName, $customerUsername, $isBusiness ? 'business_message' : 'direct_message', $text, $status);

echo json_encode([
    'ok' => true,
    'replied' => !empty($sendResult['success']),
    'details' => $sendResult
]);
