<?php
/**
 * Public Gym Identification & Branding Lookup Endpoint
 * Supports Gym Code, Slug, or QR Code identification.
 * Validates status, subscription, and returns public branding.
 */

require_once __DIR__ . '/common.php';

$input = get_json_input();
$gymCode = trim($input['gym_code'] ?? $_GET['code'] ?? '');

if (empty($gymCode)) {
    ApiResponse::error('Please enter a valid Gym Code.', 422, ['gym_code' => 'Gym Code is required.']);
}

// Throttle code-guessing / enumeration per IP
$lookupRateKey = api_rate_key(['gym_lookup_ip', api_client_ip()]);
if (api_rate_limited('gym_lookup', $lookupRateKey, 30, 600)) {
    ApiResponse::error('Too many lookups. Please wait a few minutes and try again.', 429);
}
api_rate_record('gym_lookup', $lookupRateKey);

// Match on gym_code, slug, id or exact gym name
$cleanCode = strtolower($gymCode);
$numericId = is_numeric($gymCode) ? (int)$gymCode : 0;
$gym = DB::fetchOne(
    "SELECT t.id, t.gym_code, t.gym_name, t.slug, t.address, 
            t.logo, t.primary_color, t.secondary_color, t.currency, t.timezone, t.status, 
            t.subscription_expiry, p.name as plan_name, p.features
     FROM tenants t 
     LEFT JOIN subscription_plans p ON t.subscription_plan_id = p.id 
     WHERE LOWER(t.gym_code) = ? OR LOWER(t.slug) = ? OR LOWER(t.gym_name) = ? OR t.id = ? LIMIT 1",
    [$cleanCode, $cleanCode, $cleanCode, $numericId]
);

if (!$gym) {
    ApiResponse::notFound('No gym found matching this Gym Code. Please verify your Gym Code and try again.');
}

// Validate Gym Account Status & SaaS subscription (with 7-day grace)
$blockReason = api_tenant_block_reason($gym);
if ($blockReason !== null) {
    ApiResponse::forbidden($blockReason);
}

// Fetch public branches for this gym
$branches = DB::fetchAll(
    "SELECT id, branch_name, address, phone, is_main FROM branches WHERE tenant_id = ? AND status = 'active'",
    [$gym['id']]
);

// Format features
$features = [];
if (!empty($gym['features'])) {
    $features = json_decode($gym['features'], true) ?: [];
}

// Format Logo URL
$logoUrl = null;
if (!empty($gym['logo'])) {
    if (str_starts_with($gym['logo'], 'http')) {
        $logoUrl = $gym['logo'];
    } elseif (file_exists(__DIR__ . '/../../uploads/logos/' . $gym['logo'])) {
        $logoUrl = base_url('/uploads/logos/' . $gym['logo']);
    } else {
        $logoUrl = base_url('/img/' . $gym['logo']);
    }
}

// Build safe public gym branding payload
$publicGymInfo = [
    'gym_id' => (int)$gym['id'],
    'gym_code' => $gym['gym_code'] ?: 'GYM-' . $gym['id'],
    'gym_name' => $gym['gym_name'],
    'slug' => $gym['slug'],
    'logo' => $logoUrl,
    'address' => $gym['address'] ?: 'Fitness Facility',
    'currency' => $gym['currency'] ?: '$',
    'timezone' => $gym['timezone'] ?: 'UTC',
    'primary_color' => $gym['primary_color'] ?: '#2563eb',
    'secondary_color' => $gym['secondary_color'] ?: '#10b981',
    'plan_name' => $gym['plan_name'] ?: 'Standard Plan',
    'branches' => $branches,
    'features_enabled' => [
        'workouts' => in_array('all_features', $features) || in_array('workouts', $features),
        'diet' => in_array('all_features', $features) || in_array('diet', $features),
        'classes' => in_array('all_features', $features) || in_array('classes', $features),
        'online_payments' => in_array('all_features', $features) || in_array('payments', $features),
    ]
];

ApiResponse::success($publicGymInfo, 'Gym verified successfully.');
