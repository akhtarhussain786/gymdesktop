<?php
require_once __DIR__ . '/../../core/auth.php';
Auth::requireAuth(['gym_admin', 'staff']);
require_once __DIR__ . '/../../core/helpers.php';
$tenantId = Tenant::getTenantId();
$sum = (float)DB::fetchValue("SELECT SUM(amount * quantity) FROM equipment WHERE tenant_id = ?", [$tenantId]);
echo format_currency($sum);