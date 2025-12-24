<?php
// Visitor tracking script for KouPrey website
// Include this at the top of pages you want to track

require_once __DIR__ . '/../Config/database.php';

class VisitorTracker {
    private $pdo;
    private $session_timeout = 1800; // 30 minutes

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->trackVisitor();
    }

    private function trackVisitor() {
        try {
            $ip = $this->getClientIP();
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $pageUrl = $_SERVER['REQUEST_URI'] ?? '';
            $referrer = $_SERVER['HTTP_REFERER'] ?? '';
            $sessionId = session_id();

            // Skip tracking for bots only (allow local requests for development/testing)
            if ($this->isBot($userAgent)) {
                return;
            }

            // Get or create a persistent visitor ID (survives browser close/reopen)
            $visitorId = $this->getOrCreateVisitorId();

            // Record individual visit and check if this is a new visitor for today
            $isNewVisitor = $this->recordVisit($ip, $userAgent, $pageUrl, $referrer, $sessionId, $visitorId);

            // Only update daily statistics for new visitors (to avoid recounting the same visitor)
            if ($isNewVisitor) {
                $this->updateDailyStats();
            }

        } catch (Exception $e) {
            // Silently fail to avoid breaking the page
            error_log("Visitor tracking error: " . $e->getMessage());
        }
    }

    private function recordVisit($ip, $userAgent, $pageUrl, $referrer, $sessionId, $visitorId) {
        // Check if this visitor has already visited today
        $checkStmt = $this->pdo->prepare("
            SELECT id FROM visitors
            WHERE visitor_id = ? AND visit_date = CURDATE()
            LIMIT 1
        ");
        $checkStmt->execute([$visitorId]);

        $isNewVisitor = ($checkStmt->rowCount() == 0);

        // Always record the page view
        $stmt = $this->pdo->prepare("
            INSERT INTO visitors (ip_address, user_agent, page_url, referrer, session_id, visitor_id, visit_date, visit_time)
            VALUES (?, ?, ?, ?, ?, ?, CURDATE(), CURTIME())
        ");
        $stmt->execute([$ip, $userAgent, $pageUrl, $referrer, $sessionId, $visitorId]);

        return $isNewVisitor;
    }

    private function updateDailyStats() {
        $today = date('Y-m-d');

        // Get today's statistics
        // unique_visitors = number of distinct visitor_ids that visited today (each persistent visitor counts once per day)
        // total_visits = total page views from all visitors today
        $visitsStmt = $this->pdo->prepare("
            SELECT
                COUNT(DISTINCT visitor_id) as unique_visitors,
                COUNT(*) as total_page_views
            FROM visitors
            WHERE visit_date = ?
        ");
        $visitsStmt->execute([$today]);
        $stats = $visitsStmt->fetch();

        // Update or insert daily stats
        $updateStmt = $this->pdo->prepare("
            INSERT INTO visitor_stats (date, total_visitors, unique_visitors, page_views)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                total_visitors = VALUES(total_visitors),
                unique_visitors = VALUES(unique_visitors),
                page_views = VALUES(page_views),
                updated_at = CURRENT_TIMESTAMP
        ");

        $updateStmt->execute([
            $today,
            $stats['total_page_views'],    // Total page views
            $stats['unique_visitors'],     // Unique visitors (persistent visitor_ids)
            $stats['total_page_views']     // Page views (same as total_visitors for now)
        ]);
    }

    private function getClientIP() {
        $ip_headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // Handle comma-separated IPs (like X-Forwarded-For)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    private function isBot($userAgent) {
        $bot_patterns = [
            'bot', 'crawler', 'spider', 'scraper', 'indexer',
            'googlebot', 'bingbot', 'yahoo', 'baidu', 'yandex',
            'facebook', 'twitter', 'linkedin', 'whatsapp'
        ];

        $userAgent = strtolower($userAgent);
        foreach ($bot_patterns as $pattern) {
            if (strpos($userAgent, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    private function getOrCreateVisitorId() {
        // Check if visitor ID exists in cookie
        if (isset($_COOKIE['kouprey_visitor_id'])) {
            return $_COOKIE['kouprey_visitor_id'];
        }

        // Create new visitor ID (32 character random string)
        $visitorId = bin2hex(random_bytes(16));

        // Set cookie that lasts for 1 year
        setcookie('kouprey_visitor_id', $visitorId, time() + (365 * 24 * 60 * 60), '/');

        return $visitorId;
    }

    public static function getTotalVisitors() {
        try {
            require_once __DIR__ . '/../Config/database.php';
            global $pdo;
            $stmt = $pdo->query("SELECT COUNT(DISTINCT visitor_id) as total FROM visitors");
            $result = $stmt->fetch();
            return (int) $result['total'];
        } catch (Exception $e) {
            return 0;
        }
    }

    public static function getTodayVisitors() {
        try {
            require_once __DIR__ . '/../Config/database.php';
            global $pdo;
            $stmt = $pdo->prepare("SELECT unique_visitors FROM visitor_stats WHERE date = CURDATE()");
            $stmt->execute();
            $result = $stmt->fetch();
            return (int) ($result['unique_visitors'] ?? 0);
        } catch (Exception $e) {
            return 0;
        }
    }
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize visitor tracking
$tracker = new VisitorTracker($pdo);
?>