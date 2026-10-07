<?php
require_once __DIR__ . '/../../core/auth.php';
Auth::requireAuth(['gym_admin', 'staff', 'trainer']);
$tenantId = Tenant::getTenantId();
$id = (int)($_GET['id'] ?? 0);

$member = DB::fetchOne("SELECT ini_weight, curr_weight FROM members WHERE user_id = ? AND tenant_id = ?", [$id, $tenantId]);
if ($member) {
    $diff = (float)$member['curr_weight'] - (float)$member['ini_weight'];
    echo round($diff, 2);
} else {
    echo 0;
}