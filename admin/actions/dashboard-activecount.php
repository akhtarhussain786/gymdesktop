<?php
require_once __DIR__ . '/../../core/auth.php';
Auth::requireAuth(['gym_admin', 'staff']);
$tenantId = Tenant::getTenantId();
$count = (int)DB::fetchValue("SELECT COUNT(*) FROM members WHERE tenant_id = ? AND status = 'Active' AND DATE_ADD(paid_date, INTERVAL GREATEST(1, CAST(plan AS UNSIGNED)) MONTH) >= CURDATE()", [$tenantId]);
echo $count;