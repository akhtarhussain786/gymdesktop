<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helpers.php';
Auth::requireAuth('gym_admin');

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
    // users has no staff_id column: match the mirrored login row by tenant + username + staff role
    $staffUsername = DB::fetchValue("SELECT username FROM staffs WHERE user_id = ? AND tenant_id = ?", [$id, $tenantId]);
    DB::delete('staffs', 'user_id = ? AND tenant_id = ?', [$id, $tenantId]);
    if ($staffUsername !== null) {
        DB::delete('users', "tenant_id = ? AND username = ? AND role IN ('staff', 'trainer')", [$tenantId, $staffUsername]);
        DB::query("UPDATE members SET trainer_id = NULL WHERE trainer_id = ? AND tenant_id = ?", [$id, $tenantId]);
    }
    Auth::auditLog('DELETE_STAFF', "Deleted staff member ID $id");
    set_flash('success', 'Staff member removed successfully.');
}

redirect(base_url('/admin/staffs.php'));