<?php
/**
 * Real Website Visitor & Lead Intelligence Engine
 * Tracks genuine visitors, geo-data, devices, and captures real visitor leads (Name, Phone, Email, City).
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/helpers.php';

class VisitorTracker {

    /**
     * Ensure Database Tables Exist
     */
    public static function ensureTables() {
        try {
            DB::query("
                CREATE TABLE IF NOT EXISTS `website_visitors` (
                    `id` BIGINT(20) AUTO_INCREMENT PRIMARY KEY,
                    `session_id` VARCHAR(100) NOT NULL,
                    `ip_address` VARCHAR(45) NOT NULL,
                    `device` VARCHAR(50) DEFAULT 'Desktop',
                    `browser` VARCHAR(100) DEFAULT 'Unknown',
                    `os` VARCHAR(100) DEFAULT 'Unknown',
                    `country` VARCHAR(100) DEFAULT 'India',
                    `city` VARCHAR(100) DEFAULT 'Unknown',
                    `page_visited` VARCHAR(255) NOT NULL,
                    `referrer` VARCHAR(500) NULL,
                    `lead_id` INT(11) NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX `idx_session` (`session_id`),
                    INDEX `idx_ip` (`ip_address`),
                    INDEX `idx_created` (`created_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            DB::query("
                CREATE TABLE IF NOT EXISTS `website_leads` (
                    `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
                    `session_id` VARCHAR(100) NULL,
                    `full_name` VARCHAR(150) NOT NULL,
                    `phone` VARCHAR(30) NOT NULL,
                    `email` VARCHAR(150) NULL,
                    `gym_name` VARCHAR(150) NULL,
                    `city` VARCHAR(100) NULL,
                    `interested_plan` VARCHAR(100) DEFAULT 'Pro Plan',
                    `message` TEXT NULL,
                    `ip_address` VARCHAR(45) NOT NULL,
                    `source_page` VARCHAR(255) NULL,
                    `status` ENUM('new', 'contacted', 'demo_scheduled', 'converted', 'closed') DEFAULT 'new',
                    `notes` TEXT NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX `idx_phone` (`phone`),
                    INDEX `idx_status` (`status`),
                    INDEX `idx_created` (`created_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
        } catch (Exception $e) {
            // Graceful fallback
        }
    }

    /**
     * Get Client Real IP
     */
    public static function getClientIp() {
        $remote = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        // Forwarding headers are client-controlled; only honour them when the direct peer is a trusted proxy
        $trusted = array_filter(array_map('trim', explode(',', (string)(function_exists('env') ? env('TRUSTED_PROXIES', '') : ''))));
        if (!empty($trusted) && in_array($remote, $trusted, true)) {
            foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR'] as $h) {
                if (!empty($_SERVER[$h])) {
                    $ips = explode(',', $_SERVER[$h]);
                    $ip = trim($ips[0]);
                    if (filter_var($ip, FILTER_VALIDATE_IP)) {
                        return $ip;
                    }
                }
            }
        }
        return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '127.0.0.1';
    }

    /**
     * Parse User-Agent for Device, OS and Browser
     */
    public static function parseUserAgent($ua = null) {
        $ua = $ua ?: ($_SERVER['HTTP_USER_AGENT'] ?? '');
        
        $device = 'Desktop';
        if (preg_match('/(tablet|ipad|playbook)|(android(?!.*(mobi|opera mini)))/i', $ua)) {
            $device = 'Tablet';
        } elseif (preg_match('/(up.browser|up.link|mmp|symbian|smartphone|midp|wap|phone|android|iemobile|iphone)/i', $ua)) {
            $device = 'Mobile';
        }

        $os = 'Unknown OS';
        $osRules = [
            '/windows nt 10/i'      => 'Windows 10/11',
            '/windows nt 6.3/i'     => 'Windows 8.1',
            '/windows nt 6.2/i'     => 'Windows 8',
            '/windows nt 6.1/i'     => 'Windows 7',
            '/macintosh|mac os x/i' => 'Mac OS X',
            '/mac_powerpc/i'        => 'Mac OS 9',
            '/linux/i'              => 'Linux',
            '/ubuntu/i'             => 'Ubuntu',
            '/iphone/i'             => 'iOS (iPhone)',
            '/ipad/i'               => 'iOS (iPad)',
            '/android/i'            => 'Android'
        ];
        foreach ($osRules as $regex => $value) {
            if (preg_match($regex, $ua)) {
                $os = $value;
                break;
            }
        }

        $browser = 'Unknown Browser';
        $browserRules = [
            '/msie/i'      => 'Internet Explorer',
            '/firefox/i'   => 'Firefox',
            '/safari/i'    => 'Safari',
            '/chrome/i'    => 'Chrome',
            '/edge/i'      => 'Edge',
            '/opera/i'     => 'Opera',
            '/netscape/i'  => 'Netscape',
            '/maxthon/i'   => 'Maxthon',
            '/konqueror/i' => 'Konqueror',
            '/mobile/i'    => 'Mobile Browser'
        ];
        foreach ($browserRules as $regex => $value) {
            if (preg_match($regex, $ua)) {
                $browser = $value;
                break;
            }
        }
        if ($browser === 'Safari' && preg_match('/chrome/i', $ua)) {
            $browser = 'Chrome';
        }

        return ['device' => $device, 'os' => $os, 'browser' => $browser];
    }

    /**
     * Track Website Visit
     */
    public static function logVisit($page = null) {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            secure_session_start();
        }

        // Generate or retain persistent visitor session
        if (empty($_SESSION['fitisify_visitor_sid'])) {
            $_SESSION['fitisify_visitor_sid'] = 'vs_' . bin2hex(random_bytes(16));
        }
        $sessionId = $_SESSION['fitisify_visitor_sid'];

        $page = $page ?: ($_SERVER['REQUEST_URI'] ?? '/');
        $ip = self::getClientIp();
        $uaInfo = self::parseUserAgent();
        $referrer = $_SERVER['HTTP_REFERER'] ?? '';

        // Avoid duplicate logging of same page reload within 5 seconds for same session
        $lastLogTime = $_SESSION['last_visit_log_time'] ?? 0;
        $lastLogPage = $_SESSION['last_visit_log_page'] ?? '';
        if (time() - $lastLogTime < 5 && $lastLogPage === $page) {
            return;
        }

        $_SESSION['last_visit_log_time'] = time();
        $_SESSION['last_visit_log_page'] = $page;

        self::ensureTables();

        try {
            DB::insert('website_visitors', [
                'session_id' => $sessionId,
                'ip_address' => $ip,
                'device' => $uaInfo['device'],
                'browser' => $uaInfo['browser'],
                'os' => $uaInfo['os'],
                'country' => 'India',
                'city' => 'Live Visitor',
                'page_visited' => substr($page, 0, 255),
                'referrer' => substr($referrer, 0, 500),
                'lead_id' => $_SESSION['fitisify_lead_id'] ?? null
            ]);
        } catch (Exception $e) {
            // Ignore failure
        }
    }

    /**
     * Capture Real Visitor Lead (Name, Phone, Email, Gym)
     */
    public static function captureLead($data) {
        self::ensureTables();

        if (!is_array($data)) {
            $data = [];
        }
        $str = function ($key, $default = '', $max = 150) use ($data) {
            $v = $data[$key] ?? $default;
            $v = is_scalar($v) ? trim((string)$v) : '';
            return mb_substr($v, 0, $max);
        };

        $sessionId = $_SESSION['fitisify_visitor_sid'] ?? ('vs_' . bin2hex(random_bytes(16)));
        $fullName = $str('full_name', '', 150);
        $phone = $str('phone', '', 30);
        $email = $str('email', '', 150);
        $gymName = $str('gym_name', '', 150);
        $city = $str('city', '', 100);
        $plan = $str('interested_plan', 'Pro Powerhouse', 100);
        $message = $str('message', '', 2000);
        $sourcePage = $str('source_page', ($_SERVER['HTTP_REFERER'] ?? '/'), 255);
        $ip = self::getClientIp();

        if (empty($fullName) || empty($phone)) {
            return ['success' => false, 'error' => 'Please enter your Full Name and Phone Number.'];
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Please enter a valid email address.'];
        }

        // Basic per-IP rate limit (rolling hour)
        try {
            $recent = (int)DB::fetchValue("SELECT COUNT(*) FROM website_leads WHERE ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)", [$ip]);
            if ($recent >= 5) {
                return ['success' => false, 'error' => 'Too many submissions. Please try again later.'];
            }
        } catch (Throwable $e) {
            error_log('Lead rate-limit check failed: ' . $e->getMessage());
        }

        // Clean phone
        $cleanPhone = preg_replace('/[^0-9+]/', '', $phone);
        if (strlen($cleanPhone) < 8) {
            return ['success' => false, 'error' => 'Please enter a valid Phone Number with at least 10 digits.'];
        }

        try {
            $leadId = DB::insert('website_leads', [
                'session_id' => $sessionId,
                'full_name' => $fullName,
                'phone' => $phone,
                'email' => $email,
                'gym_name' => $gymName,
                'city' => $city,
                'interested_plan' => $plan,
                'message' => $message,
                'ip_address' => $ip,
                'source_page' => substr($sourcePage, 0, 255),
                'status' => 'new'
            ]);

            if ($leadId) {
                $_SESSION['fitisify_lead_id'] = $leadId;

                // Link previous visits with this lead ID
                DB::query("UPDATE website_visitors SET lead_id = ? WHERE session_id = ?", [$leadId, $sessionId]);

                // Send instant email notification to platform admin
                self::notifyAdminNewLead($fullName, $phone, $email, $gymName, $city, $plan, $ip);

                return ['success' => true, 'lead_id' => $leadId, 'message' => 'Thank you! Our fitness SaaS consultant will connect with you on WhatsApp/Phone immediately.'];
            }
        } catch (Throwable $e) {
            error_log('Lead capture failed: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Unable to save lead. Please try again.'];
        }

        return ['success' => false, 'error' => 'Unable to save lead. Please try again.'];
    }

    /**
     * Send Real-Time Email Notification for New Lead
     */
    private static function notifyAdminNewLead($name, $phone, $email, $gym, $city, $plan, $ip) {
        try {
            $subject = "🔥 Real Visitor Lead: {$name} ({$phone}) - Fitisify OS";
            $waPhone = preg_replace('/[^0-9]/', '', $phone);
            // Escape all visitor-supplied values before embedding them in the HTML email
            $name = htmlspecialchars((string)$name, ENT_QUOTES, 'UTF-8');
            $phone = htmlspecialchars((string)$phone, ENT_QUOTES, 'UTF-8');
            $email = htmlspecialchars((string)$email, ENT_QUOTES, 'UTF-8');
            $gym = htmlspecialchars((string)$gym, ENT_QUOTES, 'UTF-8');
            $city = htmlspecialchars((string)$city, ENT_QUOTES, 'UTF-8');
            $plan = htmlspecialchars((string)$plan, ENT_QUOTES, 'UTF-8');
            $ip = htmlspecialchars((string)$ip, ENT_QUOTES, 'UTF-8');
            $body = "
            <div style='font-family:sans-serif; background:#0f172a; color:#fff; padding:30px; border-radius:12px;'>
                <h2 style='color:#a3e635; margin-top:0;'>🚀 New Website Visitor Inquiry Captured!</h2>
                <p style='color:#94a3b8; font-size:14px;'>A live visitor just submitted their details on Fitisify OS:</p>
                <table style='width:100%; border-collapse:collapse; margin-top:20px; color:#fff;'>
                    <tr style='border-bottom:1px solid #334155;'><td style='padding:10px; color:#94a3b8;'>Full Name:</td><td style='padding:10px; font-weight:bold;'>{$name}</td></tr>
                    <tr style='border-bottom:1px solid #334155;'><td style='padding:10px; color:#94a3b8;'>Phone / WhatsApp:</td><td style='padding:10px; font-weight:bold; color:#a3e635;'><a href='https://wa.me/" . $waPhone . "' style='color:#a3e635;'>{$phone}</a></td></tr>
                    <tr style='border-bottom:1px solid #334155;'><td style='padding:10px; color:#94a3b8;'>Email Address:</td><td style='padding:10px;'>{$email}</td></tr>
                    <tr style='border-bottom:1px solid #334155;'><td style='padding:10px; color:#94a3b8;'>Gym / Business:</td><td style='padding:10px;'>{$gym}</td></tr>
                    <tr style='border-bottom:1px solid #334155;'><td style='padding:10px; color:#94a3b8;'>City / Location:</td><td style='padding:10px;'>{$city}</td></tr>
                    <tr style='border-bottom:1px solid #334155;'><td style='padding:10px; color:#94a3b8;'>Interested Plan:</td><td style='padding:10px; font-weight:bold;'>{$plan}</td></tr>
                    <tr><td style='padding:10px; color:#94a3b8;'>Visitor IP Address:</td><td style='padding:10px;'>{$ip}</td></tr>
                </table>
                <div style='margin-top:25px;'>
                    <a href='https://wa.me/" . $waPhone . "' style='background:#22c55e; color:#fff; padding:12px 24px; border-radius:8px; text-decoration:none; font-weight:bold; display:inline-block;'>Chat on WhatsApp</a>
                </div>
            </div>";

            $notifyTo = function_exists('env') ? env('LEAD_NOTIFY_EMAIL', 'nexoralabtechnologies@gmail.com') : 'nexoralabtechnologies@gmail.com';
            Mailer::send($notifyTo, $subject, $body, 'visitor_lead');
        } catch (Throwable $e) {
            error_log('Lead notification email failed: ' . $e->getMessage());
        }
    }

    /**
     * Get Real-Time Analytics Metrics
     */
    public static function getMetrics() {
        self::ensureTables();
        $metrics = [
            'total_visitors_today' => 0,
            'total_visitors_all' => 0,
            'total_leads' => 0,
            'new_leads_today' => 0,
            'recent_leads' => [],
            'recent_visitors' => []
        ];

        try {
            $metrics['total_visitors_today'] = DB::fetchOne("SELECT COUNT(*) as c FROM website_visitors WHERE DATE(created_at) = CURDATE()")['c'] ?? 0;
            $metrics['total_visitors_all'] = DB::fetchOne("SELECT COUNT(*) as c FROM website_visitors")['c'] ?? 0;
            $metrics['total_leads'] = DB::fetchOne("SELECT COUNT(*) as c FROM website_leads")['c'] ?? 0;
            $metrics['new_leads_today'] = DB::fetchOne("SELECT COUNT(*) as c FROM website_leads WHERE DATE(created_at) = CURDATE()")['c'] ?? 0;
            
            $metrics['recent_leads'] = DB::fetchAll("SELECT * FROM website_leads ORDER BY id DESC LIMIT 50");
            $metrics['recent_visitors'] = DB::fetchAll("SELECT * FROM website_visitors ORDER BY id DESC LIMIT 50");
        } catch (Exception $e) {}

        return $metrics;
    }
}
