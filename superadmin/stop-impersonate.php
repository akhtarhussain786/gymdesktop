<?php
/**
 * End a Super Admin impersonation session and restore the original super admin identity
 */
require_once __DIR__ . '/../core/auth.php';

// Re-validates the session (including the stored super admin identity) before restoring it
Auth::requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(base_url('/admin/index.php'), 'error', 'Invalid request.');
}
Auth::verifyCsrf();

$imp = $_SESSION['impersonator'] ?? null;
if (empty($imp) || ($imp['role'] ?? '') !== 'super_admin') {
    redirect(Auth::homeFor($_SESSION['role'] ?? ''), 'error', 'You are not currently impersonating a gym.');
}

$impersonatedTenant = (int)($_SESSION['tenant_id'] ?? 0);
Auth::auditLog('IMPERSONATE_STOP', 'Super Admin stopped impersonating tenant #' . $impersonatedTenant, $impersonatedTenant ?: null);

unset($_SESSION['impersonator'], $_SESSION['is_impersonating'], $_SESSION['super_admin_id']);
$_SESSION['user_id']     = $imp['user_id'];
$_SESSION['id']          = $imp['user_id'];
$_SESSION['account_id']  = $imp['account_id'];
$_SESSION['auth_source'] = $imp['auth_source'];
$_SESSION['username']    = $imp['username'];
$_SESSION['fullname']    = $imp['fullname'];
$_SESSION['role']        = 'super_admin';
$_SESSION['tenant_id']   = null;
$_SESSION['branch_id']   = $imp['branch_id'] ?? null;
$_SESSION['avatar']      = $imp['avatar'] ?? null;
$_SESSION['must_change_password'] = (int)($imp['must_change_password'] ?? 0);
Tenant::reset();

@session_regenerate_id(true);
unset($_SESSION['csrf_token']);

redirect(base_url('/superadmin/tenants.php'), 'success', 'Impersonation ended. You are back in the Super Admin console.');
