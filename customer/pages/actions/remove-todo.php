<?php
require_once __DIR__ . '/../../../core/auth.php';
require_once __DIR__ . '/../../../core/helpers.php';
Auth::requireAuth('member');

// State-changing endpoint: POST + CSRF only (was a GET link, CSRF-able).
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method Not Allowed');
}
Auth::verifyCsrf();

$memberId = (int)$_SESSION['user_id'];
$tenantId = Tenant::getTenantId();
$id = (int)($_POST['id'] ?? 0);

if ($id > 0) {
    DB::delete('todo', 'id = ? AND user_id = ? AND tenant_id = ?', [$id, $memberId, $tenantId]);
    set_flash('success', 'To-Do item removed.');
}

redirect(base_url('/customer/pages/to-do.php'));