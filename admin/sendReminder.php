<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helpers.php';
Auth::requireAuth(['gym_admin', 'staff']);

// State-changing endpoint: POST + CSRF only (was a GET link, CSRF-able).
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method Not Allowed');
}
Auth::verifyCsrf();

$tenantId = Tenant::getTenantId();
$id = (int)($_POST['id'] ?? 0);

if ($id > 0) {
    // Only members of this gym whose plan ends within 7 days (or has ended) and who have not
    // already been flagged get a reminder - avoids nagging paid-up members / duplicate reminders.
    $affected = DB::update('members', ['reminder' => 1],
        "user_id = ? AND tenant_id = ? AND COALESCE(reminder, 0) = 0
         AND (paid_date IS NULL OR DATE_ADD(paid_date, INTERVAL GREATEST(1, CAST(plan AS UNSIGNED)) MONTH) <= DATE_ADD(CURDATE(), INTERVAL 7 DAY))",
        [$id, $tenantId]);
    if ($affected) {
        set_flash('success', 'Fee reminder notification sent to member!');
    } else {
        set_flash('info', 'No reminder sent: member not found, not yet due, or already reminded.');
    }
}

redirect(base_url('/admin/payment.php'));