<?php
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/helpers.php';
Auth::requireAuth(['gym_admin']);

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
    // Remove the member's login + session artefacts so a deleted member can no longer sign in
    // (users mirror row, mobile API tokens) and drop operational child rows. Invoices and
    // payment records are intentionally KEPT for financial/audit history; pending/queued
    // subscriptions are cancelled rather than deleted.
    if (DB::fetchValue("SELECT user_id FROM members WHERE user_id = ? AND tenant_id = ?", [$id, $tenantId])) {
        DB::delete('users', "member_id = ? AND tenant_id = ? AND role = 'member'", [$id, $tenantId]);
        DB::delete('member_tokens', 'member_id = ? AND tenant_id = ?', [$id, $tenantId]);
        DB::delete('device_tokens', 'member_id = ? AND tenant_id = ?', [$id, $tenantId]);
        DB::delete('attendance', 'user_id = ? AND tenant_id = ?', [$id, $tenantId]);
        DB::delete('member_assigned_plans', 'member_id = ? AND tenant_id = ?', [$id, $tenantId]);
        DB::delete('class_bookings', 'member_id = ? AND tenant_id = ?', [$id, $tenantId]);
        DB::delete('todo', 'user_id = ? AND tenant_id = ?', [$id, $tenantId]);
        DB::query("UPDATE member_subscriptions SET status = 'cancelled' WHERE member_id = ? AND tenant_id = ? AND status IN ('active', 'upcoming', 'pending_payment')", [$id, $tenantId]);
        DB::delete('members', 'user_id = ? AND tenant_id = ?', [$id, $tenantId]);
        Auth::auditLog('DELETE_MEMBER', "Deleted member ID " . $id);
    }
    set_flash('success', 'Member deleted successfully.');
}

redirect(base_url('/admin/members.php'));