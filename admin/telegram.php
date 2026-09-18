<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../app/Config/database.php';
require_once '../app/Config/settings.php';
require_once '../app/Config/telegram_helper.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$successMsg = '';
$errorMsg = '';

// Fetch current telegram settings
$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE category = 'telegram' OR setting_key LIKE 'telegram_%'");
$cfg = $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

$scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'www.kouprey.asia';
$defaultWebhook = "$scheme://$host/telegram-webhook.php";

$botToken = $cfg['telegram_bot_token'] ?? '';
$groupId = $cfg['telegram_group_id'] ?? '';
$channelUrl = $cfg['telegram_channel_url'] ?? 'https://t.me/gogobrand98';
$miniappUrl = $cfg['telegram_miniapp_url'] ?? 'https://www.kouprey.asia/telegram.php';
$supportUrl = $cfg['telegram_support_url'] ?? 'https://t.me/Bos_Sauveli98';
$webhookUrl = $cfg['telegram_webhook_url'] ?? $defaultWebhook;
$autoreplyEnabled = $cfg['telegram_autoreply_enabled'] ?? '1';
$autoreplyBusinessEnabled = $cfg['telegram_autoreply_business_enabled'] ?? '1';
$autoreplyMsg = $cfg['telegram_autoreply_message'] ?? "<b>សួស្តី {name}! សូមស្វាគមន៍មកកាន់ GoGo Brand ✨</b>\n\nយើងខ្ញុំមានលក់ផលិតផលគ្រឿងបន្ថែមរស់ជាតិភេសជ្ជៈ, ស៊ីរ៉ូ (Syrup) និងម្សៅ (Powder) គុណភាពខ្ពស់។\n\n👉 សូមជ្រើសរើសជម្រើសខាងក្រោមដើម្បីមើលផលិតផល ឬទាក់ទងមកកាន់យើងខ្ញុំ៖";
$autoreplyPhoto = $cfg['telegram_autoreply_photo'] ?? 'https://i.ibb.co/WW1FQSG2/Gemini-Generated-Image-l5ljj5l5ljj5l5lj.jpg';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formAction = $_POST['form_action'] ?? '';

    if ($formAction === 'send_broadcast') {
        $targetChat = trim($_POST['chat_id'] ?? $groupId);
        $caption = trim($_POST['caption'] ?? '');
        $photoUrl = trim($_POST['photo_url'] ?? '');
        
        // Handle uploaded photo
        if (isset($_FILES['photo_file']) && $_FILES['photo_file']['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES['photo_file']['tmp_name'];
            $fn = 'broadcast_' . time() . '_' . uniqid() . '.' . pathinfo($_FILES['photo_file']['name'], PATHINFO_EXTENSION);
            $dest = __DIR__ . '/../public/uploads/banners/' . $fn;
            if (!is_dir(dirname($dest))) mkdir(dirname($dest), 0755, true);
            if (move_uploaded_file($tmp, $dest)) {
                $photoUrl = $dest;
            }
        }

        $includeMiniapp = !empty($_POST['include_miniapp']);
        $includeChannel = !empty($_POST['include_channel']);
        $includeSupport = !empty($_POST['include_support']);

        $buttonRows = [];
        if ($includeMiniapp && !empty($_POST['miniapp_url'])) {
            $mUrl = trim($_POST['miniapp_url']);
            $isTgLink = (strpos($mUrl, '@') === 0 || stripos($mUrl, 't.me/') !== false);
            if ($isTgLink) {
                $cleanUrl = (strpos($mUrl, '@') === 0) ? ('https://t.me/' . ltrim($mUrl, '@')) : $mUrl;
                $buttonRows[] = [
                    ['text' => $_POST['miniapp_text'] ?: '🛍️ បើកមើលទំនិញ (Open Mini App)', 'url' => $cleanUrl]
                ];
            } else {
                $buttonRows[] = [
                    ['text' => $_POST['miniapp_text'] ?: '🛍️ បើកមើលទំនិញ (Open Mini App)', 'web_app' => $mUrl]
                ];
            }
        }

        $row2 = [];
        if ($includeChannel && !empty($_POST['channel_url'])) {
            $row2[] = ['text' => $_POST['channel_text'] ?: '📢 ចូលរួមឆានែល', 'url' => $_POST['channel_url']];
        }
        if ($includeSupport && !empty($_POST['support_url'])) {
            $row2[] = ['text' => $_POST['support_text'] ?: '💬 ទាក់ទងផ្ទាល់ / កម្ម៉ង់', 'url' => $_POST['support_url']];
        }
        if (!empty($row2)) $buttonRows[] = $row2;

        $inlineKeyboard = buildTelegramInlineKeyboard($buttonRows);

        if (empty($targetChat)) {
            $errorMsg = 'សូមបញ្ចូល Group ឬ Channel ID!';
        } elseif (empty($caption) && empty($photoUrl)) {
            $errorMsg = 'សូមបញ្ចូលអត្ថបទ ឬរូបភាពដែលត្រូវផ្ញើ!';
        } else {
            if (!empty($photoUrl)) {
                $res = sendTelegramPhoto($botToken, $targetChat, $photoUrl, $caption, [
                    'reply_markup' => $inlineKeyboard,
                    'parse_mode' => 'HTML'
                ]);
            } else {
                $res = sendTelegramMessage($botToken, $targetChat, $caption, [
                    'reply_markup' => $inlineKeyboard,
                    'parse_mode' => 'HTML'
                ]);
            }

            if (!empty($res['success'])) {
                $successMsg = '🎉 បានផ្ញើសារទៅកាន់ Telegram Group រួចរាល់ដោយជោគជ័យ!';
                // Insert log
                try {
                    $pdo->exec("CREATE TABLE IF NOT EXISTS telegram_broadcast_logs (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        chat_id VARCHAR(100) NOT NULL,
                        chat_title VARCHAR(255) NULL,
                        message_text TEXT NULL,
                        photo_url VARCHAR(500) NULL,
                        buttons_json TEXT NULL,
                        status VARCHAR(50) DEFAULT 'success',
                        error_message TEXT NULL,
                        sent_by VARCHAR(100) NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_created (created_at)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

                    $st = $pdo->prepare("INSERT INTO telegram_broadcast_logs (chat_id, message_text, photo_url, buttons_json, status, sent_by) VALUES (?, ?, ?, ?, 'success', ?)");
                    $st->execute([$targetChat, $caption, is_string($photoUrl) ? $photoUrl : '', json_encode($buttonRows, JSON_UNESCAPED_UNICODE), $_SESSION['admin_username'] ?? 'admin']);
                } catch (Exception $e) {}
            } else {
                $errorMsg = 'ការផ្ញើសារបានបរាជ័យ៖ ' . ($res['error'] ?? 'Unknown error');
            }
        }
    } elseif ($formAction === 'save_settings') {
        $updates = [
            'telegram_bot_token' => trim($_POST['telegram_bot_token'] ?? ''),
            'telegram_group_id' => trim($_POST['telegram_group_id'] ?? ''),
            'telegram_channel_url' => trim($_POST['telegram_channel_url'] ?? ''),
            'telegram_miniapp_url' => trim($_POST['telegram_miniapp_url'] ?? ''),
            'telegram_support_url' => trim($_POST['telegram_support_url'] ?? ''),
            'telegram_webhook_url' => trim($_POST['telegram_webhook_url'] ?? ''),
            'telegram_autoreply_enabled' => !empty($_POST['telegram_autoreply_enabled']) ? '1' : '0',
            'telegram_autoreply_business_enabled' => !empty($_POST['telegram_autoreply_business_enabled']) ? '1' : '0',
            'telegram_autoreply_message' => trim($_POST['telegram_autoreply_message'] ?? '')
        ];

        $st = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, category, language) VALUES (?, ?, 'telegram', 'km') ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        foreach ($updates as $k => $v) {
            $st->execute([$k, $v]);
        }
        $successMsg = 'បានរក្សាទុកការកំណត់ Telegram ដោយជោគជ័យ!';
        // Refresh local variables
        $botToken = $updates['telegram_bot_token'];
        $groupId = $updates['telegram_group_id'];
        $channelUrl = $updates['telegram_channel_url'];
        $miniappUrl = $updates['telegram_miniapp_url'];
        $supportUrl = $updates['telegram_support_url'];
        $autoreplyEnabled = $updates['telegram_autoreply_enabled'];
        $autoreplyBusinessEnabled = $updates['telegram_autoreply_business_enabled'];
        $autoreplyMsg = $updates['telegram_autoreply_message'];
    } elseif ($formAction === 'set_webhook') {
        $res = setTelegramWebhook($botToken, $webhookUrl);
        if ($res['success']) {
            $successMsg = '🎉 បានដំឡើង Webhook ទៅកាន់ Telegram រួចរាល់ដោយជោគជ័យ!';
        } else {
            $errorMsg = 'ដំឡើង Webhook បរាជ័យ៖ ' . ($res['error'] ?? 'Error');
        }
    }
}

// Bot Test Info
$botInfo = null;
if (!empty($botToken)) {
    $bRes = getTelegramBotInfo($botToken);
    if (!empty($bRes['success'])) {
        $botInfo = $bRes['data'];
    }
}

// Fetch history
$history = [];
try {
    $histStmt = $pdo->query("SELECT * FROM telegram_broadcast_logs ORDER BY id DESC LIMIT 10");
    if ($histStmt) $history = $histStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

ob_start();
?>

<div class="container-fluid py-4">
    <!-- Header Banner -->
    <div class="card border-0 rounded-4 shadow-sm mb-4" style="background: linear-gradient(135deg, #064e3b 0%, #065f46 50%, #134e4a 100%); color: white;">
        <div class="card-body p-4 p-lg-5">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div>
                    <span class="badge bg-emerald-700 text-white rounded-pill px-3 py-2 mb-2" style="background: rgba(16, 185, 129, 0.3);">
                        <i class="bi bi-robot me-1"></i> Telegram Automation Hub
                    </span>
                    <h2 class="fw-bold mb-2">គ្រប់គ្រង Telegram Bot & Auto-Reply</h2>
                    <p class="text-white-50 mb-0">ផ្ញើសារប្រូម៉ូសិនទៅ Telegram Group ជាមួយរូបភាព និង Inline Button បើក Mini App ព្រមទាំងកំណត់ Auto-Reply</p>
                </div>
                <div>
                    <?php if ($botInfo): ?>
                        <div class="badge bg-success rounded-pill px-3 py-2 fs-6">
                            <i class="bi bi-check-circle-fill me-1"></i> Bot: @<?php echo htmlspecialchars($botInfo['username'] ?? ''); ?>
                        </div>
                    <?php else: ?>
                        <div class="badge bg-warning text-dark rounded-pill px-3 py-2 fs-6">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i> Token មិនទាន់តេស្ត
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if ($successMsg): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4 shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> <?php echo htmlspecialchars($successMsg); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($errorMsg): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($errorMsg); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Navigation Tabs -->
    <ul class="nav nav-pills mb-4 gap-2" id="telegramTabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link active rounded-pill px-4 py-2 fw-bold" data-bs-toggle="pill" data-bs-target="#tab-broadcast">
                <i class="bi bi-send-fill me-1"></i> ផ្ញើសារទៅ Group (Broadcast)
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link rounded-pill px-4 py-2 fw-bold" data-bs-toggle="pill" data-bs-target="#tab-autoreply">
                <i class="bi bi-chat-dots-fill me-1"></i> ឆ្លើយតបស្វ័យប្រវត្តិ (Auto-Reply)
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link rounded-pill px-4 py-2 fw-bold" data-bs-toggle="pill" data-bs-target="#tab-settings">
                <i class="bi bi-gear-wide-connected me-1"></i> ការកំណត់ Bot & Webhook
            </button>
        </li>
    </ul>

    <div class="tab-content" id="telegramTabsContent">
        <!-- TAB 1: BROADCAST -->
        <div class="tab-pane fade show active" id="tab-broadcast">
            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="card border-0 rounded-4 shadow-sm p-4">
                        <h4 class="fw-bold mb-3 text-dark"><i class="bi bi-megaphone-fill text-success me-2"></i>បង្កើតសារផ្សព្វផ្សាយ</h4>
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="form_action" value="send_broadcast">
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Telegram Group / Channel ID <span class="text-danger">*</span></label>
                                <input type="text" name="chat_id" class="form-control rounded-3" value="<?php echo htmlspecialchars($groupId); ?>" placeholder="-100xxxxxxxxxx" required>
                                <small class="text-muted">Group ត្រូវមានសញ្ញាដកខាងមុខ (-100...) ហើយ Bot ត្រូវតែជាសមាជិក/Admin ក្នុង Group នោះ។</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">អត្ថបទ / ការពិពណ៌នា (Caption)</label>
                                <textarea name="caption" rows="5" class="form-control rounded-3" placeholder="សរសេរសារផ្សព្វផ្សាយ ឬការបញ្ចុះតម្លៃ...">🎉 ការបញ្ចុះតម្លៃពិសេសពី GoGo Brand! 
ទទួលបានការបញ្ចុះតម្លៃរហូតដល់ 20% លើគ្រប់មុខផលិតផលស៊ីរ៉ូ និងម្សៅលំដាប់ពិសេស!

👉 ចុចប៊ូតុងខាងក្រោមដើម្បីបើកមើលទំនិញក្នុង Bot Mini App ឥឡូវនេះ!</textarea>
                                <small class="text-muted">គាំទ្រ HTML tags: &lt;b&gt;, &lt;i&gt;, &lt;a href="..."&gt;</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">រូបភាពភ្ជាប់ជាមួយសារ (Photo)</label>
                                <input type="file" name="photo_file" class="form-control rounded-3 mb-2" accept="image/*">
                                <input type="text" name="photo_url" class="form-control rounded-3" placeholder="ឬបញ្ចូល URL រូបភាព: https://...">
                            </div>

                            <div class="card bg-light border-0 rounded-3 p-3 mb-4">
                                <h6 class="fw-bold mb-3"><i class="bi bi-menu-button-wide text-primary me-2"></i>ប៊ូតុង Inline (Inline Buttons)</h6>
                                
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" name="include_miniapp" id="chkMiniapp" value="1" checked>
                                    <label class="form-check-label fw-bold" for ChkMiniapp>ប៊ូតុងបើក Bot Mini App (Web App)</label>
                                </div>
                                <div class="row g-2 mb-3">
                                    <div class="col-md-5">
                                        <input type="text" name="miniapp_text" class="form-control form-control-sm rounded-2" value="🛍️ បើកមើលទំនិញ (Open Mini App)">
                                    </div>
                                    <div class="col-md-7">
                                        <input type="text" name="miniapp_url" class="form-control form-control-sm rounded-2" value="<?php echo htmlspecialchars($miniappUrl); ?>">
                                    </div>
                                </div>

                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" name="include_channel" id="chkChannel" value="1" checked>
                                    <label class="form-check-label fw-bold" for="chkChannel">ប៊ូតុងចូលរួម Telegram Channel</label>
                                </div>
                                <div class="row g-2 mb-3">
                                    <div class="col-md-5">
                                        <input type="text" name="channel_text" class="form-control form-control-sm rounded-2" value="📢 ចូលរួមឆានែល">
                                    </div>
                                    <div class="col-md-7">
                                        <input type="text" name="channel_url" class="form-control form-control-sm rounded-2" value="<?php echo htmlspecialchars($channelUrl); ?>">
                                    </div>
                                </div>

                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" name="include_support" id="chkSupport" value="1" checked>
                                    <label class="form-check-label fw-bold" for="chkSupport">ប៊ូតុងទាក់ទងផ្ទាល់ (Personal Account)</label>
                                </div>
                                <div class="row g-2">
                                    <div class="col-md-5">
                                        <input type="text" name="support_text" class="form-control form-control-sm rounded-2" value="💬 ទាក់ទងផ្ទាល់ / កម្ម៉ង់">
                                    </div>
                                    <div class="col-md-7">
                                        <input type="text" name="support_url" class="form-control form-control-sm rounded-2" value="<?php echo htmlspecialchars($supportUrl); ?>">
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-success rounded-pill px-4 py-2.5 fw-bold shadow-sm">
                                <i class="bi bi-send-fill me-2"></i> ផ្ញើសារទៅ Group ឥឡូវនេះ
                            </button>
                        </form>
                    </div>
                </div>

                <div class="col-lg-5">
                    <!-- History -->
                    <div class="card border-0 rounded-4 shadow-sm p-4">
                        <h5 class="fw-bold mb-3"><i class="bi bi-clock-history me-2 text-primary"></i>ប្រវត្តិសារដែលបានផ្ញើ</h5>
                        <?php if (empty($history)): ?>
                            <p class="text-muted small text-center py-4">មិនទាន់មានប្រវត្តិសារនៅឡើយទេ</p>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($history as $h): ?>
                                    <div class="list-group-item px-0 py-3">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="badge bg-success-subtle text-success rounded-pill"><?php echo htmlspecialchars($h['status'] ?? 'sent'); ?></span>
                                            <small class="text-muted"><?php echo htmlspecialchars($h['created_at'] ?? ''); ?></small>
                                        </div>
                                        <p class="mb-1 small text-dark fw-medium text-truncate"><?php echo htmlspecialchars($h['message_text'] ?: '(Photo)'); ?></p>
                                        <small class="text-muted font-monospace">Chat: <?php echo htmlspecialchars($h['chat_id'] ?? ''); ?></small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 2: AUTO-REPLY -->
        <div class="tab-pane fade" id="tab-autoreply">
            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="card border-0 rounded-4 shadow-sm p-4">
                        <h4 class="fw-bold mb-3 text-dark"><i class="bi bi-robot text-teal me-2"></i>កំណត់សារឆ្លើយតបស្វ័យប្រវត្តិ</h4>
                        <form method="POST">
                            <input type="hidden" name="form_action" value="save_settings">
                            
                            <div class="card bg-light border-0 rounded-3 p-3 mb-3">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" name="telegram_autoreply_enabled" id="arDirect" value="1" <?php echo ($autoreplyEnabled === '1') ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-bold" for="arDirect">បើកឆ្លើយតបស្វ័យប្រវត្តិក្នុ​ង Telegram Bot (/start)</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="telegram_autoreply_business_enabled" id="arBusiness" value="1" <?php echo ($autoreplyBusinessEnabled === '1') ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-bold" for="arBusiness">បើកឆ្លើយតបលើ Personal Account (Telegram Business)</label>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">សារស្វាគមន៍ (Greeting Message)</label>
                                <textarea name="telegram_autoreply_message" rows="6" class="form-control rounded-3"><?php echo htmlspecialchars($autoreplyMsg); ?></textarea>
                                <small class="text-muted">ប្រើ {name} សម្រាប់ឈ្មោះអតិថិជន និង {username} សម្រាប់ Telegram username។</small>
                            </div>

                            <button type="submit" class="btn btn-primary rounded-pill px-4 py-2.5 fw-bold shadow-sm">
                                <i class="bi bi-save me-2"></i> រក្សាទុកការកំណត់ Auto-Reply
                            </button>
                        </form>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="card border-0 rounded-4 shadow-sm p-4 text-white" style="background: linear-gradient(135deg, #042f2e 0%, #0f766e 100%);">
                        <h5 class="fw-bold mb-3"><i class="bi bi-phone me-2"></i>របៀបភ្ជាប់ Bot ជាមួយ Personal Account</h5>
                        <p class="small text-white-50">អនុញ្ញាតឱ្យ Bot ឆ្លើយតបជំនួសលោកអ្នកក្នុង Chat ផ្ទាល់ខ្លួនតាម Telegram Business៖</p>
                        <ol class="small ps-3 mb-0 space-y-2">
                            <li class="mb-2">បើក Telegram លើទូរស័ព្ទដៃ &rarr; <b>Settings</b> &rarr; <b>Telegram Business</b> &rarr; <b>Chatbots</b></li>
                            <li class="mb-2">បញ្ចូល Username Bot របស់អ្នក (<b>@<?php echo htmlspecialchars($botInfo['username'] ?? 'your_bot'); ?></b>)</li>
                            <li class="mb-2">ចុច Connect រួចកំណត់ឱ្យ Bot ឆ្លើយតបពេលអតិថិជនឆាតមក</li>
                            <li>ដំឡើង Webhook ក្នុង Tab "ការកំណត់ Bot & Webhook" ដើម្បីឱ្យប្រព័ន្ធដំណើរការ ២៤/៧!</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 3: SETTINGS & WEBHOOK -->
        <div class="tab-pane fade" id="tab-settings">
            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="card border-0 rounded-4 shadow-sm p-4">
                        <h4 class="fw-bold mb-3"><i class="bi bi-key-fill text-warning me-2"></i>ការកំណត់ Telegram Bot</h4>
                        <form method="POST">
                            <input type="hidden" name="form_action" value="save_settings">
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Telegram Bot Token <span class="text-danger">*</span></label>
                                <input type="password" name="telegram_bot_token" class="form-control rounded-3 font-monospace" value="<?php echo htmlspecialchars($botToken); ?>" placeholder="1234567890:ABC..." required>
                                <small class="text-muted">ទទួលបានពី @BotFather ក្នុង Telegram</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Default Group ID</label>
                                <input type="text" name="telegram_group_id" class="form-control rounded-3 font-monospace" value="<?php echo htmlspecialchars($groupId); ?>" placeholder="-100xxxxxxxxxx">
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Mini App URL</label>
                                <input type="text" name="telegram_miniapp_url" class="form-control rounded-3 font-monospace" value="<?php echo htmlspecialchars($miniappUrl); ?>">
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Telegram Channel URL</label>
                                <input type="text" name="telegram_channel_url" class="form-control rounded-3 font-monospace" value="<?php echo htmlspecialchars($channelUrl); ?>">
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Personal Support Link</label>
                                <input type="text" name="telegram_support_url" class="form-control rounded-3 font-monospace" value="<?php echo htmlspecialchars($supportUrl); ?>">
                            </div>

                            <button type="submit" class="btn btn-success rounded-pill px-4 py-2.5 fw-bold shadow-sm">
                                <i class="bi bi-check-circle me-2"></i> រក្សាទុកការកំណត់
                            </button>
                        </form>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="card border-0 rounded-4 shadow-sm p-4">
                        <h5 class="fw-bold mb-3"><i class="bi bi-broadcast text-danger me-2"></i>ដំឡើង Webhook</h5>
                        <p class="small text-muted">ដំឡើង Webhook ទៅកាន់ Telegram ដើម្បីឱ្យ Bot អាចទទួលសារអតិថិជនបានដោយស្វ័យប្រវត្តិ៖</p>
                        <form method="POST">
                            <input type="hidden" name="form_action" value="set_webhook">
                            <div class="mb-3">
                                <label class="form-label fw-bold small">Webhook Endpoint URL</label>
                                <input type="text" name="webhook_url" class="form-control form-control-sm rounded-2 font-monospace" value="<?php echo htmlspecialchars($webhookUrl); ?>">
                            </div>
                            <button type="submit" class="btn btn-dark w-100 rounded-pill py-2 fw-bold shadow-sm">
                                <i class="bi bi-link-45deg me-1"></i> ដំឡើង Webhook ទៅ Telegram
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$pageContent = ob_get_clean();
$pageTitle = 'Telegram Bot & Auto-Reply';
$activeNav = 'telegram';
include 'layout.php';
?>
