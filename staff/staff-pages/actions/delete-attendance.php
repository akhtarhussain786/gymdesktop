<?php
// RETIRED legacy staff panel page (no tenant isolation / GET actions without CSRF). Superseded by admin/attendance.php.
// Kept only as a stub; the legacy code below never runs.
require_once __DIR__ . '/../../../core/auth.php';
Auth::requireAuth(['gym_admin', 'staff', 'trainer']);
redirect(base_url((($_SESSION['role'] ?? '') === 'trainer') ? '/trainer/index.php' : '/admin/attendance.php'), 'info', 'This legacy page has been retired.');
exit;
?>
<?php
require_once __DIR__ . '/../../../core/auth.php';
require_once __DIR__ . '/../../../core/helpers.php';
Auth::requireAuth(['gym_admin', 'staff']);

$tenantId = Tenant::getTenantId();
$memberId = (int)($_GET['id'] ?? 0);
$todayDate = date('Y-m-d');

if ($memberId > 0) {
    $deleted = DB::delete('attendance', 'user_id = ? AND curr_date = ? AND tenant_id = ?', [$memberId, $todayDate, $tenantId]);
    if ($deleted) {
        DB::query("UPDATE members SET attendance_count = GREATEST(0, attendance_count - 1) WHERE user_id = ? AND tenant_id = ?", [$memberId, $tenantId]);
        set_flash('warning', "Today's check-in record removed.");
    }
}

redirect(base_url('/staff/staff-pages/attendance.php'));