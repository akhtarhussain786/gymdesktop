<?php
require_once __DIR__ . '/../core/auth.php';
Auth::requireAuth('super_admin');

// State-changing: POST + CSRF only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(base_url('/superadmin/tenants.php'), 'error', 'Invalid request.');
}
Auth::verifyCsrf();

if (!empty($_SESSION['impersonator'])) {
    redirect(base_url('/admin/index.php'), 'error', 'You are already impersonating a gym. Stop impersonating first.');
}

$tenantId = (int)($_POST['tenant_id'] ?? 0);
if ($tenantId > 0) {
    $tenant = DB::fetchOne("SELECT * FROM tenants WHERE id = ?", [$tenantId]);
    if ($tenant) {
        $branchId = DB::fetchValue("SELECT id FROM branches WHERE tenant_id = ? ORDER BY is_main DESC, id ASC LIMIT 1", [$tenantId]);

        // Store original super admin identity so it can be restored exactly
        $_SESSION['impersonator'] = [
            'user_id'     => $_SESSION['user_id'],
            'account_id'  => $_SESSION['account_id'] ?? $_SESSION['user_id'],
            'auth_source' => $_SESSION['auth_source'] ?? 'users',
            'username'    => $_SESSION['username'] ?? '',
            'fullname'    => $_SESSION['fullname'] ?? '',
            'role'        => 'super_admin',
            'tenant_id'   => $_SESSION['tenant_id'] ?? null,
            'branch_id'   => $_SESSION['branch_id'] ?? null,
            'avatar'      => $_SESSION['avatar'] ?? null,
            'must_change_password' => (int)($_SESSION['must_change_password'] ?? 0),
            'started_at'  => time()
        ];
        $_SESSION['is_impersonating'] = true; // legacy flag read by admin/index.php
        $_SESSION['super_admin_id'] = $_SESSION['user_id'];
        $_SESSION['tenant_id'] = $tenantId;
        $_SESSION['branch_id'] = $branchId ? (int)$branchId : null;
        $_SESSION['role'] = 'gym_admin';
        $_SESSION['must_change_password'] = 0;
        Tenant::reset();

        @session_regenerate_id(true);
        unset($_SESSION['csrf_token']);

        Auth::auditLog('IMPERSONATE', 'Super Admin started impersonating tenant ' . $tenant['gym_name'] . ' (#' . $tenantId . ')', $tenantId);
        redirect(base_url('/admin/index.php'), 'info', 'You are now viewing ' . $tenant['gym_name'] . ' in administrator mode.');
    }
}

redirect(base_url('/superadmin/tenants.php'), 'error', 'Invalid tenant selected.');
