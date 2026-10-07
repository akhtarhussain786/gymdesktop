<?php
/**
 * Member Assigned Trainer Details Endpoint
 */

require_once __DIR__ . '/middleware.php';

$auth = MemberAuthMiddleware::authenticate();
$tenant = $auth['tenant'];
$member = $auth['member'];
$tenantId = $auth['tenant_id'];
$memberId = $auth['member_id'];

// Check assigned plan for trainer, or member's direct trainer_id
$assignedPlan = DB::fetchOne(
    "SELECT trainer_id, notes FROM member_assigned_plans WHERE member_id = ? AND tenant_id = ? AND status = 'active' AND trainer_id IS NOT NULL ORDER BY id DESC LIMIT 1",
    [$memberId, $tenantId]
);

$trainerId = $assignedPlan['trainer_id'] ?? $member['trainer_id'] ?? null;

$trainer = null;
if ($trainerId) {
    $staff = DB::fetchOne(
        "SELECT user_id, fullname, email, designation, gender, contact FROM staffs WHERE user_id = ? AND tenant_id = ? AND status = 'active'",
        [(int)$trainerId, $tenantId]
    );

    if ($staff) {
        $trainer = [
            'id' => (int)$staff['user_id'],
            'fullname' => $staff['fullname'],
            'designation' => $staff['designation'] ?: 'Certified Personal Trainer',
            'phone' => (string)$staff['contact'],
            'email' => $staff['email'],
            'gender' => $staff['gender'],
            'specializations' => ['Strength & Hypertrophy', 'Fat Loss & Conditioning', 'Functional Mobility'],
            'available_timings' => 'Monday - Saturday: 06:00 AM - 12:00 PM & 04:00 PM - 09:00 PM',
            'notes' => $assignedPlan['notes'] ?? 'Always perform warm-ups before lifting heavy weights.'
        ];
    }
}

// If no specific trainer, return gym general trainer desk
if (!$trainer) {
    // Check if there's any active trainer in this gym.
    // This trainer is NOT assigned to the member, so never expose their personal phone/email: route via gym desk.
    $generalTrainer = DB::fetchOne(
        "SELECT user_id, fullname, designation FROM staffs WHERE tenant_id = ? AND status = 'active' AND (LOWER(designation) = 'trainer' OR role = 'trainer') ORDER BY user_id ASC LIMIT 1",
        [$tenantId]
    );

    if ($generalTrainer) {
        $trainer = [
            'id' => (int)$generalTrainer['user_id'],
            'fullname' => $generalTrainer['fullname'],
            'designation' => 'Gym Floor Trainer',
            'phone' => (string)($tenant['phone'] ?? ''),
            'email' => (string)($tenant['email'] ?? ''),
            'gender' => 'Certified Staff',
            'specializations' => ['General Fitness Consultation', 'Gym Floor Assistance'],
            'available_timings' => 'Gym Working Hours',
            'notes' => 'Available on the gym floor for equipment guidance and spot assistance.'
        ];
    }
}

$response = [
    'has_trainer' => ($trainer !== null),
    'trainer' => $trainer,
    'gym_contact' => [
        'phone' => $tenant['phone'],
        'email' => $tenant['email']
    ]
];

ApiResponse::success($response, 'Trainer details retrieved.');
