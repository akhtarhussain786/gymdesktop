<?php
/**
 * Gym Admin Add Member API
 * Handles Customer Registration with Live Camera/Gallery Photo, Package, and Partial Dues Calculation
 */

require_once __DIR__ . '/middleware.php';
require_once __DIR__ . '/../../core/subscription_engine.php';

$auth = AdminAuthMiddleware::authenticate();
$tenant = $auth['tenant'];
$tenantId = (int)$auth['tenant_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::error('Method not allowed', 405);
}

// 1. Quota Check
$quota = Tenant::checkLimit('members');
if (!$quota['allowed']) {
    ApiResponse::forbidden('Member limit reached for your active SaaS plan (' . $quota['current'] . '/' . $quota['max'] . '). Please upgrade your gym plan.');
}

// Accept both POST form-data (for files) and JSON
$input = $_POST;
if (empty($input)) {
    $input = get_json_input();
}

$fullname = trim($input['fullname'] ?? '');
$contact = trim($input['phone'] ?? $input['contact'] ?? '');
$address = trim($input['address'] ?? '');
$gender = trim($input['gender'] ?? 'Male');
$services = trim($input['services'] ?? 'General Fitness');
$planMonths = max(1, (int)($input['plan_months'] ?? $input['plan'] ?? 1));
$dor = !empty($input['dor']) ? trim($input['dor']) : date('Y-m-d');
$email = trim($input['email'] ?? '');

$totalAmount = (float)($input['total_amount'] ?? $input['amount'] ?? 0.0);
$paidAmount = (float)($input['paid_amount'] ?? $totalAmount);
$dueAmount = max(0.0, (float)($input['due_amount'] ?? ($totalAmount - $paidAmount)));
$dueDate = !empty($input['due_date']) ? trim($input['due_date']) : null;
$paymentMethod = trim($input['payment_method'] ?? 'Cash');

// Validation
$errors = [];
if (empty($fullname)) $errors['fullname'] = 'Full Name is required.';
if (empty($contact)) $errors['phone'] = 'Mobile Phone Number is required.';
if ($totalAmount < 0) $errors['total_amount'] = 'Total amount cannot be negative.';
if ($paidAmount < 0) $errors['paid_amount'] = 'Paid amount cannot be negative.';
if ($paidAmount > $totalAmount) $paidAmount = $totalAmount;

if (!empty($errors)) {
    ApiResponse::error('Validation failed', 422, $errors);
}

// Generate unique username
$cleanPhone = preg_replace('/[^0-9]/', '', $contact);
$baseUsername = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $fullname));
if (strlen($baseUsername) < 3) {
    $baseUsername = 'mem' . ($cleanPhone ?: rand(1000, 9999));
}
$username = $baseUsername;
$suffix = 1;
while (DB::fetchOne("SELECT user_id FROM members WHERE username = ?", [$username]) || DB::fetchOne("SELECT id FROM users WHERE username = ?", [$username])) {
    $username = $baseUsername . $suffix;
    $suffix++;
}

$password = trim($input['password'] ?? '123456');
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

// 2. Handle Photo Upload
$avatarFilename = null;
if (!empty($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    $tmpName = $_FILES['photo']['tmp_name'];
    $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
        $avatarFilename = 'avatar_' . $tenantId . '_' . time() . '_' . rand(100, 999) . '.' . $ext;
        $uploadDir = __DIR__ . '/../../uploads/avatars/';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0777, true);
        }
        @move_uploaded_file($tmpName, $uploadDir . $avatarFilename);
    }
}

DB::beginTransaction();
try {
    // 3. Insert Member
    $memberId = DB::insert('members', [
        'tenant_id' => $tenantId,
        'branch_id' => 101,
        'fullname' => $fullname,
        'username' => $username,
        'password' => $passwordHash,
        'gender' => $gender,
        'dor' => $dor,
        'services' => $services,
        'amount' => $totalAmount,
        'due_amount' => $dueAmount,
        'due_date' => $dueAmount > 0 ? $dueDate : null,
        'paid_date' => $dor,
        'p_year' => (int)date('Y', strtotime($dor)),
        'plan' => $planMonths,
        'address' => $address,
        'contact' => $contact,
        'email' => $email ?: ($username . '@gym.com'),
        'avatar' => $avatarFilename,
        'status' => 'Active',
        'attendance_count' => 0
    ]);

    if (!$memberId) {
        throw new RuntimeException('Failed to create member record.');
    }

    // 4. Sync User account for Member login
    DB::insert('users', [
        'tenant_id' => $tenantId,
        'branch_id' => 101,
        'member_id' => $memberId,
        'username' => $username,
        'password' => $passwordHash,
        'fullname' => $fullname,
        'email' => $email ?: ($username . '@gym.com'),
        'phone' => $contact,
        'avatar' => $avatarFilename,
        'role' => 'member',
        'status' => 'active'
    ]);

    // 5. Generate Tenant-aware Invoice
    $invCount = (int)DB::fetchValue("SELECT COUNT(*) FROM invoices WHERE tenant_id = ?", [$tenantId]);
    $tenantSlug = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $tenant['slug'] ?? $tenant['gym_name'] ?? 'GYM'), 0, 4));
    $invoiceNumber = 'INV-' . $tenantSlug . '-' . date('Ym') . '-' . str_pad($invCount + 1, 4, '0', STR_PAD_LEFT);

    $invoiceStatus = ($dueAmount <= 0) ? 'Paid' : ($paidAmount > 0 ? 'Partial' : 'Unpaid');

    $invId = DB::insert('invoices', [
        'tenant_id' => $tenantId,
        'branch_id' => 101,
        'member_id' => $memberId,
        'invoice_number' => $invoiceNumber,
        'service_name' => $services,
        'plan_months' => $planMonths,
        'amount' => $totalAmount,
        'paid_amount' => $paidAmount,
        'discount' => 0.00,
        'due_date' => $dueAmount > 0 ? $dueDate : null,
        'status' => $invoiceStatus,
        'payment_method' => $paymentMethod,
        'payment_date' => $dor,
        'transaction_ref' => strtoupper($paymentMethod) . '-' . strtoupper(substr(md5(uniqid()), 0, 8)),
        'notes' => "Registration for {$services} ({$planMonths} Mo). Paid: " . format_currency($paidAmount) . ($dueAmount > 0 ? ", Due: " . format_currency($dueAmount) : ""),
        'created_by' => $auth['user_id'] ?? null,
        'created_at' => date('Y-m-d H:i:s')
    ]);

    // 6. Create initial active subscription record
    $expiryDate = date('Y-m-d', strtotime("+$planMonths months", strtotime($dor)));
    DB::insert('member_subscriptions', [
        'tenant_id' => $tenantId,
        'member_id' => $memberId,
        'plan_name_snapshot' => $services,
        'plan_price_snapshot' => $totalAmount,
        'plan_duration_snapshot' => $planMonths,
        'start_date' => $dor,
        'expiry_date' => $expiryDate,
        'status' => 'active',
        'payment_id' => $invId,
        'queue_position' => 1,
        'activated_at' => date('Y-m-d H:i:s'),
        'created_at' => date('Y-m-d H:i:s')
    ]);

    DB::commit();

    $avatarUrl = $avatarFilename ? base_url('/uploads/avatars/' . $avatarFilename) : null;

    ApiResponse::success([
        'member_id' => $memberId,
        'fullname' => $fullname,
        'username' => $username,
        'phone' => $contact,
        'avatar' => $avatarUrl,
        'services' => $services,
        'plan_months' => $planMonths,
        'total_amount' => $totalAmount,
        'paid_amount' => $paidAmount,
        'due_amount' => $dueAmount,
        'due_date' => $dueDate,
        'invoice_number' => $invoiceNumber,
        'invoice_id' => $invId,
        'expiry_date' => $expiryDate
    ], 'Member created successfully with photo and payment details!', 201);

} catch (Throwable $e) {
    DB::rollback();
    error_log("Add Member API Error: " . $e->getMessage());
    ApiResponse::error('Failed to create member: ' . $e->getMessage(), 500);
}
