<?php
/**
 * API: Fetch Available SaaS Subscription Plans & Current Tenant Subscription
 * Endpoint: GET /api/subscription/plans.php
 */

require_once __DIR__ . '/../admin/middleware.php';

$auth = AdminAuthMiddleware::authenticate();
$tenantId = (int)$auth['tenant_id'];

$tenant = DB::fetchOne("SELECT t.*, sp.name as plan_name, sp.max_members, sp.max_staff 
                        FROM tenants t 
                        LEFT JOIN subscription_plans sp ON t.subscription_plan_id = sp.id 
                        WHERE t.id = ?", [$tenantId]);

if (!$tenant) {
    ApiResponse::error('Tenant not found', 404);
}

// 1. Current Subscription Status
$subStatus = Tenant::getSubscriptionStatus();
$today = date('Y-m-d');
$expiryDate = $tenant['subscription_expiry'] ?? $today;
$isExpired = ($expiryDate < $today) || ($tenant['status'] === 'expired');

$currentSubscription = [
    'plan_id' => (int)($tenant['subscription_plan_id'] ?? 0),
    'plan_name' => $tenant['plan_name'] ?? 'Standard SaaS Plan',
    'status' => $isExpired ? 'expired' : 'active',
    'start_date' => $tenant['subscription_start'] ?? $today,
    'expiry_date' => $expiryDate,
    'days_remaining' => (int)($subStatus['days_left'] ?? 0),
    'max_members' => (int)($tenant['max_members'] ?? 100),
    'max_staff' => (int)($tenant['max_staff'] ?? 10),
];

// 2. Available Active Plans from Database
$plansRaw = DB::fetchAll("SELECT * FROM subscription_plans WHERE is_active = 1 ORDER BY price_monthly ASC");
$plansList = [];

foreach ($plansRaw as $p) {
    $features = [];
    if (!empty($p['features'])) {
        $decoded = json_decode($p['features'], true);
        if (is_array($decoded)) {
            $features = $decoded;
        }
    }

    $monthly = (float)$p['price_monthly'];
    $yearly = (float)$p['price_yearly'];
    $quarterly = round($monthly * 3 * 0.90, 2);

    $plansList[] = [
        'plan_id' => (int)$p['id'],
        'plan_name' => $p['name'],
        'slug' => $p['slug'],
        'price' => $monthly,
        'price_monthly' => $monthly,
        'price_quarterly' => $quarterly,
        'price_yearly' => $yearly,
        'duration' => '1 Month',
        'duration_months' => 1,
        'features' => $features,
        'max_members' => (int)$p['max_members'],
        'max_staff' => (int)$p['max_staff'],
        'status' => 'active',
        'is_current_plan' => ((int)$p['id'] === (int)($tenant['subscription_plan_id'] ?? 0))
    ];
}

ApiResponse::success([
    'current_subscription' => $currentSubscription,
    'plans' => $plansList,
    'currency' => $tenant['currency'] ?? '₹'
], 'Subscription plans retrieved successfully');
