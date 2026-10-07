<?php
/**
 * Gym Admin Equipment Inventory API
 * List, Add, Edit, and Delete Gym Equipment matching admin/equipment.php
 */

require_once __DIR__ . '/middleware.php';

$auth = AdminAuthMiddleware::authenticate();
$tenant = $auth['tenant'];
$tenantId = (int)$auth['tenant_id'];

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $equipments = DB::fetchAll(
        "SELECT id, name, amount, quantity, vendor, description, address, contact, date
         FROM equipment 
         WHERE tenant_id = ? 
         ORDER BY id DESC",
        [$tenantId]
    );

    $totalValuation = 0.0;
    $totalUnits = 0;
    foreach ($equipments as $e) {
        $totalValuation += ((float)$e['amount'] * (int)$e['quantity']);
        $totalUnits += (int)$e['quantity'];
    }

    ApiResponse::success([
        'total_count' => count($equipments),
        'total_units' => $totalUnits,
        'total_valuation' => $totalValuation,
        'equipments' => $equipments
    ], 'Equipment inventory retrieved successfully');
}

if ($method === 'POST') {
    $input = get_json_input();
    if (empty($input)) $input = $_POST;

    $action = trim($input['action'] ?? 'create');

    if ($action === 'delete') {
        $deleteId = (int)($input['id'] ?? 0);
        if ($deleteId <= 0) {
            ApiResponse::error('Equipment ID is required', 400);
        }
        DB::delete('equipment', 'id = ? AND tenant_id = ?', [$deleteId, $tenantId]);
        ApiResponse::success(null, 'Equipment removed successfully');
    }

    $name = trim($input['name'] ?? '');
    $amount = (float)($input['amount'] ?? 0.0);
    $quantity = max(1, (int)($input['quantity'] ?? 1));
    $vendor = trim($input['vendor'] ?? '');
    $description = trim($input['description'] ?? '');
    $address = trim($input['address'] ?? '');
    $contact = trim($input['contact'] ?? '');
    $date = !empty($input['date']) ? trim($input['date']) : date('Y-m-d');

    if (empty($name)) {
        ApiResponse::error('Equipment name is required.', 422);
    }

    $editId = (int)($input['id'] ?? 0);
    if ($editId > 0) {
        DB::update('equipment', [
            'name' => $name,
            'amount' => $amount,
            'quantity' => $quantity,
            'vendor' => $vendor ?: null,
            'description' => $description ?: null,
            'address' => $address ?: null,
            'contact' => $contact ?: null,
            'date' => $date
        ], 'id = ? AND tenant_id = ?', [$editId, $tenantId]);

        ApiResponse::success(['id' => $editId], 'Equipment updated successfully');
    } else {
        $id = DB::insert('equipment', [
            'tenant_id' => $tenantId,
            'name' => $name,
            'amount' => $amount,
            'quantity' => $quantity,
            'vendor' => $vendor ?: null,
            'description' => $description ?: null,
            'address' => $address ?: null,
            'contact' => $contact ?: null,
            'date' => $date
        ]);

        ApiResponse::success(['id' => $id], 'Equipment added successfully!', 201);
    }
}
