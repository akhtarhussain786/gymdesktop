<?php
require_once __DIR__ . '/../../core/auth.php';
Auth::requireAuth(['gym_admin', 'staff', 'trainer']);
$tenantId = Tenant::getTenantId();
$id = (int)($_GET['id'] ?? 0);

$member = DB::fetchOne("SELECT ini_weight, curr_weight FROM members WHERE user_id = ? AND tenant_id = ?", [$id, $tenantId]);
if ($member && (float)$member['ini_weight'] > 0) {
    $diff = ((float)$member['curr_weight'] - (float)$member['ini_weight']) / (float)$member['ini_weight'] * 100;
    echo (int)round($diff);
} else {
    echo 0;
}