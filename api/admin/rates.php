<?php
/**
 * Gym Admin Rates & Packages API
 * Full CRUD for membership packages and service offerings
 */

require_once __DIR__ . '/middleware.php';

$auth = AdminAuthMiddleware::authenticate();
$tenantId = (int)$auth['tenant_id'];
$method = $_SERVER['REQUEST_METHOD'];

// Handle POST: Add, Update, or Delete
if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true) ?: $_POST;
    $action = $input['action'] ?? 'add';

    if ($action === 'delete') {
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            ApiResponse::error('Invalid package ID for deletion', 400);
        }
        $deleted = DB::delete('rates', 'id = ? AND tenant_id = ?', [$id, $tenantId]);
        if ($deleted) {
            Auth::auditLog('DELETE_RATE', "Deleted package ID $id");
            ApiResponse::success([], 'Package deleted successfully');
        } else {
            ApiResponse::error('Package not found or could not be deleted', 404);
        }
    }

    if ($action === 'update') {
        $id = (int)($input['id'] ?? 0);
        $name = trim($input['name'] ?? '');
        $charge = round((float)($input['charge'] ?? 0), 2);
        $type = trim($input['type'] ?? 'Monthly');

        if ($id <= 0 || empty($name) || $charge <= 0) {
            ApiResponse::error('Please provide a valid package name and positive charge', 400);
        }

        $existing = DB::fetchOne("SELECT * FROM rates WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);
        if (!$existing) {
            ApiResponse::error('Package not found', 404);
        }

        DB::update('rates', [
            'name' => $name,
            'charge' => $charge,
            'type' => $type
        ], 'id = ? AND tenant_id = ?', [$id, $tenantId]);

        Auth::auditLog('UPDATE_RATE', "Updated package '$name' with rate $charge");
        ApiResponse::success([], "Package '$name' updated successfully");
    }

    // Default: Add Rate
    $name = trim($input['name'] ?? '');
    $charge = round((float)($input['charge'] ?? 0), 2);
    $type = trim($input['type'] ?? 'Monthly');

    if (empty($name) || $charge <= 0) {
        ApiResponse::error('Package name and a positive charge amount are required', 400);
    }

    $exists = DB::fetchValue("SELECT id FROM rates WHERE tenant_id = ? AND LOWER(name) = LOWER(?)", [$tenantId, $name]);
    if ($exists) {
        ApiResponse::error("A package named '$name' already exists", 400);
    }

    $newId = DB::insert('rates', [
        'tenant_id' => $tenantId,
        'name' => $name,
        'charge' => $charge,
        'type' => $type
    ]);

    Auth::auditLog('ADD_RATE', "Created package '$name' with rate $charge");
    ApiResponse::success(['id' => $newId], "Package '$name' added successfully");
}

// GET: Retrieve all packages
Tenant::ensureDefaultRates($tenantId);
$rates = Tenant::getRates($tenantId);

$formattedRates = [];
foreach ($rates as $r) {
    $formattedRates[] = [
        'id' => (int)$r['id'],
        'name' => $r['name'],
        'charge' => (float)$r['charge'],
        'type' => $r['type'] ?? 'Monthly'
    ];
}

if (empty($formattedRates)) {
    $formattedRates = [
        ['id' => 1, 'name' => 'General Fitness', 'charge' => 1000.00, 'type' => 'Monthly'],
        ['id' => 2, 'name' => 'Strength & Cardio', 'charge' => 1500.00, 'type' => 'Monthly'],
        ['id' => 3, 'name' => 'Personal Training', 'charge' => 3000.00, 'type' => 'Monthly'],
        ['id' => 4, 'name' => 'CrossFit / HIIT', 'charge' => 2000.00, 'type' => 'Monthly']
    ];
}

ApiResponse::success(['rates' => $formattedRates], 'Rates retrieved successfully');
