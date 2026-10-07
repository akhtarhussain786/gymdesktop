<?php
/**
 * Member Membership Details, Upcoming Subscriptions Queue & Renewal History Endpoint
 */

require_once __DIR__ . '/middleware.php';
require_once __DIR__ . '/../../core/subscription_engine.php';

$auth = MemberAuthMiddleware::authenticate();
$tenant = $auth['tenant'];
$member = $auth['member'];
$tenantId = $auth['tenant_id'];
$memberId = $auth['member_id'];

// Request-Time Auto-Activation Engine (Fallback check)
SubscriptionEngine::activateUpcomingSubscriptions($tenantId);

// Refresh member record after potential auto-activation
$member = DB::fetchOne("SELECT * FROM members WHERE user_id = ? AND tenant_id = ?", [$memberId, $tenantId]);

$paidDate = $member['paid_date'];
$planMonths = (int)($member['plan'] ?: 1);
// paid_date is the start of the current period; month-end safe expiry
$expiryDate = SubscriptionEngine::addMonths($paidDate, $planMonths);
$today = date('Y-m-d');
$daysLeft = (int)(new DateTime($today))->diff(new DateTime($expiryDate))->format('%r%a');

// Fetch Upcoming Plans Queue from member_subscriptions
$upcomingRows = DB::fetchAll(
    "SELECT id, plan_name_snapshot, plan_price_snapshot, plan_duration_snapshot, start_date, expiry_date, queue_position, created_at
     FROM member_subscriptions
     WHERE tenant_id = ? AND member_id = ? AND status = 'upcoming'
     ORDER BY start_date ASC, queue_position ASC",
    [$tenantId, $memberId]
);

$upcomingPlans = array_map(function($up, $index) use ($tenant) {
    return [
        'id' => (int)$up['id'],
        'plan_name' => $up['plan_name_snapshot'],
        'amount' => (float)$up['plan_price_snapshot'],
        'duration_months' => (int)$up['plan_duration_snapshot'],
        'start_date' => $up['start_date'],
        'expiry_date' => $up['expiry_date'],
        'queue_position' => (int)($up['queue_position'] ?: ($index + 1)),
        'status' => 'Upcoming',
        'currency' => $tenant['currency'] ?: '₹',
        'scheduled_notice' => "Your new plan has been successfully scheduled and will activate automatically on {$up['start_date']} after your current plan expires."
    ];
}, $upcomingRows, array_keys($upcomingRows));

// Invoices / Renewal records
$invoices = DB::fetchAll(
    "SELECT id, invoice_number, service_name, total_amount as amount, paid_amount, discount, plan_months, payment_method, payment_date, status, transaction_ref 
     FROM invoices 
     WHERE tenant_id = ? AND member_id = ? 
     ORDER BY payment_date DESC, id DESC",
    [$tenantId, $memberId]
);

// Standard Gym Benefits based on service
$serviceName = $member['services'] ?: 'General Fitness';
$benefits = [
    'Unlimited gym floor & cardio machines access',
    'Locker room & shower amenities',
    'Personal fitness assessment & progress tracking',
    'Standard diet consultation & routine updates',
    'Access to certified gym trainers during training hours'
];

if (stripos($serviceName, 'vip') !== false || stripos($serviceName, 'gold') !== false || stripos($serviceName, 'platinum') !== false) {
    $benefits[] = 'Priority personal trainer slot scheduling';
    $benefits[] = 'Access to sauna, steam & recovery suites';
    $benefits[] = 'Free guest pass (1 per month)';
}

$response = [
    'current_plan' => [
        'plan_name' => $serviceName,
        'duration_months' => $planMonths,
        'total_fee' => (float)$member['amount'],
        'start_date' => $paidDate,
        'expiry_date' => $expiryDate,
        'days_remaining' => max(0, $daysLeft),
        'status' => ($daysLeft >= 0) ? 'Active' : 'Expired',
        'is_expiring_soon' => ($daysLeft >= 0 && $daysLeft <= 7),
        'renewal_due' => ($daysLeft <= 5),
        'benefits' => $benefits
    ],
    'upcoming_plans' => $upcomingPlans,
    'has_upcoming_plans' => !empty($upcomingPlans),
    'renewal_history' => array_map(function($inv) {
        return [
            'invoice_id' => (int)$inv['id'],
            'invoice_number' => $inv['invoice_number'],
            'service_name' => $inv['service_name'],
            'paid_amount' => (float)$inv['paid_amount'],
            'plan_months' => (int)$inv['plan_months'],
            'payment_date' => $inv['payment_date'],
            'payment_method' => $inv['payment_method'],
            'status' => ucfirst(strtolower($inv['status'])),
            'awaiting_verification' => (strtolower($inv['status']) === 'pending'),
            'transaction_ref' => $inv['transaction_ref']
        ];
    }, $invoices),
    'payment_qr' => [
        'upi_id' => !empty($tenant['upi_id']) ? $tenant['upi_id'] : '',
        'gym_name' => $tenant['gym_name'] ?: 'Gym Owner',
        'currency' => $tenant['currency'] ?: '₹',
        'custom_qr_url' => !empty($tenant['upi_qr']) ? base_url("/uploads/qr/" . $tenant['upi_qr']) : null
    ],
    'gym_support' => [
        'phone' => $tenant['phone'] ?: '',
        'email' => $tenant['email'] ?: '',
        'contact_message' => 'To upgrade or renew your membership, pay directly via QR Code or contact reception desk at ' . ($tenant['phone'] ?: $tenant['email'])
    ]
];

ApiResponse::success($response, 'Membership details retrieved.');
