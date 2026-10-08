<?php
/**
 * Gym Admin Settings & Branding API
 * Allows gym owner to update branding, contact info, and their own UPI ID for receiving payments
 */

require_once __DIR__ . '/middleware.php';

$auth = AdminAuthMiddleware::authenticate();
$tenantId = (int)$auth['tenant_id'];
$method = $_SERVER['REQUEST_METHOD'];

// Ensure all required columns exist in tenants table
static $tenantsSchemaChecked = false;
if (!$tenantsSchemaChecked) {
    try {
        if (!api_column_exists('tenants', 'upi_id')) {
            @DB::query("ALTER TABLE `tenants` ADD COLUMN `upi_id` varchar(100) DEFAULT NULL AFTER `phone`");
        }
        if (!api_column_exists('tenants', 'upi_qr')) {
            @DB::query("ALTER TABLE `tenants` ADD COLUMN `upi_qr` varchar(255) DEFAULT NULL AFTER `upi_id`");
        }
        if (!api_column_exists('tenants', 'primary_color')) {
            @DB::query("ALTER TABLE `tenants` ADD COLUMN `primary_color` varchar(16) DEFAULT '#3b82f6'");
        }
        if (!api_column_exists('tenants', 'secondary_color')) {
            @DB::query("ALTER TABLE `tenants` ADD COLUMN `secondary_color` varchar(16) DEFAULT '#10b981'");
        }
        if (!api_column_exists('tenants', 'currency')) {
            @DB::query("ALTER TABLE `tenants` ADD COLUMN `currency` varchar(10) DEFAULT '₹'");
        }
        if (!api_column_exists('tenants', 'timezone')) {
            @DB::query("ALTER TABLE `tenants` ADD COLUMN `timezone` varchar(64) DEFAULT 'Asia/Kolkata'");
        }
    } catch (Throwable $e) {
        error_log("Tenants schema auto-alter notice: " . $e->getMessage());
    }
    $tenantsSchemaChecked = true;
}

$tenant = DB::fetchOne("SELECT * FROM tenants WHERE id = ?", [$tenantId]) ?: Tenant::getCurrent();
if (!$tenant) {
    ApiResponse::notFound('Tenant not found');
}

// Handle POST: Update settings
if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true) ?: $_POST;

    $gym_name = trim($input['gym_name'] ?? '');
    $email = trim($input['email'] ?? '');
    $phone = trim($input['phone'] ?? '');
    $address = trim($input['address'] ?? '');
    $currency = trim($input['currency'] ?? '₹');
    $timezone = trim($input['timezone'] ?? 'Asia/Kolkata');
    $primary_color = trim($input['primary_color'] ?? '#3b82f6');
    $secondary_color = trim($input['secondary_color'] ?? '#10b981');
    $upi_id = trim($input['upi_id'] ?? '');

    if ($gym_name === '') {
        $gym_name = $tenant['gym_name'] ?? $tenant['name'] ?? 'My Gym';
    }

    $updateData = [
        'gym_name' => $gym_name,
        'email' => $email,
        'phone' => $phone,
        'address' => $address,
        'currency' => $currency ?: '₹',
        'timezone' => $timezone ?: 'Asia/Kolkata',
        'primary_color' => $primary_color ?: '#3b82f6',
        'secondary_color' => $secondary_color ?: '#10b981',
        'upi_id' => $upi_id
    ];

    if (api_column_exists('tenants', 'name')) {
        $updateData['name'] = $gym_name;
    }

    // Handle base64 logo upload if provided
    if (!empty($input['logo_base64'])) {
        $data = $input['logo_base64'];
        if (preg_match('/^data:image\/(\w+);base64,/', $data, $type)) {
            $data = substr($data, strpos($data, ',') + 1);
            $type = strtolower($type[1]);
            if (!in_array($type, ['jpg', 'jpeg', 'gif', 'png', 'webp'])) {
                $type = 'png';
            }
            $data = base64_decode($data);
            if ($data !== false) {
                $logoDir = __DIR__ . '/../../uploads/logos';
                if (!is_dir($logoDir)) {
                    @mkdir($logoDir, 0777, true);
                }
                $filename = 'logo_' . $tenantId . '_' . time() . '.' . $type;
                file_put_contents($logoDir . '/' . $filename, $data);
                $updateData['logo'] = $filename;
            }
        }
    }

    DB::update('tenants', $updateData, 'id = ?', [$tenantId]);

    if (class_exists('Auth')) {
        try {
            Auth::auditLog('UPDATE_SETTINGS', "Updated gym settings and UPI ID to '$upi_id' for $gym_name", $tenantId);
        } catch (Throwable $e) {
            error_log("Audit log failed: " . $e->getMessage());
        }
    }

    // Fetch fresh updated record
    $tenant = DB::fetchOne("SELECT * FROM tenants WHERE id = ?", [$tenantId]);
    Tenant::setCurrent($tenant);

    $logoUrl = null;
    if (!empty($tenant['logo'])) {
        $logoUrl = base_url('/uploads/logos/' . $tenant['logo']);
    }

    ApiResponse::success([
        'tenant' => [
            'id' => (int)$tenant['id'],
            'gym_name' => $tenant['gym_name'] ?? $tenant['name'] ?? '',
            'email' => $tenant['email'] ?? '',
            'phone' => $tenant['phone'] ?? '',
            'address' => $tenant['address'] ?? '',
            'currency' => $tenant['currency'] ?? '₹',
            'timezone' => $tenant['timezone'] ?? 'Asia/Kolkata',
            'primary_color' => $tenant['primary_color'] ?? '#3b82f6',
            'secondary_color' => $tenant['secondary_color'] ?? '#10b981',
            'upi_id' => $tenant['upi_id'] ?? '',
            'logo' => $tenant['logo'] ?? '',
            'logo_url' => $logoUrl
        ]
    ], 'Settings and UPI ID updated successfully');
}

// GET: Return current settings
$logoUrl = null;
if (!empty($tenant['logo'])) {
    $logoUrl = base_url('/uploads/logos/' . $tenant['logo']);
}

ApiResponse::success([
    'tenant' => [
        'id' => (int)$tenant['id'],
        'gym_name' => $tenant['gym_name'] ?? $tenant['name'] ?? '',
        'email' => $tenant['email'] ?? '',
        'phone' => $tenant['phone'] ?? '',
        'address' => $tenant['address'] ?? '',
        'currency' => $tenant['currency'] ?? '₹',
        'timezone' => $tenant['timezone'] ?? 'Asia/Kolkata',
        'primary_color' => $tenant['primary_color'] ?? '#3b82f6',
        'secondary_color' => $tenant['secondary_color'] ?? '#10b981',
        'upi_id' => $tenant['upi_id'] ?? '',
        'logo' => $tenant['logo'] ?? '',
        'logo_url' => $logoUrl
    ]
], 'Settings retrieved successfully');
