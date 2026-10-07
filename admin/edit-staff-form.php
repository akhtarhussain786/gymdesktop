<?php
require_once __DIR__ . '/../core/auth.php';
Auth::requireAuth(['gym_admin']);

$page = 'staffs';
$pageTitle = 'Edit Staff Member';
$pageSubtitle = 'Update staff details, designation, and login credentials';
$tenantId = Tenant::getTenantId();
$staffId = (int)($_GET['id'] ?? 0);

$staff = DB::fetchOne("SELECT * FROM staffs WHERE user_id = ? AND tenant_id = ?", [$staffId, $tenantId]);
if (!$staff) {
    redirect('staffs.php', 'error', 'Staff record not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_staff'])) {
    Auth::verifyCsrf();

    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $designation = $_POST['designation'] ?? 'Trainer';
    $gender = $_POST['gender'] ?? 'Male';
    $address = trim($_POST['address'] ?? '');

    DB::update('staffs', [
        'fullname' => $fullname,
        'email' => $email,
        'contact' => $contact,
        'designation' => $designation,
        'gender' => $gender,
        'address' => $address,
        'role' => (strtolower($designation) === 'trainer') ? 'trainer' : 'staff'
    ], 'user_id = ? AND tenant_id = ?', [$staffId, $tenantId]);

    if (!empty($_POST['new_password'])) {
        $hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT); // was unsalted md5
        DB::update('staffs', ['password' => $hash], 'user_id = ? AND tenant_id = ?', [$staffId, $tenantId]);
    }

    Auth::auditLog('UPDATE_STAFF', "Updated staff $fullname (#$staffId)");
    redirect('staffs.php', 'success', "Staff account for $fullname updated successfully!");
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/topbar.php';
?>

<div class="card" style="max-width: 800px; margin: 0 auto;">
    <div class="card-header">
        <div class="card-title">
            <i class="fas fa-user-edit"></i>
            <span>Edit Staff: <?php echo e($staff['fullname']); ?></span>
        </div>
        <a href="staffs.php" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <?php echo Auth::csrfField(); ?>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Full Name *</label>
                    <input type="text" name="fullname" class="form-control" value="<?php echo e($staff['fullname']); ?>" required />
                </div>
                <div class="form-group">
                    <label class="form-label">Username (Read-only)</label>
                    <input type="text" class="form-control" value="<?php echo e($staff['username']); ?>" readonly style="background: var(--bg-app);" />
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Designation / Role</label>
                    <select name="designation" class="form-select">
                        <option value="Trainer" <?php echo $staff['designation'] === 'Trainer' ? 'selected' : ''; ?>>Trainer / Fitness Instructor</option>
                        <option value="Cashier" <?php echo $staff['designation'] === 'Cashier' ? 'selected' : ''; ?>>Cashier / Receptionist</option>
                        <option value="Manager" <?php echo $staff['designation'] === 'Manager' ? 'selected' : ''; ?>>Gym Manager</option>
                        <option value="Assistant" <?php echo $staff['designation'] === 'Assistant' ? 'selected' : ''; ?>>Assistant</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Contact Number *</label>
                    <input type="text" name="contact" class="form-control" value="<?php echo e($staff['contact']); ?>" required />
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control" value="<?php echo e($staff['email']); ?>" />
                </div>
                <div class="form-group">
                    <label class="form-label">Gender</label>
                    <select name="gender" class="form-select">
                        <option value="Male" <?php echo $staff['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?php echo $staff['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                        <option value="Other" <?php echo $staff['gender'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Reset Password</label>
                    <input type="password" name="new_password" class="form-control" placeholder="Leave blank to keep current" />
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Residential Address</label>
                <input type="text" name="address" class="form-control" value="<?php echo e($staff['address']); ?>" />
            </div>

            <div style="margin-top: 24px; display: flex; justify-content: flex-end; gap: 12px;">
                <a href="staffs.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" name="update_staff" value="1" class="btn btn-primary btn-lg">
                    <i class="fas fa-save"></i> Save Staff Profile
                </button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>