<?php
require_once __DIR__ . '/../../../core/auth.php';
require_once __DIR__ . '/../../../core/helpers.php';
Auth::requireAuth('member');

$memberId = (int)$_SESSION['user_id'];
$tenantId = Tenant::getTenantId();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['task_desc'])) {
    Auth::verifyCsrf();
    $task_desc = trim($_POST['task_desc'] ?? '');
    $task_status = (trim($_POST['task_status'] ?? '') === 'Completed') ? 'Completed' : 'Pending';
    $id = (int)($_POST['id'] ?? 0);

    if ($id > 0 && !empty($task_desc)) {
        DB::update('todo', [
            'task_desc' => $task_desc,
            'task_status' => $task_status
        ], 'id = ? AND user_id = ? AND tenant_id = ?', [$id, $memberId, $tenantId]);
        set_flash('success', 'To-Do item updated successfully.');
    }
}

redirect(base_url('/customer/pages/to-do.php'));