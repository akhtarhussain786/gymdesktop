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

// Gym UPI Configuration
$gymUpiId = !empty($tenant['upi_id']) ? $tenant['upi_id'] : 'fitisify@upi';
$gymNameClean = preg_replace('/[^a-zA-Z0-9 ]/', '', $tenant['gym_name'] ?? 'Gym');

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
    $paymentMethod = $_POST['payment_method'] ?? 'Cash';
    $transactionRef = trim($_POST['transaction_ref'] ?? '');

    $totalAmount = $amount * $plan;
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // Photo / Avatar upload
    $avatarFileName = null;
    if (!empty($_FILES['avatar']['name']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif']) && @getimagesize($_FILES['avatar']['tmp_name']) !== false) {
            $uploadDir = __DIR__ . '/../uploads/avatars';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0777, true);
            }
            $avatarFileName = 'avatar_' . $tenantId . '_' . time() . '_' . substr(md5(uniqid()), 0, 6) . '.' . $ext;
            if (!@move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadDir . '/' . $avatarFileName)) {
                $avatarFileName = null;
            }
        }
    }

    if (empty($fullname) || empty($username)) {
        $error = "Full Name and Username are required fields.";
    } else {
        // Login (Auth::attempt) looks usernames up across ALL gyms, so they must be globally unique
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
                'avatar' => $avatarFileName,
                'photo' => $avatarFileName,
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
                    'avatar' => $avatarFileName,
                    'email' => $email ?: null,
                    'phone' => $contact ?: null,
                    'role' => 'member',
                    'status' => 'active'
                ]);

                // Generate unique tenant-aware invoice number
                $invCount = (int)DB::fetchValue("SELECT COUNT(*) FROM invoices WHERE tenant_id = ?", [$tenantId]);
                $tenantSlug = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $tenant['slug'] ?? $tenant['gym_name'] ?? 'GYM'), 0, 4));
                $invoiceNumber = 'INV-' . $tenantSlug . '-' . date('Ym') . '-' . str_pad($invCount + 1, 4, '0', STR_PAD_LEFT);

                if (empty($transactionRef)) {
                    $prefix = ($paymentMethod === 'UPI') ? 'UPI-' : 'PAY-';
                    $transactionRef = $prefix . strtoupper(substr(md5(uniqid()), 0, 8));
                }

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
                    'payment_method' => $paymentMethod,
                    'payment_date' => date('Y-m-d'),
                    'transaction_ref' => $transactionRef,
                    'notes' => "Initial membership registration for {$services} ({$plan} Mo) via {$paymentMethod}",
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

                Auth::auditLog('ADD_MEMBER', "Added new member $fullname (#$memberId) via {$paymentMethod} with Invoice #$invoiceNumber");
                redirect('members.php', 'success', "Member $fullname successfully registered via {$paymentMethod}! App login & Invoice #$invoiceNumber activated.");
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

<div class="card" style="max-width: 880px; margin: 0 auto;">
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

        <form method="POST" action="" id="member-reg-form" enctype="multipart/form-data">
            <?php echo Auth::csrfField(); ?>

            <!-- 1. Personal & Login Details -->
            <div style="font-size: 0.8rem; font-weight: 800; color: var(--text-light); text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 12px;">
                1. Member Personal & Login Details
            </div>

            <!-- Profile Photo Upload -->
            <div style="background: var(--bg-app); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 16px; margin-bottom: 20px; display: flex; align-items: center; gap: 20px;">
                <div style="width: 72px; height: 72px; border-radius: 50%; background: var(--bg-card); border: 2px dashed var(--primary); display: flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0;" id="avatar-preview-box">
                    <i class="fas fa-camera" id="avatar-preview-icon" style="font-size: 1.5rem; color: var(--text-muted);"></i>
                    <img id="avatar-preview-img" src="" style="width: 100%; height: 100%; object-fit: cover; display: none;" alt="Preview" />
                </div>
                <div style="flex: 1;">
                    <label class="form-label" style="margin-bottom: 4px;">Member Profile Photo (App & ID Pass)</label>
                    <input type="file" name="avatar" id="avatar-file-input" class="form-control" accept="image/*" onchange="previewMemberPhoto(this)" style="font-size: 0.85rem;" />
                    <small style="color: var(--text-muted);">JPG, PNG, WebP up to 5MB. Will be displayed on Member Mobile App.</small>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Full Name *</label>
                    <input type="text" name="fullname" id="fullname-input" class="form-control" placeholder="e.g. Rahul Sharma" required oninput="autoSuggestUsername()" />
                </div>
                <div class="form-group">
                    <label class="form-label">Username (Member App Login) *</label>
                    <input type="text" name="username" id="username-input" class="form-control" placeholder="e.g. rahul_sharma" required />
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Password *</label>
                    <input type="password" name="password" class="form-control" value="123456" required />
                    <small style="color: var(--text-muted);">Default: <code>123456</code> (Member can change on app login)</small>
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
                    <label class="form-label">Contact Number (WhatsApp) *</label>
                    <input type="text" name="contact" class="form-control" placeholder="e.g. 9876543210" required />
                </div>
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control" placeholder="member@email.com" />
                </div>
                <div class="form-group">
                    <label class="form-label">Joining Date (DOR) *</label>
                    <input type="date" name="dor" class="form-control" value="<?php echo date('Y-m-d'); ?>" required />
                </div>
            </div>

            <!-- 2. Package & Duration Configuration -->
            <div style="font-size: 0.8rem; font-weight: 800; color: var(--text-light); text-transform: uppercase; letter-spacing: 0.08em; margin: 20px 0 12px 0;">
                2. Membership Package & Duration
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
                        <option value="3">3 Months (Quarterly)</option>
                        <option value="6">6 Months (Half-Yearly)</option>
                        <option value="12">12 Months (1 Year)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Monthly Rate (<?php echo $tenant['currency']; ?>) *</label>
                    <input type="number" step="0.01" name="amount" id="rate-input" class="form-control" value="<?php echo $rates[0]['charge'] ?? 1000; ?>" oninput="updateAmount()" required />
                </div>
            </div>

            <div style="background: var(--bg-app); border: 1px solid var(--border-color); padding: 16px; border-radius: var(--radius-md); margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px;">
                <div>
                    <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Total Payable Amount</div>
                    <div style="font-size: 1.8rem; font-weight: 800; color: var(--primary);" id="total-display">
                        <?php echo format_currency($rates[0]['charge'] ?? 1000); ?>
                    </div>
                </div>
                <div class="form-group" style="margin-bottom: 0; min-width: 220px;">
                    <label class="form-label">Assign Personal Trainer</label>
                    <select name="trainer_id" class="form-select">
                        <option value="">None / General Fitness</option>
                        <?php foreach ($trainers as $tr): ?>
                            <option value="<?php echo $tr['user_id']; ?>"><?php echo e($tr['fullname']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- 3. Payment Method & Dynamic UPI QR Section -->
            <div style="font-size: 0.8rem; font-weight: 800; color: var(--text-light); text-transform: uppercase; letter-spacing: 0.08em; margin: 20px 0 12px 0;">
                3. Payment Method & Settlement
            </div>

            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label class="form-label">Select Payment Method *</label>
                    <select name="payment_method" id="payment-method-select" class="form-select" onchange="togglePaymentMethodView()" style="font-weight: 700; font-size: 0.95rem;">
                        <option value="Cash">💵 Cash at Desk</option>
                        <option value="UPI" selected>📱 UPI / Dynamic QR Code (Scan & Pay)</option>
                        <option value="Card">💳 Credit / Debit Card</option>
                        <option value="Online">🏦 Online Bank Transfer</option>
                    </select>
                </div>

                <div class="form-group" style="flex: 1;" id="txn-ref-wrapper">
                    <label class="form-label">Transaction / UTR Reference No. (Optional)</label>
                    <input type="text" name="transaction_ref" id="txn-ref-input" class="form-control" placeholder="e.g. UPI Ref / UTR / Cash Receipt" />
                </div>
            </div>

            <!-- Dynamic UPI QR Card Box (Shown when UPI is selected) -->
            <div id="upi-qr-box" style="background: linear-gradient(135deg, rgba(204, 255, 0, 0.05), rgba(0, 242, 254, 0.03)); border: 1.5px solid var(--lime-border); border-radius: var(--radius-lg); padding: 20px; margin-bottom: 24px;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="background: var(--lime); color: #000; padding: 4px 10px; border-radius: 6px; font-weight: 800; font-size: 0.8rem; text-transform: uppercase;">
                            <i class="fas fa-qrcode"></i> Live UPI QR
                        </span>
                        <strong style="color: #fff; font-size: 1.05rem;">Scan & Pay via GPay / PhonePe / Paytm</strong>
                    </div>
                    <span class="status-badge badge-success" style="font-size: 0.8rem;">
                        <i class="fas fa-check-circle"></i> Instant Verification
                    </span>
                </div>

                <div style="display: flex; gap: 24px; align-items: center; flex-wrap: wrap;">
                    <!-- QR Code Frame -->
                    <div style="background: #fff; padding: 12px; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.4); text-align: center; width: 180px; flex-shrink: 0;">
                        <img id="dynamic-qr-img" src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=upi://pay" style="width: 156px; height: 156px; display: block; border-radius: 8px;" alt="UPI QR Code" />
                        <div style="font-size: 0.68rem; font-weight: 800; color: #000; margin-top: 6px; letter-spacing: 0.05em;">SCAN WITH ANY UPI APP</div>
                    </div>

                    <!-- UPI Details & Instructions -->
                    <div style="flex: 1; min-width: 240px;">
                        <div style="background: var(--bg-card); padding: 14px 16px; border-radius: 12px; border: 1px solid var(--border-color); margin-bottom: 12px;">
                            <div style="font-size: 0.78rem; color: var(--text-muted); margin-bottom: 4px;">Gym UPI ID:</div>
                            <div style="display: flex; align-items: center; justify-content: space-between; gap: 10px;">
                                <code id="gym-upi-text" style="font-size: 1rem; color: var(--lime); font-weight: 800;"><?php echo htmlspecialchars($gymUpiId); ?></code>
                                <button type="button" class="btn btn-secondary btn-sm" onclick="copyUpiId()" style="padding: 4px 10px; font-size: 0.78rem;">
                                    <i class="fas fa-copy"></i> Copy
                                </button>
                            </div>
                        </div>

                        <div style="font-size: 0.85rem; color: var(--text-main); line-height: 1.6;">
                            <div>• <strong>Payee Name:</strong> <?php echo htmlspecialchars($tenant['gym_name'] ?? 'Gym Master'); ?></div>
                            <div>• <strong>Payable Amount:</strong> <span id="upi-amount-text" style="color: var(--lime); font-weight: 800; font-size: 1.1rem;"><?php echo format_currency($rates[0]['charge'] ?? 1000); ?></span></div>
                            <div style="color: var(--text-muted); font-size: 0.78rem; margin-top: 6px;">
                                <i class="fas fa-info-circle" style="color: var(--primary);"></i> Show this QR code to the member on your desk or mobile screen. Once paid, click <strong>Save & Register Member</strong> below.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Address Field -->
            <div class="form-group">
                <label class="form-label">Residential Address</label>
                <input type="text" name="address" class="form-control" placeholder="Street Address, Area, City" />
            </div>

            <div style="margin-top: 24px; display: flex; justify-content: flex-end; gap: 12px;">
                <a href="members.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" name="submit_member" value="1" class="btn btn-primary btn-lg" style="font-weight: 800; padding: 14px 28px;">
                    <i class="fas fa-check-circle"></i> Save & Register Member
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const gymUpiId = "<?php echo addslashes($gymUpiId); ?>";
const gymName = "<?php echo addslashes($gymNameClean); ?>";

function updateAmount() {
    const serviceSel = document.getElementById('service-select');
    const rate = parseFloat(document.getElementById('rate-input').value) || 0;
    const plan = parseInt(document.getElementById('plan-select').value) || 1;
    const total = rate * plan;
    const currency = "<?php echo $tenant['currency']; ?>";
    
    document.getElementById('total-display').innerText = currency + total.toFixed(2);
    document.getElementById('upi-amount-text').innerText = currency + total.toFixed(2);

    updateQrCode(total);
}

function updateQrCode(amount) {
    const memberName = document.getElementById('fullname-input').value.trim() || 'Member';
    const upiString = `upi://pay?pa=${encodeURIComponent(gymUpiId)}&pn=${encodeURIComponent(gymName)}&am=${amount.toFixed(2)}&cu=INR&tn=${encodeURIComponent('Gym Fee - ' + memberName)}`;
    const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=${encodeURIComponent(upiString)}`;
    
    const qrImg = document.getElementById('dynamic-qr-img');
    if (qrImg) {
        qrImg.src = qrUrl;
    }
}

function togglePaymentMethodView() {
    const method = document.getElementById('payment-method-select').value;
    const qrBox = document.getElementById('upi-qr-box');
    const txnRefInput = document.getElementById('txn-ref-input');

    if (method === 'UPI') {
        qrBox.style.display = 'block';
        txnRefInput.placeholder = 'e.g. UTR No. (12 digits) or UPI Ref';
        updateAmount();
    } else {
        qrBox.style.display = 'none';
        txnRefInput.placeholder = method === 'Cash' ? 'e.g. Cash Receipt No. (Optional)' : 'e.g. Card / Bank Ref';
    }
}

function copyUpiId() {
    navigator.clipboard.writeText(gymUpiId).then(() => {
        alert('Gym UPI ID copied to clipboard: ' + gymUpiId);
    });
}

function autoSuggestUsername() {
    const name = document.getElementById('fullname-input').value.trim().toLowerCase().replace(/[^a-z0-9]/g, '_');
    const userInp = document.getElementById('username-input');
    if (userInp.value === '' || userInp.dataset.autosuggested === 'true') {
        userInp.value = name;
        userInp.dataset.autosuggested = 'true';
    }
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

function previewMemberPhoto(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const previewImg = document.getElementById('avatar-preview-img');
            const previewIcon = document.getElementById('avatar-preview-icon');
            if (previewImg && previewIcon) {
                previewImg.src = e.target.result;
                previewImg.style.display = 'block';
                previewIcon.style.display = 'none';
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    togglePaymentMethodView();
    updateAmount();
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
