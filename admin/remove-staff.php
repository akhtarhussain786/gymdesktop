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
    $staffUsername = DB::fetchValue("SELECT username FROM staffs WHERE user_id = ? AND tenant_id = ?", [$id, $tenantId]);
    // The person's users login: explicitly linked rows (users.staff_id) whatever their role but member /
    // super admin; unlinked older rows only by tenant + username + staff role, so an owner login is never hit
    $linkedUserIds = [];
    if ($staffUsername !== null) {
        if (in_array('staff_id', DB::tableColumns('users') ?? [], true)) {
            $linkedUserIds = array_column(DB::fetchAll(
                "SELECT id FROM users WHERE tenant_id = ? AND role NOT IN ('member', 'super_admin', 'superadmin')
                 AND (staff_id = ? OR (staff_id IS NULL AND username = ? AND role IN ('staff', 'trainer')))",
                [$tenantId, $id, $staffUsername]
            ), 'id');
        } else {
            $linkedUserIds = array_column(DB::fetchAll(
                "SELECT id FROM users WHERE tenant_id = ? AND username = ? AND role IN ('staff', 'trainer')",
                [$tenantId, $staffUsername]
            ), 'id');
        }
        if (($_SESSION['auth_source'] ?? '') === 'users' && in_array((int)($_SESSION['account_id'] ?? 0), array_map('intval', $linkedUserIds), true)) {
            redirect(base_url('/admin/staffs.php'), 'error', 'You cannot remove the staff record linked to your own login.');
        }
    }
    DB::delete('staffs', 'user_id = ? AND tenant_id = ?', [$id, $tenantId]);
    if ($staffUsername !== null) {
        foreach ($linkedUserIds as $linkedUserId) {
            Auth::revokeApiTokens($tenantId, (int)$linkedUserId);
            DB::delete('users', "id = ? AND tenant_id = ? AND role NOT IN ('member', 'super_admin', 'superadmin')", [(int)$linkedUserId, $tenantId]);
        }
        DB::query("UPDATE members SET trainer_id = NULL WHERE trainer_id = ? AND tenant_id = ?", [$id, $tenantId]);
    }
    Auth::auditLog('DELETE_STAFF', "Deleted staff member ID $id");
    set_flash('success', 'Staff member removed successfully.');
}

redirect(base_url('/admin/staffs.php'));