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
Auth::requireAuth(['gym_admin', 'staff', 'trainer']);

$tenantId = Tenant::getTenantId();
$memberId = (int)($_GET['id'] ?? 0);
$todayDate = date('Y-m-d');
$currentTime = date('h:i A');

if ($memberId > 0) {
    $existing = DB::fetchOne("SELECT id FROM attendance WHERE user_id = ? AND curr_date = ? AND tenant_id = ?", [$memberId, $todayDate, $tenantId]);
    if (!$existing) {
        DB::insert('attendance', [
            'tenant_id' => $tenantId,
            'user_id' => $memberId,
            'curr_date' => $todayDate,
            'curr_time' => $currentTime,
            'present' => 1
        ]);
        DB::query("UPDATE members SET attendance_count = attendance_count + 1 WHERE user_id = ? AND tenant_id = ?", [$memberId, $tenantId]);
        set_flash('success', 'Member checked in successfully at ' . $currentTime);
    } else {
        set_flash('info', 'Member already checked in today.');
    }
}

redirect(base_url('/staff/staff-pages/attendance.php'));