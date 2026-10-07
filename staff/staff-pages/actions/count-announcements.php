<?php
// RETIRED legacy staff panel page (no tenant isolation / GET actions without CSRF). Superseded by admin/index.php.
// Kept only as a stub; the legacy code below never runs.
require_once __DIR__ . '/../../../core/auth.php';
Auth::requireAuth(['gym_admin', 'staff', 'trainer']);
redirect(base_url((($_SESSION['role'] ?? '') === 'trainer') ? '/trainer/index.php' : '/admin/index.php'), 'info', 'This legacy page has been retired.');
exit;
?>
<?php
require_once __DIR__ . '/../../../core/auth.php';
$tenantId = Tenant::getTenantId();
echo (int)DB::fetchValue("SELECT COUNT(*) FROM announcements WHERE tenant_id = ?", [$tenantId]);