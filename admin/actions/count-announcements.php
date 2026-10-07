<?php
require_once __DIR__ . '/../../core/auth.php';
Auth::requireAuth(['gym_admin', 'staff']);
$tenantId = Tenant::getTenantId();
$count = (int)DB::fetchValue("SELECT COUNT(*) FROM announcements WHERE tenant_id = ?", [$tenantId]);
echo $count;