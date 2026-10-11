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
    // 'id' / 'phone' are the names the app model prefers
    foreach ($staffs as &$st) {
        $st['id'] = (int)$st['user_id'];
        $st['phone'] = $st['contact'] ?? '';
    }
    unset($st);

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

        $staff = DB::fetchOne("SELECT user_id, username FROM staffs WHERE user_id = ? AND tenant_id = ?", [$deleteId, $tenantId]);
        if (!$staff) {
            ApiResponse::notFound('Staff member not found in this gym.');
        }

        // Every login row of this staff member: linked by users.staff_id, or (older rows) by username
        $loginIds = staffs_linked_user_ids($tenantId, $deleteId, $staff['username']);
        if (in_array((int)$auth['user_id'], $loginIds, true)) {
            ApiResponse::error('You cannot delete your own account.', 422);
        }

        DB::query("UPDATE members SET trainer_id = NULL WHERE trainer_id = ? AND tenant_id = ?", [$deleteId, $tenantId]);
        foreach ($loginIds as $uid) {
            Auth::revokeApiTokens($tenantId, $uid);
            DB::delete('users', "id = ? AND tenant_id = ? AND role <> 'super_admin'", [$uid, $tenantId]);
        }
        DB::delete('staffs', 'user_id = ? AND tenant_id = ?', [$deleteId, $tenantId]);

        Auth::auditLog('DELETE_STAFF', "Deleted staff member ID $deleteId", $tenantId, (int)$auth['user_id']);
        ApiResponse::success(null, 'Staff member deleted successfully');
    }

    $editId = (int)($input['user_id'] ?? $input['id'] ?? 0);

    // Masked placeholder some clients send back unchanged; never a real new password
    $password = trim((string)($input['password'] ?? ''));
    if ($password === '••••••') {
        $password = '';
    }

    if ($editId > 0) {
        // Update existing staff: only the fields that were sent (a partial edit must not reset the rest)
        $staff = DB::fetchOne("SELECT * FROM staffs WHERE user_id = ? AND tenant_id = ?", [$editId, $tenantId]);
        if (!$staff) {
            ApiResponse::notFound('Staff member not found in this gym.');
        }

        $updateData = [];
        foreach (['fullname' => 'fullname', 'email' => 'email', 'address' => 'address', 'designation' => 'designation', 'gender' => 'gender'] as $key => $col) {
            if (array_key_exists($key, $input)) {
                $updateData[$col] = trim((string)$input[$key]);
            }
        }
        if (array_key_exists('contact', $input) || array_key_exists('phone', $input)) {
            $updateData['contact'] = trim((string)($input['contact'] ?? $input['phone'] ?? ''));
        }
        if (array_key_exists('fullname', $updateData) && $updateData['fullname'] === '') {
            ApiResponse::error('Full Name is required.', 422);
        }
        if ($password !== '' && strlen($password) < 6) {
            ApiResponse::error('Password must be at least 6 characters.', 422);
        }
        $designationChanged = isset($updateData['designation']) && strcasecmp($updateData['designation'], (string)$staff['designation']) !== 0;
        if (!empty($input['role']) || $designationChanged) {
            $updateData['role'] = staffs_resolve_role($input['role'] ?? null, $updateData['designation'] ?? $staff['designation']);
        }

        if (!empty($updateData)) {
            DB::update('staffs', $updateData, 'user_id = ? AND tenant_id = ?', [$editId, $tenantId]);
        }

        // Mirror onto the staff member's login row(s)
        $userUpdate = [];
        if (isset($updateData['fullname'])) $userUpdate['fullname'] = $updateData['fullname'];
        if (isset($updateData['email'])) $userUpdate['email'] = $updateData['email'] ?: null;
        if (isset($updateData['contact'])) $userUpdate['phone'] = $updateData['contact'] ?: null;
        if (isset($updateData['role'])) $userUpdate['role'] = $updateData['role'];
        $loginIds = staffs_linked_user_ids($tenantId, $editId, $staff['username']);
        if (!empty($userUpdate)) {
            foreach ($loginIds as $uid) {
                DB::update('users', $userUpdate, "id = ? AND tenant_id = ? AND role <> 'super_admin'", [$uid, $tenantId]);
            }
        }

        // The password only changes when a new one is supplied
        if ($password !== '') {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            DB::update('staffs', ['password' => $newHash], 'user_id = ? AND tenant_id = ?', [$editId, $tenantId]);
            Auth::syncLinkedPasswords('staffs', $editId, $tenantId, $newHash);
            foreach ($loginIds as $uid) {
                DB::update('users', ['password' => $newHash], "id = ? AND tenant_id = ? AND role <> 'super_admin'", [$uid, $tenantId]);
                Auth::revokeApiTokens($tenantId, $uid, null, (int)$auth['token_id']);
            }
        }

        Auth::auditLog('UPDATE_STAFF', "Updated staff member ID $editId", $tenantId, (int)$auth['user_id']);
        ApiResponse::success(['user_id' => $editId], 'Staff member updated successfully');
    }

    // Create new staff
    $fullname = trim((string)($input['fullname'] ?? ''));
    $username = trim((string)($input['username'] ?? ''));
    $email = trim((string)($input['email'] ?? ''));
    $contact = trim((string)($input['contact'] ?? $input['phone'] ?? ''));
    $address = trim((string)($input['address'] ?? ''));
    $designation = trim((string)($input['designation'] ?? 'Trainer')) ?: 'Trainer';
    $gender = trim((string)($input['gender'] ?? 'Male')) ?: 'Male';
    $role = staffs_resolve_role($input['role'] ?? null, $designation);

    if ($fullname === '') {
        ApiResponse::error('Full Name is required.', 422);
    }

    // Check quota
    $quota = Tenant::checkLimit('staff');
    if (!$quota['allowed']) {
        ApiResponse::forbidden('Staff limit reached for your plan (' . $quota['current'] . '/' . $quota['max'] . '). Please upgrade.');
    }

    // Login resolves usernames across all gyms, so they must be globally unique
    if ($username === '') {
        // The app never sends a username: derive one from the name
        $username = staffs_generate_username($fullname);
    } elseif (staffs_username_taken($username)) {
        ApiResponse::error("Username '$username' is already taken. Please choose another.", 422);
    }

    $passwordGenerated = false;
    if ($password === '') {
        $password = staffs_random_password();
        $passwordGenerated = true;
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    DB::beginTransaction();
    try {
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
            throw new RuntimeException('staffs insert failed: ' . DB::$lastError);
        }

        // Create unified user login
        $loginId = DB::insert('users', [
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

        if (!$loginId) {
            throw new RuntimeException('users insert failed: ' . DB::$lastError);
        }

        DB::commit();
    } catch (Throwable $e) {
        DB::rollback();
        error_log('Admin API add staff error: ' . $e->getMessage());
        ApiResponse::error('Failed to create staff member. Please try again.', 500);
    }

    Auth::auditLog('ADD_STAFF', "Added new staff $fullname ($designation)", $tenantId, (int)$auth['user_id']);

    $result = ['user_id' => $staffId, 'id' => $staffId, 'username' => $username, 'role' => $role];
    if ($passwordGenerated) {
        // Shown once to the owner so it can be handed to the staff member
        $result['password'] = $password;
        $result['temporary_password'] = true;
    }
    ApiResponse::success($result, 'Staff member added successfully!', 201);
}

ApiResponse::error('Method not allowed', 405);

/**
 * Role for a staff account: an explicit allowed role, otherwise derived from the designation
 */
function staffs_resolve_role($role, $designation) {
    $role = strtolower(trim((string)$role));
    if (in_array($role, ['gym_admin', 'staff', 'trainer'], true)) {
        return $role;
    }
    $d = strtolower(trim((string)$designation));
    if (in_array($d, ['gym admin', 'gym_admin', 'admin'], true)) {
        return 'gym_admin';
    }
    return str_contains($d, 'trainer') ? 'trainer' : 'staff';
}

function staffs_username_taken($username) {
    return (bool)(DB::fetchValue("SELECT 1 FROM staffs WHERE LOWER(username) = LOWER(?) LIMIT 1", [$username])
        || DB::fetchValue("SELECT 1 FROM users WHERE LOWER(username) = LOWER(?) LIMIT 1", [$username])
        || DB::fetchValue("SELECT 1 FROM members WHERE LOWER(username) = LOWER(?) LIMIT 1", [$username]));
}

function staffs_generate_username($fullname) {
    $base = substr(strtolower(preg_replace('/[^a-zA-Z0-9]/', '', (string)$fullname)), 0, 40);
    if (strlen($base) < 3) {
        $base = 'staff' . $base;
    }
    $candidate = $base;
    for ($i = 1; $i < 1000 && staffs_username_taken($candidate); $i++) {
        $candidate = $base . $i;
    }
    if (staffs_username_taken($candidate)) {
        $candidate = $base . bin2hex(random_bytes(3));
    }
    return $candidate;
}

function staffs_random_password($length = 10) {
    $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $out = '';
    for ($i = 0; $i < $length; $i++) {
        $out .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $out;
}

/**
 * users.id of the login row(s) belonging to a staffs row (never a super admin or a member login)
 */
function staffs_linked_user_ids($tenantId, $staffId, $username) {
    $ids = [];
    $userCols = DB::tableColumns('users');
    if ($userCols !== null && in_array('staff_id', $userCols, true)) {
        foreach (DB::fetchAll("SELECT id FROM users WHERE tenant_id = ? AND staff_id = ? AND role <> 'super_admin'", [(int)$tenantId, (int)$staffId]) as $r) {
            $ids[] = (int)$r['id'];
        }
        // Older logins created before users.staff_id existed are matched by username (staff roles only)
        if ((string)$username !== '') {
            foreach (DB::fetchAll("SELECT id FROM users WHERE tenant_id = ? AND username = ? AND staff_id IS NULL AND role IN ('staff', 'trainer')", [(int)$tenantId, (string)$username]) as $r) {
                $ids[] = (int)$r['id'];
            }
        }
    } elseif ((string)$username !== '') {
        foreach (DB::fetchAll("SELECT id FROM users WHERE tenant_id = ? AND username = ? AND role NOT IN ('super_admin', 'member')", [(int)$tenantId, (string)$username]) as $r) {
            $ids[] = (int)$r['id'];
        }
    }
    return array_values(array_unique($ids));
}
