<?php
require_once __DIR__ . '/../core/auth.php';
Auth::requireAuth(['gym_admin']);

$page = 'staffs';
$pageTitle = 'Add New Staff / Trainer';
$pageSubtitle = 'Create a new employee or trainer account for your gym';
$tenantId = Tenant::getTenantId();

// Quota Check
$quota = Tenant::checkLimit('staff');
if (!$quota['allowed']) {
    redirect('subscription.php', 'error', 'Staff limit reached for your active subscription plan. Please upgrade to add more staff.');
}

$error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_staff'])) {
    Auth::verifyCsrf();

    $fullname = trim($_POST['fullname'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '123456';
    $email = trim($_POST['email'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $designation = $_POST['designation'] ?? 'Trainer';
    $gender = $_POST['gender'] ?? 'Male';
    $address = trim($_POST['address'] ?? '');

    $passwordHash = password_hash($password, PASSWORD_DEFAULT); // was unsalted md5

    // Login resolves usernames across ALL gyms, so they must be globally unique
    $taken = DB::fetchOne("SELECT user_id FROM staffs WHERE LOWER(username) = LOWER(?)", [$username])
        ?: DB::fetchOne("SELECT id FROM users WHERE LOWER(username) = LOWER(?)", [$username])
        ?: DB::fetchOne("SELECT user_id FROM members WHERE LOWER(username) = LOWER(?)", [$username]);

    if (empty($fullname) || empty($username)) {
        $error = "Full Name and Username are required.";
    } elseif ($taken) {
        $error = "Username '$username' is already taken. Please choose a different username.";
    } else {
        $staffId = DB::insert('staffs', [
            'tenant_id' => $tenantId,
            'fullname' => $fullname,
            'username' => $username,
            'password' => $passwordHash,
            'email' => $email,
            'contact' => $contact,
            'designation' => $designation,
            'gender' => $gender,
            'address' => $address,
            'role' => (strtolower($designation) === 'trainer') ? 'trainer' : 'staff',
            'status' => 'active'
        ]);

        if ($staffId) {
            Auth::auditLog('ADD_STAFF', "Added new staff $fullname ($designation)");
            redirect('staffs.php', 'success', "Staff account for $fullname created successfully!");
        } else {
            $error = "Failed to add staff record. Username might already exist.";
        }
    }
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/topbar.php';
?>

<div class="card" style="max-width: 800px; margin: 0 auto;">
    <div class="card-header">
        <div class="card-title">
            <i class="fas fa-user-plus"></i>
            <span>Staff Entry Form</span>
        </div>
        <a href="staffs.php" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>
    <div class="card-body">
        <?php if (!empty($error)): ?>
            <div style="background: rgba(239, 68, 68, 0.12); color: #ef4444; padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px;">
                <i class="fas fa-exclamation-triangle"></i> <?php echo e($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <?php echo Auth::csrfField(); ?>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Full Name *</label>
                    <input type="text" name="fullname" class="form-control" placeholder="Staff member's full name" required />
                </div>
                <div class="form-group">
                    <label class="form-label">Login Username *</label>
                    <input type="text" name="username" class="form-control" placeholder="Unique username" required />
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Password *</label>
                    <input type="password" name="password" class="form-control" value="123456" required />
                    <small style="color: var(--text-muted);">Default: <code>123456</code></small>
                </div>
                <div class="form-group">
                    <label class="form-label">Designation / Role *</label>
                    <select name="designation" class="form-select">
                        <option value="Trainer">Trainer / Fitness Instructor</option>
                        <option value="Cashier">Cashier / Receptionist</option>
                        <option value="Manager">Gym Manager</option>
                        <option value="Assistant">Assistant</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control" placeholder="staff@gym.com" />
                </div>
                <div class="form-group">
                    <label class="form-label">Contact Number *</label>
                    <input type="text" name="contact" class="form-control" placeholder="e.g. 9876543210" required />
                </div>
                <div class="form-group">
                    <label class="form-label">Gender</label>
                    <select name="gender" class="form-select">
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Residential Address</label>
                <input type="text" name="address" class="form-control" placeholder="Address, City" />
            </div>

            <div style="margin-top: 24px; display: flex; justify-content: flex-end; gap: 12px;">
                <a href="staffs.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" name="submit_staff" value="1" class="btn btn-primary btn-lg">
                    <i class="fas fa-check-circle"></i> Create Staff Account
                </button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
