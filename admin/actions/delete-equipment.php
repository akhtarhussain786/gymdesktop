<?php
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/helpers.php';
Auth::requireAuth(['gym_admin', 'staff']);

// State-changing endpoint: POST + CSRF only (was a GET link, CSRF-able).
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method Not Allowed');
}
Auth::verifyCsrf();

$tenantId = Tenant::getTenantId();
$id = (int)($_POST['id'] ?? 0);

if ($id > 0) {
    DB::delete('equipment', 'id = ? AND tenant_id = ?', [$id, $tenantId]);
    Auth::auditLog('DELETE_EQUIPMENT', "Deleted equipment ID $id");
    set_flash('success', 'Equipment deleted successfully.');
}

redirect(base_url('/admin/equipment.php'));