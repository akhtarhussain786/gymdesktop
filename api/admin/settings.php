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

    if (!is_array($input)) {
        $input = [];
    }

    // Only fields present in the request are changed: the app never sends timezone, and a missing field
    // must not reset the gym's existing value to a default.
    $updateData = [];
    $errors = [];

    if (array_key_exists('gym_name', $input)) {
        $gym_name = trim((string)$input['gym_name']);
        if ($gym_name !== '') {
            $updateData['gym_name'] = $gym_name;
            if (api_column_exists('tenants', 'name')) {
                $updateData['name'] = $gym_name;
            }
        }
    }

    if (array_key_exists('email', $input)) {
        $email = trim((string)$input['email']);
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $updateData['email'] = $email;
        } elseif ($email !== (string)($tenant['email'] ?? '')) {
            $errors['email'] = 'Please enter a valid email address.';
        }
    }

    foreach (['phone', 'address'] as $field) {
        if (array_key_exists($field, $input)) {
            $updateData[$field] = trim((string)$input[$field]);
        }
    }

    if (array_key_exists('currency', $input)) {
        // Whitelist like the web settings page (symbols drive money formatting everywhere)
        $currency = trim((string)$input['currency']);
        $validSymbols = array_column(get_supported_currencies(), 'symbol');
        if (!in_array($currency, $validSymbols, true)) {
            $match = array_values(array_filter($validSymbols, function ($sym) use ($currency) { return trim($sym) === $currency; }));
            $currency = $match[0] ?? null;
        }
        if ($currency !== null) {
            $updateData['currency'] = $currency;
        }
    }

    if (array_key_exists('timezone', $input)) {
        $timezone = trim((string)$input['timezone']);
        if (in_array($timezone, timezone_identifiers_list(), true)) {
            $updateData['timezone'] = $timezone;
        } elseif ($timezone !== '') {
            $errors['timezone'] = 'Unknown timezone.';
        }
    }

    // Colours: the app has no colour picker and always sends these two defaults; treat that exact pair as
    // "not changed" so saving from the app does not wipe colours chosen on the web panel.
    $appDefaultColours = (($input['primary_color'] ?? null) === '#3b82f6' && ($input['secondary_color'] ?? null) === '#10b981');
    if (!$appDefaultColours) {
        foreach (['primary_color', 'secondary_color'] as $field) {
            if (array_key_exists($field, $input)) {
                $colour = trim((string)$input[$field]);
                if (preg_match('/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $colour)) {
                    $updateData[$field] = $colour;
                } elseif ($colour !== '') {
                    $errors[$field] = 'Colour must be a hex value like #3b82f6.';
                }
            }
        }
    }

    if (array_key_exists('upi_id', $input)) {
        $upi_id = trim((string)$input['upi_id']);
        // Empty clears it; a stored legacy value sent back unchanged is accepted as-is
        if ($upi_id === '' || is_valid_upi_id($upi_id) || $upi_id === trim((string)($tenant['upi_id'] ?? ''))) {
            $updateData['upi_id'] = $upi_id;
        } else {
            $errors['upi_id'] = 'Please enter a valid UPI ID (e.g. yourgym@okhdfcbank).';
        }
    }

    if (!empty($errors)) {
        ApiResponse::error(reset($errors), 422, $errors);
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
            $data = base64_decode($data, true);
            if ($data === false || @getimagesizefromstring($data) === false) {
                ApiResponse::error('The logo must be a valid PNG, JPG, GIF or WEBP image.', 422);
            }
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

    if (!empty($updateData)) {
        DB::update('tenants', $updateData, 'id = ?', [$tenantId]);
    }

    if (class_exists('Auth')) {
        try {
            $gymLabel = $updateData['gym_name'] ?? ($tenant['gym_name'] ?? '');
            Auth::auditLog('UPDATE_SETTINGS', "Updated gym settings (" . implode(', ', array_keys($updateData)) . ") for $gymLabel", $tenantId, (int)$auth['user_id']);
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
