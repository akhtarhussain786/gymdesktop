<?php
/**
 * Gym Admin Staff & Trainers API
 * List, Add, Edit, and Delete Staff & Trainers matching admin/staffs.php
 */

require_once __DIR__ . '/middleware.php';

$auth = AdminAuthMiddleware::authenticate();
$tenant = $auth['tenant'];
$tenantId = (int)$auth['tenant_id'];

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // List all staff and trainers
    $staffs = DB::fetchAll(
        "SELECT user_id, fullname, username, email, contact, designation, gender, role, status, address
         FROM staffs 
         WHERE tenant_id = ? 
         ORDER BY user_id DESC",
        [$tenantId]
    );

    ApiResponse::success(['staffs' => $staffs], 'Staff members retrieved successfully');
}

if ($method === 'POST') {
    $input = get_json_input();
    if (empty($input)) $input = $_POST;

    $action = trim($input['action'] ?? 'create');

    if ($action === 'delete') {
        $deleteId = (int)($input['user_id'] ?? $input['id'] ?? 0);
        if ($deleteId <= 0) {
            ApiResponse::error('Staff ID is required', 400);
        }

        DB::query("UPDATE members SET trainer_id = NULL WHERE trainer_id = ? AND tenant_id = ?", [$deleteId, $tenantId]);
        DB::delete('users', 'staff_id = ? AND tenant_id = ?', [$deleteId, $tenantId]);
        DB::delete('staffs', 'user_id = ? AND tenant_id = ?', [$deleteId, $tenantId]);

        ApiResponse::success(null, 'Staff member deleted successfully');
    }

    // Create or Edit Staff
    $fullname = trim($input['fullname'] ?? '');
    $username = trim($input['username'] ?? '');
    $password = trim($input['password'] ?? '123456');
    $email = trim($input['email'] ?? '');
    $contact = trim($input['contact'] ?? $input['phone'] ?? '');
    $address = trim($input['address'] ?? '');
    $designation = trim($input['designation'] ?? 'Trainer');
    $gender = trim($input['gender'] ?? 'Male');
    $role = strtolower(trim($input['role'] ?? 'trainer'));
    if (!in_array($role, ['gym_admin', 'staff', 'trainer'])) {
        $role = 'trainer';
    }

    if (empty($fullname) || empty($username)) {
        ApiResponse::error('Full Name and Username are required.', 422);
    }

    $editId = (int)($input['user_id'] ?? $input['id'] ?? 0);

    if ($editId > 0) {
        // Update existing staff
        $updateData = [
            'fullname' => $fullname,
            'email' => $email,
            'contact' => $contact,
            'address' => $address,
            'designation' => $designation,
            'gender' => $gender,
            'role' => $role
        ];
        if (!empty($password) && $password !== '••••••') {
            $updateData['password'] = password_hash($password, PASSWORD_DEFAULT);
        }

        DB::update('staffs', $updateData, 'user_id = ? AND tenant_id = ?', [$editId, $tenantId]);

        // Update corresponding users table row
        $userUpdate = [
            'fullname' => $fullname,
            'email' => $email ?: null,
            'phone' => $contact ?: null,
            'role' => $role
        ];
        if (!empty($password) && $password !== '••••••') {
            $userUpdate['password'] = password_hash($password, PASSWORD_DEFAULT);
        }
        DB::update('users', $userUpdate, 'staff_id = ? AND tenant_id = ?', [$editId, $tenantId]);

        ApiResponse::success(['user_id' => $editId], 'Staff member updated successfully');
    } else {
        // Create new staff - check quota
        $quota = Tenant::checkLimit('staff');
        if (!$quota['allowed']) {
            ApiResponse::forbidden('Staff limit reached for your plan (' . $quota['current'] . '/' . $quota['max'] . '). Please upgrade.');
        }

        // Check username duplicate
        if (DB::fetchValue("SELECT 1 FROM staffs WHERE username = ? LIMIT 1", [$username]) || DB::fetchValue("SELECT 1 FROM users WHERE username = ? LIMIT 1", [$username])) {
            ApiResponse::error("Username '$username' is already taken. Please choose another.", 422);
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $staffId = DB::insert('staffs', [
            'tenant_id' => $tenantId,
            'branch_id' => (int)($tenant['branch_id'] ?? 1),
            'fullname' => $fullname,
            'username' => $username,
            'password' => $passwordHash,
            'email' => $email,
            'contact' => $contact,
            'address' => $address,
            'designation' => $designation,
            'gender' => $gender,
            'role' => $role,
            'status' => 'active'
        ]);

        if (!$staffId) {
            ApiResponse::error('Failed to create staff member in database.', 500);
        }

        // Create unified user login
        DB::insert('users', [
            'tenant_id' => $tenantId,
            'branch_id' => (int)($tenant['branch_id'] ?? 1),
            'staff_id' => $staffId,
            'username' => $username,
            'password' => $passwordHash,
            'fullname' => $fullname,
            'email' => $email ?: null,
            'phone' => $contact ?: null,
            'role' => $role,
            'status' => 'active'
        ]);

        ApiResponse::success(['user_id' => $staffId], 'Staff member added successfully!', 201);
    }
}
