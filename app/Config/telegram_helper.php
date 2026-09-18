<?php
/**
 * KouPrey Telegram Helper
 * Provides utilities for Telegram Bot API communication:
 * - Group/Channel broadcast (text & photo with inline buttons)
 * - Mini App inline button integration (web_app)
 * - Personal Account / Telegram Business connected auto-response (business_connection_id)
 * - Webhook management & diagnostic tools
 */

if (!defined('TELEGRAM_API_BASE')) {
    define('TELEGRAM_API_BASE', 'https://api.telegram.org/bot');
}

/**
 * Execute a cURL request to the Telegram Bot API
 *
 * @param string $botToken
 * @param string $method
 * @param array $params
 * @param bool $isMultipart
 * @return array ['success' => bool, 'data' => mixed, 'error' => string|null]
 */
function telegramApiRequest($botToken, $method, $params = [], $isMultipart = false) {
    $botToken = trim((string)$botToken);
    if (empty($botToken)) {
        return ['success' => false, 'error' => 'Bot Token មិនអាចទទេបានទេ (Bot token is missing)'];
    }

    $url = TELEGRAM_API_BASE . $botToken . '/' . $method;
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 25);
    curl_setopt($ch, CURLOPT_USERAGENT, 'KouPrey-Telegram-Client/2.0');

    if ($isMultipart) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    } elseif (!empty($params)) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }

    $raw = curl_exec($ch);
    $curlErr = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curlErr) {
        return ['success' => false, 'error' => 'Network error: ' . $curlErr];
    }

    $json = json_decode($raw, true);
    if (!$json) {
        return ['success' => false, 'error' => 'Invalid response from Telegram (HTTP ' . $httpCode . '): ' . substr($raw, 0, 150)];
    }

    if (empty($json['ok'])) {
        $desc = $json['description'] ?? 'Telegram API error';
        return ['success' => false, 'error' => $desc, 'raw' => $json];
    }

    return ['success' => true, 'data' => $json['result'] ?? []];
}

/**
 * Get bot details via getMe
 */
function getTelegramBotInfo($botToken) {
    return telegramApiRequest($botToken, 'getMe');
}

/**
 * Build standard Inline Keyboard Markup from simplified array
 *
 * @param array $buttons Array of button rows: [ [ ['text' => '...', 'url' => '...', 'web_app' => '...'], ... ], ... ]
 * @return array
 */
function buildTelegramInlineKeyboard($buttons) {
    if (empty($buttons) || !is_array($buttons)) {
        return null;
    }

    $inlineKeyboard = [];
    foreach ($buttons as $row) {
        if (!is_array($row)) continue;
        $rowButtons = [];
        foreach ($row as $btn) {
            if (!is_array($btn) || empty($btn['text'])) continue;
            
            $b = ['text' => (string)$btn['text']];
            if (!empty($btn['web_app'])) {
                $rawUrl = trim((string)$btn['web_app']);
                // Telegram Bot API requires web_app to have a valid HTTPS URL pointing to a web application.
                // If a user provides a bot handle (@username) or t.me link, it CANNOT be opened as web_app.
                // Safely convert it to a standard Telegram URL button instead of failing with 400 Bad Request.
                if (strpos($rawUrl, '@') === 0) {
                    $b['url'] = 'https://t.me/' . ltrim($rawUrl, '@');
                } elseif (preg_match('#^(https?:\/\/)?(t\.me|telegram\.me)\/#i', $rawUrl)) {
                    $b['url'] = preg_match('#^https?:\/\/#i', $rawUrl) ? $rawUrl : 'https://' . $rawUrl;
                } elseif (strpos($rawUrl, 'https://') === 0) {
                    $b['web_app'] = ['url' => $rawUrl];
                } elseif (strpos($rawUrl, 'http://') === 0) {
                    $b['web_app'] = ['url' => 'https://' . substr($rawUrl, 7)];
                } elseif (strpos($rawUrl, '/') === 0) {
                    $b['web_app'] = ['url' => 'https://www.kouprey.asia' . $rawUrl];
                } else {
                    $b['url'] = 'https://t.me/' . ltrim($rawUrl, '@');
                }
            } elseif (!empty($btn['url'])) {
                $rawUrl = trim((string)$btn['url']);
                if (strpos($rawUrl, '@') === 0) {
                    $b['url'] = 'https://t.me/' . ltrim($rawUrl, '@');
                } elseif (strpos($rawUrl, 't.me/') === 0) {
                    $b['url'] = 'https://' . $rawUrl;
                } else {
                    $b['url'] = $rawUrl;
                }
            } elseif (!empty($btn['callback_data'])) {
                $b['callback_data'] = (string)$btn['callback_data'];
            } else {
                continue;
            }
            $rowButtons[] = $b;
        }
        if (!empty($rowButtons)) {
            $inlineKeyboard[] = $rowButtons;
        }
    }

    return !empty($inlineKeyboard) ? ['inline_keyboard' => $inlineKeyboard] : null;
}

/**
 * Send text message to a chat, group, channel, or business connection
 *
 * @param string $botToken
 * @param string|int $chatId
 * @param string $text
 * @param array $options ['reply_markup' => ..., 'business_connection_id' => ..., 'parse_mode' => 'HTML']
 * @return array
 */
function sendTelegramMessage($botToken, $chatId, $text, $options = []) {
    $params = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => $options['parse_mode'] ?? 'HTML',
        'disable_web_page_preview' => !empty($options['disable_web_page_preview'])
    ];

    if (!empty($options['reply_markup'])) {
        $params['reply_markup'] = is_array($options['reply_markup'])
            ? $options['reply_markup']
            : json_decode($options['reply_markup'], true);
    }

    if (!empty($options['business_connection_id'])) {
        $params['business_connection_id'] = $options['business_connection_id'];
    }

    return telegramApiRequest($botToken, 'sendMessage', $params);
}

/**
 * Send photo to a chat, group, channel, or business connection
 * Supports remote URL, local path, or CURLFile upload
 *
 * @param string $botToken
 * @param string|int $chatId
 * @param string|CURLFile $photo URL or path
 * @param string $caption
 * @param array $options ['reply_markup' => ..., 'business_connection_id' => ..., 'parse_mode' => 'HTML']
 * @return array
 */
function sendTelegramPhoto($botToken, $chatId, $photo, $caption = '', $options = []) {
    $isMultipart = false;
    $replyMarkup = !empty($options['reply_markup'])
        ? (is_string($options['reply_markup']) ? $options['reply_markup'] : json_encode($options['reply_markup'], JSON_UNESCAPED_UNICODE))
        : null;

    // Check if photo is a local file path
    if (is_string($photo) && file_exists($photo)) {
        $photo = new CURLFile(realpath($photo));
        $isMultipart = true;
    }

    $params = [
        'chat_id' => (string)$chatId,
        'photo' => $photo,
        'caption' => (string)$caption,
        'parse_mode' => $options['parse_mode'] ?? 'HTML'
    ];

    if ($replyMarkup) {
        $params['reply_markup'] = $replyMarkup;
    }

    if (!empty($options['business_connection_id'])) {
        $params['business_connection_id'] = $options['business_connection_id'];
    }

    return telegramApiRequest($botToken, 'sendPhoto', $params, $isMultipart);
}

/**
 * Set Webhook for incoming updates (including business_message)
 */
function setTelegramWebhook($botToken, $webhookUrl, $secretToken = null) {
    $params = [
        'url' => $webhookUrl,
        'allowed_updates' => json_encode(['message', 'callback_query', 'business_connection', 'business_message', 'edited_business_message'])
    ];
    if (!empty($secretToken)) {
        $params['secret_token'] = $secretToken;
    }
    return telegramApiRequest($botToken, 'setWebhook', $params);
}

/**
 * Get current webhook info
 */
function getTelegramWebhookInfo($botToken) {
    return telegramApiRequest($botToken, 'getWebhookInfo');
}

/**
 * Delete webhook
 */
function deleteTelegramWebhook($botToken) {
    return telegramApiRequest($botToken, 'deleteWebhook');
}
