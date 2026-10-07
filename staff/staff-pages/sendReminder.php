<?php
// RETIRED legacy staff panel page (no tenant isolation / GET actions without CSRF). Superseded by admin/payment.php.
// Kept only as a stub; the legacy code below never runs.
require_once __DIR__ . '/../../core/auth.php';
Auth::requireAuth(['gym_admin', 'staff', 'trainer']);
redirect(base_url((($_SESSION['role'] ?? '') === 'trainer') ? '/trainer/index.php' : '/admin/payment.php'), 'info', 'This legacy page has been retired.');
exit;
?>
<?php
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/helpers.php';
Auth::requireAuth(['gym_admin', 'staff']);

$tenantId = Tenant::getTenantId();
$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    DB::update('members', ['reminder' => 1], 'user_id = ? AND tenant_id = ?', [$id, $tenantId]);
    set_flash('success', 'Fee reminder notification sent to member!');
}

redirect(base_url('/staff/staff-pages/payment.php'));