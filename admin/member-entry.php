<?php
require_once __DIR__ . '/../core/auth.php';
Auth::requireAuth(['gym_admin', 'staff']);

$page = 'members-entry';
$pageTitle = 'Add New Member';
$pageSubtitle = 'Register a new gym member and configure their service package';
$tenantId = Tenant::getTenantId();
$tenant = Tenant::getCurrent();

// Quota Check
$quota = Tenant::checkLimit('members');
if (!$quota['allowed']) {
    redirect('subscription.php', 'error', 'Member limit reached for your active subscription plan. Please upgrade to add more members.');
}

// Fetch Rates / Services
$rates = Tenant::getRates($tenantId);

// Fetch Trainers
$trainers = DB::fetchAll("SELECT * FROM staffs WHERE tenant_id = ? AND designation = 'Trainer'", [$tenantId]);

// Form Processing
$error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_member'])) {
    Auth::verifyCsrf();

    $fullname = trim($_POST['fullname'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '123456';
    $gender = $_POST['gender'] ?? 'Male';
    $dor = $_POST['dor'] ?? date('Y-m-d');
    $services = $_POST['services'] ?? 'Fitness';
    $amount = (float)($_POST['amount'] ?? 55);
    $plan = max(1, (int)($_POST['plan'] ?? 1));
    $address = trim($_POST['address'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $trainer_id = !empty($_POST['trainer_id']) ? (int)$_POST['trainer_id'] : null;

    $totalAmount = $amount * $plan;
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    if (empty($fullname) || empty($username)) {
        $error = "Full Name and Username are required fields.";
    } else {
        // Login (Auth::attempt) looks usernames up across ALL gyms, so they must be globally unique,
        // otherwise a member could be signed into another gym's account.
        $existing = DB::fetchOne("SELECT user_id FROM members WHERE LOWER(username) = LOWER(?)", [$username])
            ?: DB::fetchOne("SELECT id FROM users WHERE LOWER(username) = LOWER(?) OR (email IS NOT NULL AND email <> '' AND LOWER(email) = LOWER(?))", [$username, $username])
            ?: DB::fetchOne("SELECT user_id FROM staffs WHERE LOWER(username) = LOWER(?)", [$username]);
        if ($existing) {
            $error = "Username '$username' is already taken. Please choose a different username.";
        } else {
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
                'paid_date' => date('Y-m-d'),
                'p_year' => (int)date('Y'),
                'plan' => $plan,
                'address' => $address,
                'contact' => $contact,
                'email' => $email,
                'trainer_id' => $trainer_id,
                'status' => 'Active',
                'attendance_count' => 0
            ]);

            if ($memberId) {
                // Sync user account for Mobile App Login
                DB::insert('users', [
                    'tenant_id' => $tenantId,
                    'branch_id' => 101,
                    'member_id' => $memberId,
                    'username' => $username,
                    'password' => $passwordHash,
                    'fullname' => $fullname,
                    'email' => $email ?: null,
                    'phone' => $contact ?: null,
                    'role' => 'member',
                    'status' => 'active'
                ]);

                // Generate unique tenant-aware invoice number
                $invCount = (int)DB::fetchValue("SELECT COUNT(*) FROM invoices WHERE tenant_id = ?", [$tenantId]);
                $tenantSlug = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $tenant['slug'] ?? $tenant['gym_name'] ?? 'GYM'), 0, 4));
                $invoiceNumber = 'INV-' . $tenantSlug . '-' . date('Ym') . '-' . str_pad($invCount + 1, 4, '0', STR_PAD_LEFT);

                // Create initial invoice
                $invId = DB::insert('invoices', [
                    'tenant_id' => $tenantId,
                    'branch_id' => 101,
                    'member_id' => $memberId,
                    'invoice_number' => $invoiceNumber,
                    'service_name' => $services,
                    'plan_months' => $plan,
                    'amount' => $totalAmount,
                    'paid_amount' => $totalAmount,
                    'discount' => 0.00,
                    'status' => 'Paid',
                    'payment_method' => 'Cash',
                    'payment_date' => date('Y-m-d'),
                    'transaction_ref' => 'CASH-' . strtoupper(substr(md5(uniqid()), 0, 8)),
                    'notes' => "Initial membership registration for {$services} ({$plan} Mo)",
                    'created_by' => $_SESSION['user_id'] ?? null,
                    'created_at' => date('Y-m-d H:i:s')
                ]);

                // Create initial active subscription record
                $expiryDate = date('Y-m-d', strtotime("+{$plan} months"));
                DB::insert('member_subscriptions', [
                    'tenant_id' => $tenantId,
                    'member_id' => $memberId,
                    'plan_name_snapshot' => $services,
                    'plan_price_snapshot' => $totalAmount,
                    'plan_duration_snapshot' => $plan,
                    'start_date' => date('Y-m-d'),
                    'expiry_date' => $expiryDate,
                    'status' => 'active',
                    'payment_id' => $invId,
                    'queue_position' => 0,
                    'activated_at' => date('Y-m-d H:i:s'),
                    'created_at' => date('Y-m-d H:i:s')
                ]);

                Auth::auditLog('ADD_MEMBER', "Added new member $fullname (#$memberId) with Invoice #$invoiceNumber");
                redirect('members.php', 'success', "Member $fullname successfully registered! Mobile app login & Invoice #$invoiceNumber activated.");
            } else {
                $error = "Failed to add member to database. Please verify input fields.";
            }
        }
    }
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/topbar.php';
?>

<div class="card" style="max-width: 860px; margin: 0 auto;">
    <div class="card-header">
        <div class="card-title">
            <i class="fas fa-user-plus"></i>
            <span>New Member Registration Form</span>
        </div>
        <a href="members.php" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Back to Members
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
                    <input type="text" name="fullname" class="form-control" placeholder="Member's full name" required />
                </div>
                <div class="form-group">
                    <label class="form-label">Username (Member Portal Login) *</label>
                    <input type="text" name="username" class="form-control" placeholder="Unique username" required />
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Password *</label>
                    <input type="password" name="password" class="form-control" value="123456" required />
                    <small style="color: var(--text-muted);">Default: <code>123456</code> (Member can change on login)</small>
                </div>
                <div class="form-group">
                    <label class="form-label">Gender *</label>
                    <select name="gender" class="form-select">
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Contact Number *</label>
                    <input type="text" name="contact" class="form-control" placeholder="e.g. 9876543210" required />
                </div>
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control" placeholder="member@email.com" />
                </div>
                <div class="form-group">
                    <label class="form-label">Date of Registration (DOR) *</label>
                    <input type="date" name="dor" class="form-control" value="<?php echo date('Y-m-d'); ?>" required />
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Selected Service Package *</label>
                    <select name="services" id="service-select" class="form-select" onchange="updateAmount()">
                        <?php foreach ($rates as $r): ?>
                            <option value="<?php echo e($r['name']); ?>" data-rate="<?php echo e($r['charge']); ?>">
                                <?php echo e($r['name']); ?> (<?php echo format_currency($r['charge']); ?>/mo)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Plan Duration *</label>
                    <select name="plan" id="plan-select" class="form-select" onchange="updateAmount()">
                        <option value="1">1 Month</option>
                        <option value="3">3 Months</option>
                        <option value="6">6 Months</option>
                        <option value="12">12 Months (1 Year)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Monthly Rate (<?php echo $tenant['currency']; ?>) *</label>
                    <input type="number" step="0.01" name="amount" id="rate-input" class="form-control" value="<?php echo $rates[0]['charge'] ?? 55; ?>" oninput="updateAmount()" required />
                </div>
            </div>

            <div style="background: var(--bg-app); border: 1px solid var(--border-color); padding: 16px; border-radius: var(--radius-md); margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Total Payable Amount</div>
                    <div style="font-size: 1.8rem; font-weight: 800; color: var(--primary);" id="total-display">
                        <?php echo format_currency($rates[0]['charge'] ?? 55); ?>
                    </div>
                </div>
                <div class="form-group" style="margin-bottom: 0; width: 220px;">
                    <label class="form-label">Assign Personal Trainer</label>
                    <select name="trainer_id" class="form-select">
                        <option value="">None / General</option>
                        <?php foreach ($trainers as $tr): ?>
                            <option value="<?php echo $tr['user_id']; ?>"><?php echo e($tr['fullname']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Address</label>
                <input type="text" name="address" class="form-control" placeholder="Street Address, City" />
            </div>

            <div style="margin-top: 24px; display: flex; justify-content: flex-end; gap: 12px;">
                <a href="members.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" name="submit_member" value="1" class="btn btn-primary btn-lg">
                    <i class="fas fa-check-circle"></i> Save & Register Member
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function updateAmount() {
    const serviceSel = document.getElementById('service-select');
    const selectedOpt = serviceSel && serviceSel.selectedIndex >= 0 ? serviceSel.options[serviceSel.selectedIndex] : null;
    const rate = parseFloat(document.getElementById('rate-input').value) || 0;
    const plan = parseInt(document.getElementById('plan-select').value) || 1;
    const total = rate * plan;
    const currency = "<?php echo $tenant['currency']; ?>";
    document.getElementById('total-display').innerText = currency + total.toFixed(2);
}

var memberSvcEl = document.getElementById('service-select');
if (memberSvcEl) {
    memberSvcEl.addEventListener('change', function() {
        if (this.selectedIndex >= 0 && this.options[this.selectedIndex]) {
            const opt = this.options[this.selectedIndex];
            const baseRate = opt.getAttribute('data-rate');
            if (baseRate !== null && baseRate !== '') {
                document.getElementById('rate-input').value = baseRate;
                updateAmount();
            }
        }
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
