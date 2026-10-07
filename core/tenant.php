<?php
/**
 * Core Multi-Tenant Context, SaaS Subscription Lifecycle & Scope Engine
 */

require_once __DIR__ . '/db.php';

class Tenant {
    private static $currentTenant = null;

    /**
     * Get active tenant ID from session, authenticated context, or current tenant.
     * Fails closed: gym-scoped users without a tenant context are logged out (never silently tenant #1).
     * Super admins (not impersonating) and anonymous visitors get 0 = "no tenant".
     */
    public static function getTenantId() {
        if (self::$currentTenant !== null && !empty(self::$currentTenant['id'])) {
            return (int)self::$currentTenant['id'];
        }
        if (isset($_SESSION['tenant_id']) && !empty($_SESSION['tenant_id']) && (int)$_SESSION['tenant_id'] > 0) {
            return (int)$_SESSION['tenant_id'];
        }
        if (!empty($_SESSION['user_id']) && ($_SESSION['role'] ?? '') !== 'super_admin') {
            self::failClosed('Missing tenant context for user #' . (int)$_SESSION['user_id']);
        }
        return 0;
    }

    /**
     * Set active tenant ID in session and current context
     */
    public static function setTenantId($id) {
        $id = (int)$id;
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['tenant_id'] = $id;
        }
        if (self::$currentTenant !== null && is_array(self::$currentTenant)) {
            self::$currentTenant['id'] = $id;
        }
    }

    /**
     * Explicitly set the active tenant payload
     */
    public static function setCurrent($tenant) {
        self::$currentTenant = is_array($tenant) ? $tenant : null;
    }

    /**
     * Terminate a session that has no valid tenant context
     */
    private static function failClosed($reason) {
        error_log('Tenant fail-closed: ' . $reason);
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_destroy();
        }
        if (!headers_sent()) {
            http_response_code(403);
            $login = function_exists('base_url') ? base_url('/index2.php') : '/index2.php';
            header('Location: ' . $login);
        }
        exit('Access denied: no gym account is associated with this session.');
    }

    /**
     * Reset cached tenant static instance
     */
    public static function reset() {
        self::$currentTenant = null;
    }

    /**
     * Get current tenant information (branding, settings, limits, subscription status)
     */
    public static function getCurrent() {
        if (self::$currentTenant !== null) {
            return self::$currentTenant;
        }

        $tenantId = self::getTenantId();
        $tenant = DB::fetchOne("SELECT t.*, p.name as plan_name, p.max_members, p.max_staff, p.max_branches, p.storage_limit_mb, p.features 
                                FROM tenants t 
                                LEFT JOIN subscription_plans p ON t.subscription_plan_id = p.id 
                                WHERE t.id = ?", [$tenantId]);

        if (!$tenant) {
            if (!empty($_SESSION['user_id']) && ($_SESSION['role'] ?? '') !== 'super_admin') {
                // Logged-in gym user whose tenant no longer exists: fail closed
                self::failClosed('Tenant #' . (int)$tenantId . ' not found for user #' . (int)$_SESSION['user_id']);
            }
            // Neutral platform context for super admins / anonymous pages (no real tenant data, no quota)
            $tenant = [
                'id' => 0,
                'gym_name' => 'Fitisify',
                'slug' => '',
                'owner_name' => '',
                'email' => '',
                'phone' => '',
                'address' => '',
                'logo' => null,
                'currency' => '₹',
                'timezone' => 'Asia/Kolkata',
                'primary_color' => '#3b82f6',
                'secondary_color' => '#10b981',
                'status' => 'active',
                'subscription_start' => date('Y-m-d'),
                'subscription_expiry' => date('Y-m-d', strtotime('+1 year')),
                'plan_name' => 'Platform',
                'max_members' => 0,
                'max_staff' => 0,
                'max_branches' => 0,
                'storage_limit_mb' => 0,
                'invoice_header' => '',
                'invoice_footer' => ''
            ];
        }

        self::$currentTenant = $tenant;

        $tz = !empty($tenant['timezone']) ? $tenant['timezone'] : 'Asia/Kolkata';
        if (in_array($tz, timezone_identifiers_list(), true)) {
            @date_default_timezone_set($tz);
        } else {
            @date_default_timezone_set('Asia/Kolkata');
        }

        return self::$currentTenant;
    }

    /**
     * Get computed subscription lifecycle status
     */
    public static function getSubscriptionStatus() {
        $tenant = self::getCurrent();
        $today = new DateTime(date('Y-m-d'));
        $expiry = new DateTime($tenant['subscription_expiry'] ?? date('Y-m-d'));
        $interval = $today->diff($expiry);
        $isPast = $today > $expiry;
        $daysDiff = (int)$interval->format('%r%a');

        $status = $tenant['status'];
        $graceDays = 7;
        $graceEnd = (clone $expiry)->modify("+{$graceDays} days");

        if ($status === 'suspended' || $status === 'cancelled' || $status === 'inactive') {
            return [
                'state' => $status,
                'is_active' => false,
                'can_write' => false,
                'days_left' => $daysDiff,
                'expiry_date' => $tenant['subscription_expiry'],
                'message' => 'Gym account is ' . ucfirst($status) . '. Please renew subscription to restore full access.'
            ];
        }

        if ($isPast) {
            if ($today <= $graceEnd) {
                return [
                    'state' => 'grace_period',
                    'is_active' => true,
                    'can_write' => true,
                    'days_left' => $daysDiff,
                    'expiry_date' => $tenant['subscription_expiry'],
                    'grace_end' => $graceEnd->format('Y-m-d'),
                    'message' => 'Your subscription has expired! You are currently in a grace period ending on ' . $graceEnd->format('M d, Y') . '. Please renew to prevent service restriction.'
                ];
            } else {
                return [
                    'state' => 'expired',
                    'is_active' => false,
                    'can_write' => false,
                    'days_left' => $daysDiff,
                    'expiry_date' => $tenant['subscription_expiry'],
                    'message' => 'Subscription expired on ' . $expiry->format('M d, Y') . '. New data entries are restricted. View-only access is enabled. Renew now to restore full operations.'
                ];
            }
        }

        if ($daysDiff <= 7) {
            return [
                'state' => 'expiring_soon',
                'is_active' => true,
                'can_write' => true,
                'days_left' => $daysDiff,
                'expiry_date' => $tenant['subscription_expiry'],
                'message' => 'Subscription expires in ' . $daysDiff . ' day' . ($daysDiff == 1 ? '' : 's') . ' (' . $expiry->format('M d, Y') . '). Renew today to ensure uninterrupted service.'
            ];
        }

        return [
            'state' => $status === 'trial' ? 'trial' : 'active',
            'is_active' => true,
            'can_write' => true,
            'days_left' => $daysDiff,
            'expiry_date' => $tenant['subscription_expiry'],
            'message' => null
        ];
    }

    /**
     * Check if tenant is allowed to write data (prevent lock-out from reads)
     */
    public static function canWrite() {
        $sub = self::getSubscriptionStatus();
        return $sub['can_write'];
    }

    /**
     * Render alert banner if subscription needs attention
     */
    public static function renderExpiryBanner() {
        $sub = self::getSubscriptionStatus();
        if (empty($sub['message'])) return '';

        $colors = [
            'expiring_soon' => ['bg' => 'rgba(245, 158, 11, 0.15)', 'border' => '#f59e0b', 'text' => '#d97706', 'icon' => 'fa-hourglass-half'],
            'grace_period' => ['bg' => 'rgba(239, 68, 68, 0.15)', 'border' => '#ef4444', 'text' => '#dc2626', 'icon' => 'fa-exclamation-triangle'],
            'expired' => ['bg' => 'rgba(239, 68, 68, 0.2)', 'border' => '#ef4444', 'text' => '#b91c1c', 'icon' => 'fa-lock'],
            'suspended' => ['bg' => 'rgba(239, 68, 68, 0.2)', 'border' => '#ef4444', 'text' => '#b91c1c', 'icon' => 'fa-ban'],
            'inactive' => ['bg' => 'rgba(239, 68, 68, 0.2)', 'border' => '#ef4444', 'text' => '#b91c1c', 'icon' => 'fa-ban']
        ];

        $c = $colors[$sub['state']] ?? $colors['expiring_soon'];

        $html = '<div style="background:' . $c['bg'] . '; border: 1px solid ' . $c['border'] . '; color:' . $c['text'] . '; padding: 14px 20px; border-radius: var(--radius-md); margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap;">';
        $html .= '<div style="display: flex; align-items: center; gap: 12px; font-size: 0.92rem; font-weight: 600;">';
        $html .= '<i class="fas ' . $c['icon'] . '" style="font-size: 1.3rem;"></i>';
        $html .= '<span>' . e($sub['message']) . '</span>';
        $html .= '</div>';
        $html .= '<a href="' . base_url('/admin/subscription.php') . '" class="btn btn-primary btn-sm" style="white-space: nowrap;">';
        $html .= '<i class="fas fa-sync-alt"></i> Renew / Upgrade Subscription';
        $html .= '</a>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Check resource quota limits
     */
    public static function checkLimit($resource = 'members') {
        $tenant = self::getCurrent();
        $tenantId = (int)($tenant['id'] ?? 0);
        if ($tenantId <= 0) {
            return ['allowed' => true, 'current' => 0, 'max' => 999999];
        }

        if ($resource === 'members') {
            $count = (int)DB::fetchValue("SELECT COUNT(*) FROM members WHERE tenant_id = ?", [$tenantId]);
            $max = (int)($tenant['max_members'] ?? 0);
            return ['allowed' => ($max <= 0 || $count < $max), 'current' => $count, 'max' => ($max <= 0 ? 999999 : $max)];
        }

        if ($resource === 'staff') {
            $count = (int)DB::fetchValue("SELECT COUNT(*) FROM staffs WHERE tenant_id = ?", [$tenantId]);
            $max = (int)($tenant['max_staff'] ?? 0);
            return ['allowed' => ($max <= 0 || $count < $max), 'current' => $count, 'max' => ($max <= 0 ? 999999 : $max)];
        }

        if ($resource === 'branches') {
            $count = (int)DB::fetchValue("SELECT COUNT(*) FROM branches WHERE tenant_id = ?", [$tenantId]);
            $max = (int)($tenant['max_branches'] ?? 0);
            return ['allowed' => ($max <= 0 || $count < $max), 'current' => $count, 'max' => ($max <= 0 ? 999999 : $max)];
        }

        return ['allowed' => true, 'current' => 0, 'max' => 999999];
    }

    /**
     * Ensure standard default membership packages/rates exist for a tenant.
     * If tenant has 0 rates, seeds sensible defaults into the `rates` table.
     */
    public static function ensureDefaultRates($tenantId) {
        $tenantId = (int)$tenantId;
        if ($tenantId <= 0) return [];

        try {
            $existing = DB::fetchValue("SELECT COUNT(*) FROM rates WHERE tenant_id = ?", [$tenantId]);
            if ((int)$existing === 0) {
                $defaults = [
                    ['name' => 'Fitness', 'charge' => 500.00],
                    ['name' => 'Cardio & Aerobics', 'charge' => 800.00],
                    ['name' => 'Strength & Conditioning', 'charge' => 1200.00],
                    ['name' => 'VIP All-Access Pass', 'charge' => 2000.00]
                ];
                foreach ($defaults as $def) {
                    try {
                        DB::insert('rates', [
                            'tenant_id' => $tenantId,
                            'name' => $def['name'],
                            'charge' => $def['charge']
                        ]);
                    } catch (Throwable $e) {
                        error_log("Failed to seed rate: " . $e->getMessage());
                    }
                }
            }
            return DB::fetchAll("SELECT * FROM rates WHERE tenant_id = ? ORDER BY charge ASC, id ASC", [$tenantId]);
        } catch (Throwable $e) {
            error_log("Tenant::ensureDefaultRates error: " . $e->getMessage());
            return [
                ['id' => 1, 'tenant_id' => $tenantId, 'name' => 'Fitness', 'charge' => 500.00],
                ['id' => 2, 'tenant_id' => $tenantId, 'name' => 'Cardio & Aerobics', 'charge' => 800.00],
                ['id' => 3, 'tenant_id' => $tenantId, 'name' => 'Strength & Conditioning', 'charge' => 1200.00],
                ['id' => 4, 'tenant_id' => $tenantId, 'name' => 'VIP All-Access Pass', 'charge' => 2000.00]
            ];
        }
    }

    /**
     * Get all active service packages & rates for the current or specified tenant,
     * ensuring default packages are seeded if none exist.
     */
    public static function getRates($tenantId = null) {
        if (!$tenantId) {
            $tenantId = self::getTenantId();
        }
        $tenantId = (int)$tenantId;
        if ($tenantId <= 0) return [];

        try {
            $rates = DB::fetchAll("SELECT * FROM rates WHERE tenant_id = ? ORDER BY charge ASC, id ASC", [$tenantId]);
            if (empty($rates)) {
                $rates = self::ensureDefaultRates($tenantId);
            }
            return $rates;
        } catch (Throwable $e) {
            error_log("Tenant::getRates error: " . $e->getMessage());
            return self::ensureDefaultRates($tenantId);
        }
    }
}
