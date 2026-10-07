<?php
require_once __DIR__ . '/../../core/auth.php';
Auth::requireAuth(['gym_admin', 'staff']);
$tenantId = Tenant::getTenantId();
$count = (int)DB::fetchValue("SELECT COUNT(*) FROM attendance WHERE curr_date = ? AND tenant_id = ?", [date('Y-m-d'), $tenantId]);
echo $count;