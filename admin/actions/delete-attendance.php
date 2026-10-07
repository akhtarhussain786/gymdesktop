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
$memberId = (int)($_POST['id'] ?? 0);
$todayDate = date('Y-m-d');

if ($memberId > 0) {
    $deleted = DB::delete('attendance', 'user_id = ? AND curr_date = ? AND tenant_id = ?', [$memberId, $todayDate, $tenantId]);
    if ($deleted) {
        DB::query("UPDATE members SET attendance_count = GREATEST(0, attendance_count - 1) WHERE user_id = ? AND tenant_id = ?", [$memberId, $tenantId]);
        set_flash('warning', "Today's check-in record removed.");
    }
}

redirect('../attendance.php');