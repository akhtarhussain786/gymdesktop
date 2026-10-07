<?php
/**
 * Core Helper Functions
 */

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/tenant.php';
require_once __DIR__ . '/currencies.php';

/**
 * Get dynamic application base URL without hardcoding folder names
 */
function base_url($path = '') {
    // If already a full URL, return directly
    if (preg_match('#^https?://#i', (string)$path)) {
        return (string)$path;
    }

    $cleanPath = '';
    if ($path !== '' && $path !== null) {
        $cleanPath = '/' . ltrim((string)$path, '/');
        // Automatically remove .php extension from script paths (excluding static assets)
        if (!preg_match('/\.(css|js|png|jpg|jpeg|svg|webp|gif|pdf|mp4|json|xml|ico|woff|woff2|ttf|eot|otf|map|txt)([\?#]|$)/i', $cleanPath)) {
            $cleanPath = preg_replace('/\.php(?=[\?#]|$)/i', '', $cleanPath);
        }
        if ($cleanPath === '/index') {
            $cleanPath = '/';
        }
    }

    // Prefer the configured canonical APP_URL (never trust client-supplied host when set)
    $appUrl = function_exists('env') ? trim((string)env('APP_URL', '')) : '';
    if ($appUrl !== '' && preg_match('#^https?://[^\s/?\#]+(/[^\s?\#]*)?$#i', $appUrl)) {
        $canonicalBase = rtrim($appUrl, '/');
        if ($cleanPath === '') {
            return $canonicalBase;
        }
        if ($cleanPath === '/') {
            return $canonicalBase . '/';
        }
        return $canonicalBase . $cleanPath;
    }

    // Dynamic fallback when APP_URL is not set
    $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
    // Normalize path by stripping subfolders
    $cleanDir = preg_replace(
        '#/(admin|superadmin|customer(/pages)?|trainer|staff(/staff-pages)?|api(/member)?)$#i',
        '',
        str_replace('\\', '/', $scriptDir)
    );
    $cleanDir = rtrim($cleanDir, '/');
    // Prevent any legacy or physical folder name leakage
    $cleanDir = preg_replace('#/(Fitisify%20Fitness%20Gym%20MgMt%20system|Fitisify Fitness Gym MgMt system)#i', '', $cleanDir);

    $host = $_SERVER['HTTP_HOST'] ?? '';
    if (!preg_match('/^[A-Za-z0-9.\-]+(:\d{1,5})?$/', $host) && !preg_match('/^\[[0-9A-Fa-f:.]+\](:\d{1,5})?$/', $host)) {
        $host = '';
    }

    if (!empty($host)) {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
            || (isset($_SERVER['HTTP_FRONT_END_HTTPS']) && strtolower($_SERVER['HTTP_FRONT_END_HTTPS']) === 'on')
            || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
        $scheme = $isHttps ? 'https' : 'http';
        $fullBase = $scheme . '://' . $host . $cleanDir;

        if ($cleanPath === '') {
            return $fullBase;
        }
        if ($cleanPath === '/') {
            return $fullBase . '/';
        }
        return $fullBase . $cleanPath;
    }

    return $cleanDir . $cleanPath;
}

/**
 * Sanitize HTML output against XSS
 */
function e($str) {
    return htmlspecialchars((string)($str ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Format currency using current gym tenant's currency symbol
 */
function format_currency($amount) {
    $tenant = Tenant::getCurrent();
    $sym = $tenant['currency'] ?? '₹';
    return e($sym) . number_format((float)$amount, 2);
}

/**
 * Format date nicely
 */
function format_date($date, $format = 'M d, Y') {
    if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
        return '—';
    }
    return date($format, strtotime($date));
}

/**
 * Set flash toast message
 */
function set_flash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type, // success, error, warning, info
        'message' => $message
    ];
}

/**
 * Get and clear flash message
 */
function get_flash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Render status badge
 */
function status_badge($status) {
    $statusLower = strtolower(trim((string)$status));
    $classes = [
        'active' => 'badge-success',
        'paid' => 'badge-success',
        'operational' => 'badge-success',
        'completed' => 'badge-success',
        'trial' => 'badge-info',
        'in progress' => 'badge-warning',
        'pending' => 'badge-warning',
        'partial' => 'badge-warning',
        'expired' => 'badge-danger',
        'unpaid' => 'badge-danger',
        'suspended' => 'badge-danger',
        'inactive' => 'badge-secondary',
        'cancelled' => 'badge-danger'
    ];
    $cls = $classes[$statusLower] ?? 'badge-secondary';
    return '<span class="status-badge ' . $cls . '">' . e(ucfirst($status)) . '</span>';
}

/**
 * Output JSON response
 */
function json_response($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit();
}

/**
 * Redirect helper
 */
function redirect($url, $flashType = null, $flashMsg = null) {
    if ($flashType && $flashMsg) {
        set_flash($flashType, $flashMsg);
    }
    $target = (string)$url;
    // Automatically strip .php extension from URL (preserving query parameters and anchors)
    if (!preg_match('/\.(css|js|png|jpg|jpeg|svg|webp|gif|pdf|mp4|json|xml|ico|woff|woff2|ttf|eot|otf|map|txt)([\?#]|$)/i', $target)) {
        $target = preg_replace('/\.php(?=[\?#]|$)/i', '', $target);
    }
    // If path starts with '/', resolve against base_url() for a fully canonical redirect
    if (str_starts_with($target, '/')) {
        $target = base_url($target);
    }
    header("Location: " . $target);
    exit();
}
