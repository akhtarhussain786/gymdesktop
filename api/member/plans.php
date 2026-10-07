<?php
/**
 * Member Available Plans & Renewal Rates Endpoint
 */

require_once __DIR__ . '/middleware.php';
require_once __DIR__ . '/../../core/subscription_engine.php';

$auth = MemberAuthMiddleware::authenticate();
$tenant = $auth['tenant'];
$tenantId = $auth['tenant_id'];
$member = $auth['member'];

// Fetch available rates / plans for this tenant
$rates = Tenant::getRates($tenantId);
$rates = array_values(array_filter($rates, function($r) { return (float)$r['charge'] > 0; }));

// Duration packages with discount multipliers (same ladder the server charges)
$durationLabels = [
    1 => '1 Month',
    3 => '3 Months (Quarterly)',
    6 => '6 Months (Half-Yearly)',
    12 => '12 Months (Annual - Best Value)',
];
$durationPackages = [];
foreach (SubscriptionEngine::DURATION_DISCOUNTS as $m => $disc) {
    $durationPackages[] = ['months' => $m, 'label' => $durationLabels[$m] ?? ($m . ' Months'), 'discount_percent' => $disc];
}

$formattedPlans = [];
foreach ($rates as $r) {
    $baseCharge = (float)$r['charge'];
    $packages = [];
    foreach ($durationPackages as $d) {
        $m = $d['months'];
        $disc = $d['discount_percent'];
        $rawTotal = $baseCharge * $m;
        $finalTotal = round($rawTotal * (1 - ($disc / 100)), 2);
        
        $packages[] = [
            'months' => $m,
            'label' => $d['label'],
            'discount_percent' => $disc,
            'monthly_rate' => $baseCharge,
            'total_price' => $finalTotal
        ];
    }

    $formattedPlans[] = [
        'id' => (int)$r['id'],
        'name' => $r['name'],
        'monthly_charge' => $baseCharge,
        'packages' => $packages
    ];
}

$upiId = !empty($tenant['upi_id']) ? $tenant['upi_id'] : '';
$payeeName = !empty($tenant['gym_name']) ? $tenant['gym_name'] : 'Gym Owner';

$response = [
    'gym_info' => [
        'gym_name' => $payeeName,
        'currency' => $tenant['currency'] ?: '₹',
        'upi_id' => $upiId,
        'online_payments_enabled' => SubscriptionEngine::tenantUsesInr($tenant) && SubscriptionEngine::tenantCanCollectPayments($tenant)['allowed'],
        'custom_qr_url' => !empty($tenant['upi_qr']) ? base_url("/uploads/qr/" . $tenant['upi_qr']) : null
    ],
    'current_plan' => [
        'service_name' => $member['services'] ?: 'General Fitness',
        'amount' => (float)$member['amount'],
        'paid_date' => $member['paid_date']
    ],
    'plans' => $formattedPlans
];

ApiResponse::success($response, 'Available renewal plans retrieved.');
