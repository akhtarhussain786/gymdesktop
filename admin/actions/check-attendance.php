<?php
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/helpers.php';
Auth::requireAuth(['gym_admin', 'staff', 'trainer']);

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
$currentTime = date('H:i:s'); // TIME column (12h 'h:i A' lost AM/PM)

$member = $memberId > 0 ? DB::fetchOne("SELECT status, paid_date, plan FROM members WHERE user_id = ? AND tenant_id = ?", [$memberId, $tenantId]) : null;
$expiry = ($member && !empty($member['paid_date'])) ? date('Y-m-d', strtotime('+' . max(1, (int)$member['plan']) . ' months', strtotime($member['paid_date']))) : null;
if (!$member || $member['status'] !== 'Active' || $expiry === null || $expiry < $todayDate) {
    set_flash('error', 'Member not found or membership expired. Please renew before check-in.');
} else {
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
        set_flash('success', 'Member checked in successfully at ' . date('h:i A'));
    } else {
        set_flash('info', 'Member already checked in today.');
    }
}

redirect('../attendance.php');