<?php
/**
 * Gym Admin Rates & Packages API
 * Returns active packages and charges for the tenant to populate dropdowns in Add Member / Renewal
 */

require_once __DIR__ . '/middleware.php';

$auth = AdminAuthMiddleware::authenticate();
$tenantId = (int)$auth['tenant_id'];

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
