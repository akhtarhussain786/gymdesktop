<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/currencies.php';
Auth::requireAuth('gym_admin');

$page = 'settings';
$pageTitle = 'Gym Branding & System Settings';
$pageSubtitle = 'Customize your fitness brand identity, colors, currency, timezone, and branches';
$tenantId = Tenant::getTenantId();
$tenant = DB::fetchOne("SELECT * FROM tenants WHERE id = ?", [$tenantId]) ?: Tenant::getCurrent();

// Handle Save Branding Settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['save_settings']) || isset($_POST['gym_name']))) {
    Auth::verifyCsrf();

    // SECURITY: the tenant is always the session tenant. The old hidden 'active_tenant_id' field let
    // any gym admin overwrite another gym's settings AND switch their session into that gym.

    $gym_name = trim($_POST['gym_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $currency = trim($_POST['currency'] ?? '$');
    $timezone = trim($_POST['timezone'] ?? 'Asia/Kathmandu');
    $primary_color = trim($_POST['primary_color'] ?? '#3b82f6');
    $secondary_color = trim($_POST['secondary_color'] ?? '#10b981');
    $invoice_header = trim($_POST['invoice_header'] ?? '');
    $invoice_footer = trim($_POST['invoice_footer'] ?? '');
    $upi_id = trim($_POST['upi_id'] ?? '');

    // Whitelist values that drive money formatting, date maths and inline CSS
    $validSymbols = array_column(get_supported_currencies(), 'symbol');
    if (!in_array($currency, $validSymbols, true)) {
        // trim() above strips trailing spaces in symbols like 'Rs. '; match on trimmed symbol
        $match = array_values(array_filter($validSymbols, function ($sym) use ($currency) { return trim($sym) === $currency; }));
        $currency = $match[0] ?? ($tenant['currency'] ?? '₹');
    }
    if (!in_array($timezone, timezone_identifiers_list(), true)) {
        $timezone = $tenant['timezone'] ?? 'Asia/Kolkata';
    }
    $hexColor = '/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/';
    if (!preg_match($hexColor, $primary_color)) $primary_color = '#3b82f6';
    if (!preg_match($hexColor, $secondary_color)) $secondary_color = '#10b981';
    if ($gym_name === '') $gym_name = $tenant['gym_name'] ?? 'My Gym';
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $email = $tenant['email'] ?? '';

    $updateData = [
        'gym_name' => $gym_name,
        'email' => $email,
        'phone' => $phone,
        'address' => $address,
        'currency' => $currency,
        'timezone' => $timezone,
        'primary_color' => $primary_color,
        'secondary_color' => $secondary_color,
        'invoice_header' => $invoice_header,
        'invoice_footer' => $invoice_footer,
        'upi_id' => $upi_id
    ];

    // Robust Logo Upload Handling
    $uploadError = null;
    if (!empty($_FILES['logo']['name'])) {
        if ($_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif']; // SVG removed: can carry script (stored XSS)
            if (in_array($ext, $allowedExts) && @getimagesize($_FILES['logo']['tmp_name']) !== false) {
                $logoDir = __DIR__ . '/../uploads/logos';
                if (!is_dir($logoDir)) {
                    @mkdir($logoDir, 0777, true);
                }
                $logoFileName = 'logo_' . $tenantId . '_' . time() . '.' . $ext;
                $targetFile = $logoDir . '/' . $logoFileName;
                if (@move_uploaded_file($_FILES['logo']['tmp_name'], $targetFile)) {
                    $updateData['logo'] = $logoFileName;
                } else {
                    $uploadError = 'Failed to save logo file to server. Please check directory permissions.';
                }
            } else {
                $uploadError = 'Invalid image format. Allowed formats: PNG, JPG, JPEG, WEBP, GIF.';
            }
        } else if ($_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
            $uploadError = 'File upload failed with error code: ' . $_FILES['logo']['error'] . ' (File size might exceed PHP limit).';
        }
    }

    // Robust Payment QR Code Upload Handling
    if (!empty($_FILES['upi_qr']['name']) && $_FILES['upi_qr']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['upi_qr']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp']) && @getimagesize($_FILES['upi_qr']['tmp_name']) !== false) {
            $qrDir = __DIR__ . '/../uploads/qr';
            if (!is_dir($qrDir)) {
                @mkdir($qrDir, 0777, true);
            }
            $qrFileName = 'qr_' . $tenantId . '_' . time() . '.' . $ext;
            if (@move_uploaded_file($_FILES['upi_qr']['tmp_name'], $qrDir . '/' . $qrFileName)) {
                $updateData['upi_qr'] = $qrFileName;
            }
        }
    }

    DB::update('tenants', $updateData, 'id = ?', [$tenantId]);
    $_SESSION['tenant_id'] = $tenantId;
    Tenant::reset();
    $tenant = DB::fetchOne("SELECT * FROM tenants WHERE id = ?", [$tenantId]) ?: Tenant::getCurrent();

    Auth::auditLog('UPDATE_SETTINGS', 'Updated gym branding, logo and settings');
    
    if ($uploadError) {
        redirect('settings.php', 'error', $uploadError);
    } else {
        redirect('settings.php', 'success', 'Gym branding, logo and settings updated successfully!');
    }
}

// Handle Add Branch
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_branch'])) {
    Auth::verifyCsrf();

    $bQuota = Tenant::checkLimit('branches');
    if (!$bQuota['allowed']) {
        redirect('subscription.php', 'error', 'Branch limit reached for your active subscription plan. Please upgrade to add more branches.');
    }

    $branch_name = trim($_POST['branch_name'] ?? '');
    $branch_address = trim($_POST['branch_address'] ?? '');
    $branch_phone = trim($_POST['branch_phone'] ?? '');
    $branch_email = trim($_POST['branch_email'] ?? '');

    DB::insert('branches', [
        'tenant_id' => $tenantId,
        'branch_name' => $branch_name,
        'address' => $branch_address,
        'phone' => $branch_phone,
        'email' => $branch_email,
        'is_main' => 0,
        'status' => 'active'
    ]);

    Auth::auditLog('ADD_BRANCH', "Added new branch $branch_name");
    redirect('settings.php', 'success', "New branch '$branch_name' added successfully!");
}

$branches = DB::fetchAll("SELECT * FROM branches WHERE tenant_id = ? ORDER BY is_main DESC, id ASC", [$tenantId]);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/topbar.php';
?>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
    <!-- Branding Form -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <i class="fas fa-palette"></i>
                <span>Gym Branding & Identity</span>
            </div>
            <a href="subscription.php" class="btn btn-secondary btn-sm">
                <i class="fas fa-certificate"></i> Subscription Plan
            </a>
        </div>
        <div class="card-body">
            <form method="POST" action="" enctype="multipart/form-data">
                <?php echo Auth::csrfField(); ?>
                <input type="hidden" name="save_settings" value="1" />

                <!-- Logo Section -->
                <div style="background: var(--bg-app); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 16px; margin-bottom: 20px; display: flex; align-items: center; gap: 20px;">
                    <div style="width: 64px; height: 64px; border-radius: var(--radius-md); background: var(--bg-surface); border: 2px dashed var(--border-color); display: flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0;">
                        <?php if (!empty($tenant['logo'])): ?>
                            <img src="<?php echo str_starts_with($tenant['logo'], 'http') ? $tenant['logo'] : base_url('/uploads/logos/' . $tenant['logo']); ?>" style="width: 100%; height: 100%; object-fit: contain;" />
                        <?php else: ?>
                            <i class="fas fa-dumbbell" style="font-size: 1.8rem; color: var(--primary);"></i>
                        <?php endif; ?>
                    </div>
                    <div style="flex: 1;">
                        <label class="form-label" style="margin-bottom: 4px; font-weight: 700;">Upload Official Gym Logo</label>
                        <input type="file" name="logo" class="form-control" accept="image/*" style="font-size: 0.85rem;" />
                        <small style="color: var(--text-muted); font-size: 0.78rem;">Displayed on Mobile App, Web Portal, & Member Receipts. (PNG, JPG, WEBP, GIF)</small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Gym Business Name *</label>
                        <input type="text" name="gym_name" class="form-control" value="<?php echo e($tenant['gym_name']); ?>" required />
                    </div>
                    <div class="form-group">
                        <label class="form-label">Gym Subdomain / Slug</label>
                        <input type="text" class="form-control" value="<?php echo e($tenant['slug']); ?>" readonly style="background: var(--bg-app);" />
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Contact Email</label>
                        <input type="email" name="email" class="form-control" value="<?php echo e($tenant['email']); ?>" required />
                    </div>
                    <div class="form-group">
                        <label class="form-label">Contact Phone</label>
                        <input type="text" name="phone" class="form-control" value="<?php echo e($tenant['phone']); ?>" required />
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Currency Symbol *</label>
                        <select name="currency" id="currency-select" class="form-select">
                            <?php echo render_currency_options($tenant['currency']); ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Timezone</label>
                        <select name="timezone" id="timezone-select" class="form-select">
                            <?php echo render_timezone_options($tenant['timezone']); ?>
                        </select>
                    </div>
                </div>

                <div style="margin-bottom: 16px;">
                    <button type="button" id="detect-location-btn" class="btn btn-secondary btn-sm" onclick="detectMyLocation()" style="display: inline-flex; align-items: center; gap: 6px;">
                        <i class="fas fa-map-marker-alt"></i> Detect My Location
                    </button>
                    <span id="detect-location-status" style="font-size: 0.8rem; color: var(--text-muted); margin-left: 8px;"></span>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Primary Brand Color</label>
                        <div style="display: flex; gap: 8px; align-items: center;">
                            <input type="color" name="primary_color" id="primary-color-picker" value="<?php echo e($tenant['primary_color'] ?? '#3b82f6'); ?>" style="width: 44px; height: 38px; padding: 2px; border: 1px solid var(--border-color); border-radius: var(--radius-sm); cursor: pointer;" oninput="document.getElementById('primary-color-text').value = this.value" />
                            <input type="text" id="primary-color-text" class="form-control" value="<?php echo e($tenant['primary_color'] ?? '#3b82f6'); ?>" oninput="document.getElementById('primary-color-picker').value = this.value" />
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Secondary Brand Color</label>
                        <div style="display: flex; gap: 8px; align-items: center;">
                            <input type="color" name="secondary_color" id="secondary-color-picker" value="<?php echo e($tenant['secondary_color'] ?? '#10b981'); ?>" style="width: 44px; height: 38px; padding: 2px; border: 1px solid var(--border-color); border-radius: var(--radius-sm); cursor: pointer;" oninput="document.getElementById('secondary-color-text').value = this.value" />
                            <input type="text" id="secondary-color-text" class="form-control" value="<?php echo e($tenant['secondary_color'] ?? '#10b981'); ?>" oninput="document.getElementById('secondary-color-picker').value = this.value" />
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Gym Physical Address</label>
                    <input type="text" name="address" class="form-control" value="<?php echo e($tenant['address']); ?>" />
                </div>

                <div class="form-group">
                    <label class="form-label">Invoice & Receipt Header Title</label>
                    <input type="text" name="invoice_header" class="form-control" value="<?php echo e($tenant['invoice_header'] ?? ''); ?>" placeholder="e.g. FITISIFY FITNESS & WELLNESS" />
                </div>

                <div class="form-row" style="margin-top: 16px; padding: 16px; background: rgba(59, 130, 246, 0.05); border: 1px solid rgba(59, 130, 246, 0.2); border-radius: 8px;">
                    <div style="width: 100%; margin-bottom: 12px;">
                        <h4 style="margin: 0 0 4px 0; font-size: 0.95rem; font-weight: 700; color: var(--text-heading);">
                            <i class="fas fa-qrcode" style="color: var(--primary-color);"></i> Direct Gym Owner UPI Payment Settings (For Member Renewals)
                        </h4>
                        <p style="margin: 0; font-size: 0.8rem; color: var(--text-muted);">
                            When members renew their membership from the Mobile App or Web Portal, funds go directly to this UPI ID.
                        </p>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label class="form-label">Gym Owner UPI ID / VPA *</label>
                        <input type="text" name="upi_id" class="form-control" value="<?php echo e($tenant['upi_id'] ?? ''); ?>" placeholder="e.g. yourname@upi or 9876543210@paytm" />
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label class="form-label">Upload Custom Payment QR Image (Optional)</label>
                        <input type="file" name="upi_qr" class="form-control" accept="image/*" />
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Invoice & Receipt Footer Note</label>
                    <textarea name="invoice_footer" class="form-control" rows="2"><?php echo e($tenant['invoice_footer'] ?? ''); ?></textarea>
                </div>

                <div style="margin-top: 24px; display: flex; justify-content: flex-end;">
                    <button type="submit" name="save_settings" value="1" class="btn btn-primary btn-lg">
                        <i class="fas fa-save"></i> Save Brand Configuration
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Branch Management Card -->
    <div>
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <i class="fas fa-code-branch"></i>
                    <span>Gym Branches</span>
                </div>
                <button type="button" class="btn btn-primary btn-sm" onclick="App.openModal('add-branch-modal')">
                    <i class="fas fa-plus"></i> Add Branch
                </button>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Branch Name</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($branches as $b): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 700; color: var(--text-main);">
                                            <?php echo e($b['branch_name']); ?>
                                            <?php if ($b['is_main']): ?>
                                                <span class="status-badge badge-info" style="font-size: 0.7rem;">Main</span>
                                            <?php endif; ?>
                                        </div>
                                        <div style="font-size: 0.8rem; color: var(--text-muted);"><?php echo e($b['phone']); ?></div>
                                    </td>
                                    <td><?php echo status_badge($b['status']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Service Packages Card -->
        <div class="card" style="margin-top: 24px;">
            <div class="card-header">
                <div class="card-title">
                    <i class="fas fa-tags"></i>
                    <span>Membership Packages</span>
                </div>
                <a href="rates.php" class="btn btn-primary btn-sm">
                    <i class="fas fa-cog"></i> Configure Rates
                </a>
            </div>
            <div class="card-body">
                <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 12px;">
                    Manage monthly membership plans, custom fitness packages, and pricing tiers for your members and mobile app renewals.
                </p>
                <a href="rates.php" class="btn btn-secondary btn-sm" style="width: 100%; text-align: center; justify-content: center;">
                    <i class="fas fa-external-link-alt"></i> Open Packages & Rates Manager
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Add Branch Modal -->
<div class="modal-backdrop" id="add-branch-modal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="card-title">
                <i class="fas fa-building"></i>
                <span>Add New Gym Branch</span>
            </div>
            <button type="button" class="btn-icon" onclick="App.closeModal('add-branch-modal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" action="">
            <?php echo Auth::csrfField(); ?>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Branch Name *</label>
                    <input type="text" name="branch_name" class="form-control" placeholder="e.g. Westside Branch" required />
                </div>
                <div class="form-group">
                    <label class="form-label">Address</label>
                    <input type="text" name="branch_address" class="form-control" placeholder="Street Address, City" />
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Branch Phone</label>
                        <input type="text" name="branch_phone" class="form-control" placeholder="Contact number" />
                    </div>
                    <div class="form-group">
                        <label class="form-label">Branch Email</label>
                        <input type="email" name="branch_email" class="form-control" placeholder="branch@gym.com" />
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="App.closeModal('add-branch-modal')">Cancel</button>
                <button type="submit" name="add_branch" value="1" class="btn btn-primary">Create Branch</button>
            </div>
        </form>
    </div>
</div>

<script src="<?php echo base_url('/assets/js/geo-currency.js'); ?>"></script>
<script>
function detectMyLocation() {
    var btn = document.getElementById('detect-location-btn');
    var status = document.getElementById('detect-location-status');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Detecting...';
    status.textContent = '';

    GeoCurrency.detect({
        currencySelectId: 'currency-select',
        timezoneSelectId: 'timezone-select',
        overrideExisting: true
    });

    // Re-enable button after detection completes
    setTimeout(function() {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-map-marker-alt"></i> Detect My Location';
        status.textContent = '✓ Detection complete';
        status.style.color = '#10b981';
    }, 2000);
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
