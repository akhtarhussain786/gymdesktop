<?php
require_once __DIR__ . '/../../core/auth.php';
Auth::requireAuth(['gym_admin', 'staff']);
$tenantId = Tenant::getTenantId();
$count = (int)DB::fetchValue("SELECT COUNT(*) FROM staffs WHERE tenant_id = ?", [$tenantId]);
echo $count;