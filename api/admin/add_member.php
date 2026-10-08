<?php
/**
 * Gym Admin Add Member API
 * Handles Customer Registration with Live Camera/Gallery Photo, Package, and Partial Dues Calculation
 */

require_once __DIR__ . '/middleware.php';

$auth = AdminAuthMiddleware::authenticate();
$tenant = $auth['tenant'];
$tenantId = (int)$auth['tenant_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::error('Method not allowed', 405);
}

// 0. Ensure schema compatibility for mobile admin operations
static $schemaChecked = false;
if (!$schemaChecked) {
    try {
        if (!api_column_exists('members', 'avatar')) {
            @DB::query("ALTER TABLE `members` ADD COLUMN `avatar` varchar(255) DEFAULT NULL");
        }
        if (!api_column_exists('members', 'due_amount')) {
            @DB::query("ALTER TABLE `members` ADD COLUMN `due_amount` decimal(10,2) NOT NULL DEFAULT 0.00");
        }
        if (!api_column_exists('members', 'due_date')) {
            @DB::query("ALTER TABLE `members` ADD COLUMN `due_date` date DEFAULT NULL");
        }
        if (!api_column_exists('members', 'notes')) {
            @DB::query("ALTER TABLE `members` ADD COLUMN `notes` text DEFAULT NULL");
        }
        if (!api_column_exists('invoices', 'paid_amount')) {
            @DB::query("ALTER TABLE `invoices` ADD COLUMN `paid_amount` decimal(10,2) NOT NULL DEFAULT 0.00");
        }
        if (!api_column_exists('invoices', 'due_date')) {
            @DB::query("ALTER TABLE `invoices` ADD COLUMN `due_date` date DEFAULT NULL");
        }
        if (!api_column_exists('users', 'avatar')) {
            @DB::query("ALTER TABLE `users` ADD COLUMN `avatar` varchar(255) DEFAULT NULL");
        }
    } catch (Throwable $e) {
        error_log("Schema auto-alter ignored: " . $e->getMessage());
    }
    $schemaChecked = true;
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

// Generate or use custom username
$cleanPhone = preg_replace('/[^0-9]/', '', $contact);
$customUsername = trim($input['username'] ?? '');

if (!empty($customUsername)) {
    $username = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $customUsername));
    $exists = DB::fetchValue("SELECT 1 FROM members WHERE username = ? LIMIT 1", [$username]) 
           || DB::fetchValue("SELECT 1 FROM users WHERE username = ? LIMIT 1", [$username]);
    if ($exists) {
        $username = $username . rand(10, 99);
    }
} else {
    $baseUsername = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $fullname));
    if (strlen($baseUsername) < 3) {
        $baseUsername = 'mem' . ($cleanPhone ?: rand(1000, 9999));
    }
    $username = $baseUsername;
    $suffix = 1;
    while (DB::fetchValue("SELECT 1 FROM members WHERE username = ? LIMIT 1", [$username]) || DB::fetchValue("SELECT 1 FROM users WHERE username = ? LIMIT 1", [$username])) {
        $username = $baseUsername . $suffix;
        $suffix++;
    }
}

$password = !empty($input['password']) ? trim($input['password']) : '123456';
$passwordHash = password_hash($password, PASSWORD_DEFAULT);


// 2. Handle Photo Upload (Multipart or Base64)
$avatarFilename = null;
if (!empty($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    $tmpName = $_FILES['photo']['tmp_name'];
    $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
        $avatarFilename = 'avatar_' . $tenantId . '_' . time() . '_' . rand(100, 999) . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
        $uploadDir = __DIR__ . '/../../uploads/avatars/';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0777, true);
        }
        @move_uploaded_file($tmpName, $uploadDir . $avatarFilename);
    }
} elseif (!empty($input['photo_base64']) || !empty($input['photo'])) {
    $b64 = trim($input['photo_base64'] ?? $input['photo'] ?? '');
    if (str_starts_with($b64, 'data:image')) {
        $b64 = substr($b64, strpos($b64, ',') + 1);
    }
    if (!empty($b64) && !str_starts_with($b64, 'avatar_') && !str_starts_with($b64, 'http')) {
        $decoded = base64_decode($b64);
        if ($decoded !== false && strlen($decoded) > 50) {
            $avatarFilename = 'avatar_' . $tenantId . '_' . time() . '_' . rand(100, 999) . '.jpg';
            $uploadDir = __DIR__ . '/../../uploads/avatars/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0777, true);
            }
            @file_put_contents($uploadDir . $avatarFilename, $decoded);
        }
    }
}

// Fetch valid branch_id for this tenant
$branchId = (int)($tenant['branch_id'] ?? 1);
if ($branchId <= 0) {
    $branchId = 1;
}

DB::beginTransaction();
try {
    // 3. Insert Member
    $memberCols = [
        'tenant_id' => $tenantId,
        'branch_id' => $branchId,
        'fullname' => $fullname,
        'username' => $username,
        'password' => $passwordHash,
        'gender' => $gender,
        'dor' => $dor,
        'services' => $services,
        'amount' => $totalAmount,
        'paid_date' => $dor,
        'p_year' => (int)date('Y', strtotime($dor)),
        'plan' => $planMonths,
        'address' => $address,
        'contact' => $contact,
        'email' => $email ?: ($username . '@gym.com'),
        'status' => 'Active',
        'attendance_count' => 0
    ];

    if (api_column_exists('members', 'avatar')) {
        $memberCols['avatar'] = $avatarFilename;
    }
    if (api_column_exists('members', 'photo')) {
        $memberCols['photo'] = $avatarFilename;
    }
    if (api_column_exists('members', 'due_amount')) {
        $memberCols['due_amount'] = $dueAmount;
    }
    if (api_column_exists('members', 'due_date')) {
        $memberCols['due_date'] = $dueAmount > 0 ? $dueDate : null;
    }

    $memberId = DB::insert('members', $memberCols);

    if (!$memberId) {
        throw new RuntimeException('Failed to create member record in database.');
    }

    // 4. Sync User account for Member login
    $userCols = [
        'tenant_id' => $tenantId,
        'branch_id' => $branchId,
        'member_id' => $memberId,
        'username' => $username,
        'password' => $passwordHash,
        'fullname' => $fullname,
        'email' => $email ?: ($username . '@gym.com'),
        'phone' => $contact,
        'role' => 'member',
        'status' => 'active'
    ];

    if (api_column_exists('users', 'avatar')) {
        $userCols['avatar'] = $avatarFilename;
    }
    if (api_column_exists('users', 'photo')) {
        $userCols['photo'] = $avatarFilename;
    }

    DB::insert('users', $userCols);

    // 5. Generate Tenant-aware Invoice
    $invCount = (int)DB::fetchValue("SELECT COUNT(*) FROM invoices WHERE tenant_id = ?", [$tenantId]);
    $tenantSlug = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $tenant['slug'] ?? $tenant['gym_name'] ?? 'GYM'), 0, 4));
    $invoiceNumber = 'INV-' . $tenantSlug . '-' . date('Ym') . '-' . str_pad($invCount + 1, 4, '0', STR_PAD_LEFT);

    $invoiceStatus = ($dueAmount <= 0) ? 'Paid' : ($paidAmount > 0 ? 'Partial' : 'Unpaid');
    $currency = !empty($tenant['currency']) ? $tenant['currency'] : '₹';

    $invCols = [
        'tenant_id' => $tenantId,
        'branch_id' => $branchId,
        'member_id' => $memberId,
        'invoice_number' => $invoiceNumber,
        'service_name' => $services,
        'plan_months' => $planMonths,
        'amount' => $totalAmount,
        'paid_amount' => $paidAmount,
        'discount' => 0.00,
        'status' => $invoiceStatus,
        'payment_method' => $paymentMethod,
        'payment_date' => $dor,
        'transaction_ref' => !empty($input['transaction_ref']) ? trim($input['transaction_ref']) : (!empty($input['utr']) ? trim($input['utr']) : (strtoupper($paymentMethod) . '-' . strtoupper(substr(md5(uniqid()), 0, 8)))),
        'notes' => !empty($input['notes']) ? trim($input['notes']) : ("Registration for {$services} ({$planMonths} Mo). Paid: {$currency}" . number_format($paidAmount, 2) . ($dueAmount > 0 ? ", Due: {$currency}" . number_format($dueAmount, 2) : "")),
        'created_by' => $auth['user_id'] ?? null,
        'created_at' => date('Y-m-d H:i:s')
    ];

    if (api_column_exists('invoices', 'due_date')) {
        $invCols['due_date'] = $dueAmount > 0 ? $dueDate : null;
    }

    $invId = DB::insert('invoices', $invCols);

    // 6. Create initial active subscription record if table exists
    $customExpiry = !empty($input['expiry_date']) ? trim($input['expiry_date']) : null;
    $expiryDate = (!empty($customExpiry) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $customExpiry))
        ? $customExpiry
        : date('Y-m-d', strtotime("+$planMonths months", strtotime($dor)));

    if (!empty($customExpiry) && $customExpiry !== date('Y-m-d', strtotime("+$planMonths months", strtotime($dor)))) {
        // Adjust paid_date on member so computed expiry matches
        $newPaidDate = date('Y-m-d', strtotime("-$planMonths months", strtotime($customExpiry)));
        DB::update('members', ['paid_date' => $newPaidDate], 'user_id = ? AND tenant_id = ?', [$memberId, $tenantId]);
    }

    if (api_table_exists('member_subscriptions')) {
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
    }

    DB::commit();

    $avatarUrl = $avatarFilename ? base_url('/uploads/avatars/' . $avatarFilename) : null;

    ApiResponse::success([
        'member_id' => $memberId,
        'fullname' => $fullname,
        'username' => $username,
        'password' => $password,
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
        'start_date' => $dor,
        'expiry_date' => $expiryDate
    ], 'Member created successfully with photo and credentials!', 201);

} catch (Throwable $e) {
    DB::rollback();
    error_log("Add Member API Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    ApiResponse::error('Failed to create member: ' . $e->getMessage(), 500);
}
