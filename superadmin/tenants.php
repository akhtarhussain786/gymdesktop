<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/currencies.php';
Auth::requireAuth('super_admin');

$page = 'super_tenants';
$pageTitle = 'Gym Tenants Management';
$pageSubtitle = 'Manage gym businesses, subscriptions, limits, and statuses';

// Handle Actions (Create, Update, Suspend, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();

    if (isset($_POST['create_tenant'])) {
        $gym_name = trim($_POST['gym_name'] ?? '');
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $gym_name)));
        $owner_name = trim($_POST['owner_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $currency = trim($_POST['currency'] ?? '₹');
        $timezone = trim($_POST['timezone'] ?? 'Asia/Kolkata');
        $plan_id = (int)($_POST['subscription_plan_id'] ?? 2);
        $duration_months = max(1, min(120, (int)($_POST['duration_months'] ?? 1)));
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($gym_name) || empty($owner_name) || empty($username) || empty($password)) {
            redirect('tenants.php', 'error', 'Please fill in all required fields.');
        }

        // Check if username exists
        $userCheck = DB::fetchOne("SELECT id FROM users WHERE username = ?", [$username]);
        if ($userCheck) {
            redirect('tenants.php', 'error', "Username '$username' is already in use. Please choose another.");
        }

        // Check slug uniqueness
        $slugCheck = DB::fetchOne("SELECT id FROM tenants WHERE slug = ?", [$slug]);
        if ($slugCheck) {
            $slug = $slug . '-' . rand(100, 999);
        }

        // Generate unique gym code for member app
        $baseCode = 'GYM-' . strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $gym_name), 0, 4));
        if (strlen($baseCode) < 6) {
            $baseCode = 'GYM-' . strtoupper(substr(uniqid(), -4));
        }
        $gymCode = $baseCode;
        $codeCheck = DB::fetchOne("SELECT id FROM tenants WHERE gym_code = ?", [$gymCode]);
        if ($codeCheck) {
            $gymCode = 'GYM-' . strtoupper(substr(md5(uniqid()), 0, 5));
        }

        $expiry = date('Y-m-d 23:59:59', strtotime("+$duration_months months"));

        // Robust Logo Upload Handling for Create
        $logoFileName = null;
        if (!empty($_FILES['logo']['name'])) {
            if ($_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
                $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif']; // SVG excluded: can carry script (stored XSS)
                if (in_array($ext, $allowedExts)) {
                    $logoDir = __DIR__ . '/../uploads/logos';
                    if (!is_dir($logoDir)) {
                        @mkdir($logoDir, 0777, true);
                    }
                    $logoFileName = 'logo_' . time() . '_' . rand(100, 999) . '.' . $ext;
                    @move_uploaded_file($_FILES['logo']['tmp_name'], $logoDir . '/' . $logoFileName);
                }
            }
        }

        $tenantId = DB::insert('tenants', [
            'gym_code' => $gymCode,
            'gym_name' => $gym_name,
            'name' => $gym_name,
            'slug' => $slug,
            'logo' => $logoFileName,
            'owner_name' => $owner_name,
            'email' => $email,
            'phone' => $phone,
            'address' => $address,
            'currency' => $currency,
            'timezone' => $timezone,
            'subscription_plan_id' => $plan_id,
            'subscription_start' => date('Y-m-d'),
            'subscription_expiry' => $expiry,
            'status' => 'active',
            'primary_color' => '#3b82f6',
            'secondary_color' => '#10b981'
        ]);

        if ($tenantId) {
            $branchId = DB::insert('branches', [
                'tenant_id' => $tenantId,
                'branch_name' => $gym_name . ' - Main Branch',
                'address' => $address,
                'phone' => $phone,
                'email' => $email,
                'is_main' => 1,
                'status' => 'active'
            ]);

            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            DB::insert('users', [
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'role' => 'gym_admin',
                'username' => $username,
                'password' => $passwordHash,
                'email' => $email,
                'fullname' => $owner_name,
                'phone' => $phone,
                'status' => 'active'
            ]);

            Tenant::ensureDefaultRates($tenantId);

            Auth::auditLog('SUPER_CREATE_TENANT', "Created new gym tenant: $gym_name (ID: $tenantId)");
            redirect('tenants.php', 'success', "New gym '$gym_name' created successfully with admin username '$username'.");
        } else {
            $errDetail = DB::$lastError ? ' Details: ' . DB::$lastError : '';
            redirect('tenants.php', 'error', 'Failed to create gym tenant.' . $errDetail);
        }
    }

    if (isset($_POST['update_tenant'])) {
        $id = (int)$_POST['id'];
        $gym_name = trim($_POST['gym_name']);
        $owner_name = trim($_POST['owner_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $currency = trim($_POST['currency'] ?? '₹');
        $timezone = trim($_POST['timezone'] ?? 'Asia/Kolkata');
        $plan_id = (int)$_POST['subscription_plan_id'];
        $status = (string)($_POST['status'] ?? '');
        $expiryRaw = trim((string)($_POST['subscription_expiry'] ?? ''));

        // tenants.status enum: active, inactive, suspended, trial
        if (!in_array($status, ['active', 'inactive', 'suspended', 'trial'], true)) {
            redirect('tenants.php', 'error', 'Invalid tenant status selected.');
        }
        $expiryDate = DateTime::createFromFormat('Y-m-d', substr($expiryRaw, 0, 10));
        if (!$expiryDate || $expiryDate->format('Y-m-d') !== substr($expiryRaw, 0, 10)) {
            redirect('tenants.php', 'error', 'Invalid subscription expiry date.');
        }
        $expiry = $expiryDate->format('Y-m-d') . ' 23:59:59';
        $existingTenant = DB::fetchOne("SELECT id, status FROM tenants WHERE id = ?", [$id]);
        if (!$existingTenant) {
            redirect('tenants.php', 'error', 'Tenant not found.');
        }

        $updateData = [
            'gym_name' => $gym_name,
            'owner_name' => $owner_name,
            'email' => $email,
            'phone' => $phone,
            'currency' => $currency,
            'timezone' => $timezone,
            'subscription_plan_id' => $plan_id,
            'status' => $status,
            'subscription_expiry' => $expiry
        ];

        // Robust Logo Upload Handling for Update
        if (!empty($_FILES['logo']['name'])) {
            if ($_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
                $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif']; // SVG excluded: can carry script (stored XSS)
                if (in_array($ext, $allowedExts)) {
                    $logoDir = __DIR__ . '/../uploads/logos';
                    if (!is_dir($logoDir)) {
                        @mkdir($logoDir, 0777, true);
                    }
                    $logoFileName = 'logo_' . $id . '_' . time() . '.' . $ext;
                    if (@move_uploaded_file($_FILES['logo']['tmp_name'], $logoDir . '/' . $logoFileName)) {
                        $updateData['logo'] = $logoFileName;
                    }
                }
            }
        }

        DB::update('tenants', $updateData, 'id = ?', [$id]);
        Auth::auditLog('SUPER_UPDATE_TENANT', "Updated gym tenant #{$id} (status: {$existingTenant['status']} -> {$status}, expiry: {$expiry})", $id);

        redirect('tenants.php', 'success', 'Tenant account updated successfully.');
    }

    if (isset($_POST['delete_tenant'])) {
        $id = (int)$_POST['id'];
        if ($id === 1) {
            redirect('tenants.php', 'error', 'Default primary tenant (#1) cannot be deleted.');
        }

        $tenant = DB::fetchOne("SELECT gym_name FROM tenants WHERE id = ?", [$id]);
        if (!$tenant) {
            redirect('tenants.php', 'error', 'Tenant not found or already deleted.');
        }
        $gymName = $tenant['gym_name'] ?? "Gym #$id";

        // Cleanly delete all associated data across all tenant tables
        $tablesWithTenantId = [
            'member_tokens',
            'device_tokens',
            'attendance',
            'member_subscriptions',
            'membership_payments',
            'member_workout_todos',
            'member_assigned_plans',
            'member_inquiries',
            'workout_plans',
            'diet_plans',
            'class_bookings',
            'classes',
            'invoices',
            'expenses',
            'todo',
            'reminder',
            'rates',
            'equipment',
            'announcements',
            'staffs',
            'trainers',
            'branches'
        ];

        DB::beginTransaction();

        // Delete from all tenant-specific tables (attendance is tenant-scoped; never match by user_id
        // alone since legacy user_id values are not unique across tables)
        foreach ($tablesWithTenantId as $tbl) {
            $tblExists = (int)DB::fetchValue("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?", [$tbl]);
            if ($tblExists) {
                DB::query("DELETE FROM `{$tbl}` WHERE tenant_id = ?", [$id]);
            }
        }

        // Delete reset tokens, members, users and tenant record
        DB::query("DELETE FROM password_resets WHERE user_id IN (SELECT id FROM users WHERE tenant_id = ? AND role NOT IN ('super_admin','superadmin'))", [$id]);
        DB::query("DELETE FROM members WHERE tenant_id = ?", [$id]);
        DB::query("DELETE FROM users WHERE tenant_id = ? AND role NOT IN ('super_admin','superadmin')", [$id]);
        $deleted = DB::query("DELETE FROM tenants WHERE id = ?", [$id]);

        if (!is_array($deleted) || (int)$deleted['affected'] !== 1) {
            DB::rollback();
            redirect('tenants.php', 'error', 'Failed to delete gym tenant. No changes were made.');
        }
        DB::commit();

        Auth::auditLog('SUPER_DELETE_TENANT', "Permanently deleted gym tenant: $gymName (#$id)");
        redirect('tenants.php', 'success', "Gym '$gymName' and all its associated data have been permanently deleted.");
    }
}

$plans = DB::fetchAll("SELECT * FROM subscription_plans ORDER BY price ASC");
$tenants = DB::fetchAll("SELECT t.*, p.name as plan_name, 
                         (SELECT COUNT(*) FROM members m WHERE m.tenant_id = t.id) as member_count,
                         (SELECT COUNT(*) FROM staffs s WHERE s.tenant_id = t.id) as staff_count 
                         FROM tenants t 
                         LEFT JOIN subscription_plans p ON t.subscription_plan_id = p.id 
                         ORDER BY t.id DESC");

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/topbar.php';
?>

<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="fas fa-building"></i>
            <span>All Registered Gym Tenants (<?php echo count($tenants); ?>)</span>
        </div>
        <div style="display: flex; gap: 10px;">
            <input type="text" placeholder="Search gym name or owner..." data-table-search="#tenants-list-table" class="form-control" style="width: 250px;" />
            <button type="button" class="btn btn-primary btn-sm" onclick="App.openModal('create-tenant-modal')">
                <i class="fas fa-plus"></i> Create New Gym
            </button>
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table" id="tenants-list-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Gym Business</th>
                        <th>Owner / Contact</th>
                        <th>Plan</th>
                        <th>Usage</th>
                        <th>Status</th>
                        <th>Expiry</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tenants as $t): ?>
                        <tr>
                            <td>#<?php echo $t['id']; ?></td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div style="width: 38px; height: 38px; border-radius: var(--radius-md); background: var(--bg-surface); border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0;">
                                        <?php if (!empty($t['logo'])): ?>
                                            <img src="<?php echo str_starts_with($t['logo'], 'http') ? $t['logo'] : base_url('/uploads/logos/' . $t['logo']); ?>" style="width: 100%; height: 100%; object-fit: contain;" />
                                        <?php else: ?>
                                            <i class="fas fa-dumbbell" style="color: var(--primary);"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <div style="font-weight: 700;"><?php echo e($t['gym_name']); ?></div>
                                        <div style="margin-top: 2px; display: flex; align-items: center; gap: 6px;">
                                            <span style="background: rgba(99, 102, 241, 0.15); color: #818cf8; font-weight: 700; font-family: monospace; padding: 2px 8px; border-radius: 4px; font-size: 0.78rem; border: 1px solid rgba(99, 102, 241, 0.3);">
                                                <i class="fas fa-mobile-alt"></i> Code: <?php echo e($t['gym_code'] ?? 'N/A'); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div><?php echo e($t['owner_name']); ?></div>
                                <div style="font-size: 0.8rem; color: var(--text-muted);"><?php echo e($t['email']); ?> • <?php echo e($t['phone']); ?></div>
                            </td>
                            <td>
                                <span class="status-badge badge-info"><?php echo e($t['plan_name'] ?? 'Custom'); ?></span>
                            </td>
                            <td>
                                <div style="font-size: 0.85rem;">
                                    <span><i class="fas fa-users" style="color: var(--primary);"></i> <?php echo $t['member_count']; ?></span>
                                    <span style="margin-left: 8px;"><i class="fas fa-user-shield" style="color: var(--accent);"></i> <?php echo $t['staff_count']; ?></span>
                                </div>
                            </td>
                            <td>
                                <?php echo status_badge($t['status']); ?>
                            </td>
                            <td>
                                <?php echo format_date($t['subscription_expiry']); ?>
                            </td>
                            <td>
                                <div style="display: flex; gap: 6px;">
                                    <form method="POST" action="<?php echo base_url('/superadmin/impersonate.php'); ?>" style="display: inline; margin: 0;">
                                        <?php echo Auth::csrfField(); ?>
                                        <input type="hidden" name="tenant_id" value="<?php echo (int)$t['id']; ?>" />
                                        <button type="submit" class="btn btn-secondary btn-sm" title="Impersonate Gym Admin">
                                            <i class="fas fa-sign-in-alt"></i> Login
                                        </button>
                                    </form>
                                    <button type="button" class="btn btn-secondary btn-sm" onclick='editTenant(<?php echo json_encode($t, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)' title="Edit Gym">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if ($t['id'] != 1): ?>
                                        <button type="button" class="btn btn-danger btn-sm" onclick='confirmDeleteTenant(<?php echo $t['id']; ?>, <?php echo json_encode($t['gym_name'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)' title="Delete Gym Permanently">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Edit Tenant Modal -->
<div class="modal-backdrop" id="edit-tenant-modal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="card-title">
                <i class="fas fa-edit"></i>
                <span id="modal-tenant-title">Manage Gym Tenant</span>
            </div>
            <button type="button" class="btn-icon" onclick="App.closeModal('edit-tenant-modal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" action="" enctype="multipart/form-data">
            <?php echo Auth::csrfField(); ?>
            <input type="hidden" name="id" id="edit-id" />
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Gym Business Name</label>
                        <input type="text" name="gym_name" id="edit-name" class="form-control" required />
                    </div>
                    <div class="form-group">
                        <label class="form-label">Gym Brand Logo</label>
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <div id="edit-logo-preview-container" style="width: 42px; height: 42px; border-radius: var(--radius-md); background: var(--bg-app); border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0;">
                                <img id="edit-logo-preview" src="" style="width: 100%; height: 100%; object-fit: contain; display: none;" />
                                <i id="edit-logo-placeholder" class="fas fa-dumbbell" style="color: var(--text-muted);"></i>
                            </div>
                            <input type="file" name="logo" class="form-control" accept="image/*" style="font-size: 0.85rem;" />
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Owner Name</label>
                        <input type="text" name="owner_name" id="edit-owner" class="form-control" required />
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" id="edit-email" class="form-control" required />
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" id="edit-phone" class="form-control" required />
                    </div>
                    <div class="form-group">
                        <label class="form-label">Subscription Plan</label>
                        <select name="subscription_plan_id" id="edit-plan" class="form-select">
                            <?php foreach ($plans as $p): ?>
                                <option value="<?php echo $p['id']; ?>"><?php echo e($p['name']); ?> (₹<?php echo number_format($p['price_monthly'] ?: $p['price'], 0); ?>/mo)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Currency Symbol</label>
                        <select name="currency" id="edit-currency" class="form-select">
                            <?php echo render_currency_options(); ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Timezone</label>
                        <select name="timezone" id="edit-timezone" class="form-select">
                            <?php echo render_timezone_options(); ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Account Status</label>
                        <select name="status" id="edit-status" class="form-select">
                            <option value="active">Active</option>
                            <option value="trial">Trial</option>
                            <option value="inactive">Inactive</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Subscription Expiry Date</label>
                        <input type="date" name="subscription_expiry" id="edit-expiry" class="form-control" required />
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="display: flex; justify-content: space-between;">
                <div>
                    <button type="button" class="btn btn-danger btn-sm" id="btn-modal-delete-tenant" onclick="deleteFromEditModal()">
                        <i class="fas fa-trash-alt"></i> Delete Gym
                    </button>
                </div>
                <div style="display: flex; gap: 8px;">
                    <button type="button" class="btn btn-secondary" onclick="App.closeModal('edit-tenant-modal')">Cancel</button>
                    <button type="submit" name="update_tenant" class="btn btn-primary">Save Changes</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Create Tenant Modal -->
<div class="modal-backdrop" id="create-tenant-modal">
    <div class="modal-content" style="max-width: 680px; max-height: 90vh; overflow-y: auto;">
        <div class="modal-header">
            <div class="card-title">
                <i class="fas fa-plus-circle"></i>
                <span>Add New Gym Tenant</span>
            </div>
            <button type="button" class="btn-icon" onclick="App.closeModal('create-tenant-modal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" action="" enctype="multipart/form-data">
            <?php echo Auth::csrfField(); ?>
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Gym Business Name *</label>
                        <input type="text" name="gym_name" class="form-control" placeholder="e.g. Gold Fitness Club" required />
                    </div>
                    <div class="form-group">
                        <label class="form-label">Owner Full Name *</label>
                        <input type="text" name="owner_name" class="form-control" placeholder="e.g. Rahul Sharma" required />
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Gym Brand Logo (Optional)</label>
                        <input type="file" name="logo" class="form-control" accept="image/*" />
                    </div>
                    <div class="form-group">
                        <label class="form-label">Contact Email *</label>
                        <input type="email" name="email" class="form-control" placeholder="owner@gym.com" required />
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Contact Phone *</label>
                        <input type="text" name="phone" class="form-control" placeholder="9876543210" required />
                    </div>
                    <div class="form-group">
                        <label class="form-label">Physical Address</label>
                        <input type="text" name="address" class="form-control" placeholder="Street Address, City, State" />
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Subscription Tier *</label>
                        <select name="subscription_plan_id" class="form-select" required>
                            <?php foreach ($plans as $p): ?>
                                <option value="<?php echo $p['id']; ?>" <?php echo $p['id'] == 2 ? 'selected' : ''; ?>>
                                    <?php echo e($p['name']); ?> (₹<?php echo number_format($p['price_monthly'] ?: $p['price'], 0); ?>/mo)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Initial Duration</label>
                        <select name="duration_months" class="form-select">
                            <option value="1">1 Month</option>
                            <option value="3">3 Months</option>
                            <option value="6">6 Months</option>
                            <option value="12" selected>12 Months (1 Year)</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Currency Symbol *</label>
                        <select name="currency" class="form-select">
                            <?php echo render_currency_options('₹'); ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Timezone</label>
                        <select name="timezone" class="form-select">
                            <?php echo render_timezone_options('Asia/Kolkata'); ?>
                        </select>
                    </div>
                </div>

                <div style="background: var(--bg-app); border: 1px solid var(--border-color); padding: 14px; border-radius: var(--radius-md); margin-top: 10px;">
                    <div style="font-size: 0.82rem; font-weight: 700; color: var(--text-main); margin-bottom: 10px; text-transform: uppercase;">
                        <i class="fas fa-user-shield"></i> Gym Admin Login Credentials
                    </div>
                    <div class="form-row">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Login Username *</label>
                            <input type="text" name="username" class="form-control" placeholder="e.g. rahul_gym" required />
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Login Password *</label>
                            <input type="password" name="password" class="form-control" placeholder="••••••••" required />
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 8px;">
                <button type="button" class="btn btn-secondary" onclick="App.closeModal('create-tenant-modal')">Cancel</button>
                <button type="submit" name="create_tenant" value="1" class="btn btn-primary">
                    <i class="fas fa-check-circle"></i> Create & Launch Gym
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Tenant Confirmation Modal -->
<div class="modal-backdrop" id="delete-tenant-modal">
    <div class="modal-content" style="max-width: 480px;">
        <div class="modal-header" style="border-bottom-color: rgba(239, 68, 68, 0.2);">
            <div class="card-title" style="color: var(--danger);">
                <i class="fas fa-exclamation-triangle"></i>
                <span>Delete Gym Tenant</span>
            </div>
            <button type="button" class="btn-icon" onclick="App.closeModal('delete-tenant-modal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" action="">
            <?php echo Auth::csrfField(); ?>
            <input type="hidden" name="id" id="delete-tenant-id" />
            <input type="hidden" name="delete_tenant" value="1" />
            <div class="modal-body">
                <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); padding: 14px; border-radius: var(--radius-md); margin-bottom: 16px;">
                    <strong style="color: var(--danger); font-size: 0.95rem;">
                        <i class="fas fa-radiation"></i> Permanent Deletion Warning!
                    </strong>
                    <p style="font-size: 0.85rem; color: var(--text-main); margin-top: 6px; margin-bottom: 0;">
                        Are you sure you want to permanently delete <strong id="delete-tenant-name" style="color: var(--danger);"></strong>?
                    </p>
                </div>
                <p style="font-size: 0.85rem; color: var(--text-muted);">
                    This action will permanently delete:
                </p>
                <ul style="font-size: 0.85rem; color: var(--text-muted); padding-left: 20px; line-height: 1.6;">
                    <li>All Member profiles, accounts, & tokens</li>
                    <li>All Staff & Trainer records</li>
                    <li>All Invoices, payments & financial logs</li>
                    <li>All Workout, diet & attendance histories</li>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="App.closeModal('delete-tenant-modal')">Cancel</button>
                <button type="submit" class="btn btn-danger">
                    <i class="fas fa-trash-alt"></i> Yes, Delete Gym Permanently
                </button>
            </div>
        </form>
    </div>
</div>

<script>
let currentEditTenant = null;

function editTenant(tenant) {
    currentEditTenant = tenant;
    document.getElementById('edit-id').value = tenant.id;
    document.getElementById('edit-name').value = tenant.gym_name;
    document.getElementById('edit-owner').value = tenant.owner_name;
    document.getElementById('edit-email').value = tenant.email;
    document.getElementById('edit-phone').value = tenant.phone;
    document.getElementById('edit-plan').value = tenant.subscription_plan_id;
    if (document.getElementById('edit-currency')) {
        document.getElementById('edit-currency').value = tenant.currency || '₹';
    }
    if (document.getElementById('edit-timezone')) {
        document.getElementById('edit-timezone').value = tenant.timezone || 'Asia/Kolkata';
    }
    document.getElementById('edit-status').value = tenant.status;
    document.getElementById('edit-expiry').value = (tenant.subscription_expiry || '').substring(0, 10);
    document.getElementById('modal-tenant-title').innerText = 'Manage ' + tenant.gym_name;

    const logoImg = document.getElementById('edit-logo-preview');
    const logoIcon = document.getElementById('edit-logo-placeholder');
    if (tenant.logo) {
        let logoSrc = tenant.logo.startsWith('http') ? tenant.logo : '<?php echo base_url('/uploads/logos/'); ?>' + tenant.logo;
        logoImg.src = logoSrc;
        logoImg.style.display = 'block';
        logoIcon.style.display = 'none';
    } else {
        logoImg.src = '';
        logoImg.style.display = 'none';
        logoIcon.style.display = 'block';
    }
    
    const deleteBtn = document.getElementById('btn-modal-delete-tenant');
    if (deleteBtn) {
        deleteBtn.style.display = (tenant.id == 1) ? 'none' : 'inline-flex';
    }

    App.openModal('edit-tenant-modal');
}

function confirmDeleteTenant(id, name) {
    if (id == 1) {
        alert('Primary Gym (#1) cannot be deleted.');
        return;
    }
    document.getElementById('delete-tenant-id').value = id;
    document.getElementById('delete-tenant-name').innerText = name + ' (#' + id + ')';
    App.openModal('delete-tenant-modal');
}

function deleteFromEditModal() {
    if (currentEditTenant) {
        App.closeModal('edit-tenant-modal');
        confirmDeleteTenant(currentEditTenant.id, currentEditTenant.gym_name);
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

