<?php
require_once __DIR__ . '/../core/auth.php';
Auth::requireAuth(['gym_admin']);

$page = 'rates';
$pageTitle = 'Service Packages & Membership Rates';
$pageSubtitle = 'Configure membership packages, recurring monthly fees, and service offerings for your gym';
$tenantId = Tenant::getTenantId();
$tenant = Tenant::getCurrent();

// Ensure standard defaults exist
Tenant::ensureDefaultRates($tenantId);

// Handle Add Rate / Package
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_rate'])) {
    Auth::verifyCsrf();

    $name = trim($_POST['name'] ?? '');
    $charge = round((float)($_POST['charge'] ?? 0), 2);

    if (empty($name)) {
        redirect('rates.php', 'error', 'Package name is required.');
    }
    if ($charge <= 0) {
        redirect('rates.php', 'error', 'Monthly charge must be greater than zero.');
    }

    // Check duplicate name for this tenant
    $exists = DB::fetchValue("SELECT id FROM rates WHERE tenant_id = ? AND LOWER(name) = LOWER(?)", [$tenantId, $name]);
    if ($exists) {
        redirect('rates.php', 'error', "A package named '$name' already exists.");
    }

    DB::insert('rates', [
        'tenant_id' => $tenantId,
        'name' => $name,
        'charge' => $charge
    ]);

    Auth::auditLog('ADD_RATE', "Created service package '$name' with rate $charge");
    redirect('rates.php', 'success', "Service package '$name' added successfully.");
}

// Handle Update Rate / Package
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_rate'])) {
    Auth::verifyCsrf();

    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $charge = round((float)($_POST['charge'] ?? 0), 2);

    if ($id <= 0 || empty($name)) {
        redirect('rates.php', 'error', 'Invalid package details.');
    }
    if ($charge <= 0) {
        redirect('rates.php', 'error', 'Monthly charge must be greater than zero.');
    }

    $existing = DB::fetchOne("SELECT * FROM rates WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);
    if (!$existing) {
        redirect('rates.php', 'error', 'Package not found.');
    }

    // Check duplicate
    $dup = DB::fetchValue("SELECT id FROM rates WHERE tenant_id = ? AND LOWER(name) = LOWER(?) AND id <> ?", [$tenantId, $name, $id]);
    if ($dup) {
        redirect('rates.php', 'error', "Another package named '$name' already exists.");
    }

    DB::update('rates', [
        'name' => $name,
        'charge' => $charge
    ], 'id = ? AND tenant_id = ?', [$id, $tenantId]);

    // Also update existing members' services name if name was changed
    if ($existing['name'] !== $name) {
        DB::update('members', ['services' => $name], 'tenant_id = ? AND services = ?', [$tenantId, $existing['name']]);
    }

    Auth::auditLog('UPDATE_RATE', "Updated service package #$id to '$name' ($charge)");
    redirect('rates.php', 'success', "Service package '$name' updated successfully.");
}

// Handle Delete Rate / Package
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_rate'])) {
    Auth::verifyCsrf();

    $id = (int)($_POST['delete_rate'] ?? 0);
    $rateRow = DB::fetchOne("SELECT * FROM rates WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);
    if (!$rateRow) {
        redirect('rates.php', 'error', 'Package not found.');
    }

    $memberCount = (int)DB::fetchValue("SELECT COUNT(*) FROM members WHERE tenant_id = ? AND services = ?", [$tenantId, $rateRow['name']]);
    if ($memberCount > 0) {
        redirect('rates.php', 'error', "Cannot delete package '{$rateRow['name']}' because $memberCount active member(s) are currently enrolled. Reassign them first.");
    }

    DB::delete('rates', 'id = ? AND tenant_id = ?', [$id, $tenantId]);
    Auth::auditLog('DELETE_RATE', "Deleted service package '{$rateRow['name']}' (#$id)");
    redirect('rates.php', 'success', "Package '{$rateRow['name']}' deleted successfully.");
}

$rates = DB::fetchAll("SELECT * FROM rates WHERE tenant_id = ? ORDER BY charge ASC, id ASC", [$tenantId]);

// Calculate enrollment counts
$ratesWithCounts = [];
$totalEnrolled = 0;
$totalSumRates = 0;
foreach ($rates as $r) {
    $count = (int)DB::fetchValue("SELECT COUNT(*) FROM members WHERE tenant_id = ? AND services = ?", [$tenantId, $r['name']]);
    $r['member_count'] = $count;
    $totalEnrolled += $count;
    $totalSumRates += (float)$r['charge'];
    $ratesWithCounts[] = $r;
}
$avgRate = count($rates) > 0 ? round($totalSumRates / count($rates), 2) : 0;

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/topbar.php';
?>

<!-- Metric Cards -->
<div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-bottom: 24px;">
    <div class="stat-card stat-primary">
        <div class="stat-info">
            <h3>Active Packages</h3>
            <div class="stat-value"><?php echo count($rates); ?></div>
            <div class="stat-meta">Configured membership tiers</div>
        </div>
        <div class="stat-icon">
            <i class="fas fa-tags"></i>
        </div>
    </div>

    <div class="stat-card stat-success">
        <div class="stat-info">
            <h3>Enrolled Members</h3>
            <div class="stat-value"><?php echo $totalEnrolled; ?></div>
            <div class="stat-meta">Active gym members on plans</div>
        </div>
        <div class="stat-icon">
            <i class="fas fa-users"></i>
        </div>
    </div>

    <div class="stat-card stat-warning">
        <div class="stat-info">
            <h3>Average Monthly Rate</h3>
            <div class="stat-value"><?php echo format_currency($avgRate); ?></div>
            <div class="stat-meta">Across all service plans</div>
        </div>
        <div class="stat-icon">
            <i class="fas fa-coins"></i>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="fas fa-layer-group"></i>
            <span>Gym Service Packages & Monthly Rates</span>
        </div>
        <button type="button" class="btn btn-primary btn-sm" onclick="App.openModal('add-rate-modal')">
            <i class="fas fa-plus"></i> Add New Package
        </button>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Package / Service Name</th>
                        <th>Monthly Rate</th>
                        <th>Quarterly (3 Mo)</th>
                        <th>Annual (12 Mo)</th>
                        <th>Enrolled Members</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ratesWithCounts)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 32px; color: var(--text-muted);">
                                <i class="fas fa-tags" style="font-size: 2rem; margin-bottom: 10px; display: block; opacity: 0.5;"></i>
                                No service packages found. Click <strong>Add New Package</strong> above to configure membership tiers.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($ratesWithCounts as $idx => $r): ?>
                            <?php 
                                $monthly = (float)$r['charge']; 
                                $quarterly = round($monthly * 3 * 0.95, 2); // 5% discount
                                $annual = round($monthly * 12 * 0.85, 2); // 15% discount
                            ?>
                            <tr>
                                <td style="color: var(--text-muted); font-weight: 700; width: 40px;"><?php echo $idx + 1; ?></td>
                                <td>
                                    <div style="font-weight: 800; font-size: 0.95rem; color: var(--text-main);">
                                        <?php echo e($r['name']); ?>
                                    </div>
                                    <div style="font-size: 0.78rem; color: var(--text-muted);">
                                        Standard Plan ID: #<?php echo $r['id']; ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 800; color: var(--primary); font-size: 1.05rem;">
                                        <?php echo format_currency($monthly); ?><span style="font-size: 0.75rem; color: var(--text-muted); font-weight: normal;">/mo</span>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 600; color: var(--text-main); font-size: 0.9rem;">
                                        <?php echo format_currency($quarterly); ?>
                                    </div>
                                    <span style="font-size: 0.72rem; color: #10b981; font-weight: 700;">5% Off</span>
                                </td>
                                <td>
                                    <div style="font-weight: 600; color: var(--text-main); font-size: 0.9rem;">
                                        <?php echo format_currency($annual); ?>
                                    </div>
                                    <span style="font-size: 0.72rem; color: #10b981; font-weight: 700;">15% Off (Best Value)</span>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $r['member_count'] > 0 ? 'badge-success' : 'badge-secondary'; ?>">
                                        <i class="fas fa-user-check"></i> <?php echo $r['member_count']; ?> member<?php echo $r['member_count'] === 1 ? '' : 's'; ?>
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <div style="display: inline-flex; gap: 8px;">
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="editRate(<?php echo htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8'); ?>)">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete package \'<?php echo addslashes($r['name']); ?>\'?');">
                                            <?php echo Auth::csrfField(); ?>
                                            <input type="hidden" name="delete_rate" value="<?php echo $r['id']; ?>" />
                                            <button type="submit" class="btn btn-danger btn-sm" <?php echo $r['member_count'] > 0 ? 'disabled title="Reassign enrolled members first"' : ''; ?>>
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Package Modal -->
<div class="modal-backdrop" id="add-rate-modal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="card-title">
                <i class="fas fa-tag"></i>
                <span>Add Membership Service Package</span>
            </div>
            <button type="button" class="btn-icon" onclick="App.closeModal('add-rate-modal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" action="">
            <?php echo Auth::csrfField(); ?>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Package Name *</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Strength & Conditioning, Yoga & Cardio" required />
                </div>
                <div class="form-group">
                    <label class="form-label">Monthly Rate (<?php echo $tenant['currency']; ?>) *</label>
                    <input type="number" step="0.01" min="1" name="charge" class="form-control" placeholder="e.g. 500.00" required />
                    <small style="color: var(--text-muted); font-size: 0.78rem;">Multi-month packages (3, 6, 12 months) automatically compute discounted totals from this rate.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="App.closeModal('add-rate-modal')">Cancel</button>
                <button type="submit" name="add_rate" value="1" class="btn btn-primary">Create Package</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Package Modal -->
<div class="modal-backdrop" id="edit-rate-modal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="card-title">
                <i class="fas fa-edit"></i>
                <span>Edit Membership Package</span>
            </div>
            <button type="button" class="btn-icon" onclick="App.closeModal('edit-rate-modal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" action="">
            <?php echo Auth::csrfField(); ?>
            <input type="hidden" name="id" id="edit-rate-id" />
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Package Name *</label>
                    <input type="text" name="name" id="edit-rate-name" class="form-control" required />
                </div>
                <div class="form-group">
                    <label class="form-label">Monthly Rate (<?php echo $tenant['currency']; ?>) *</label>
                    <input type="number" step="0.01" min="1" name="charge" id="edit-rate-charge" class="form-control" required />
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="App.closeModal('edit-rate-modal')">Cancel</button>
                <button type="submit" name="update_rate" value="1" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function editRate(rate) {
    document.getElementById('edit-rate-id').value = rate.id;
    document.getElementById('edit-rate-name').value = rate.name;
    document.getElementById('edit-rate-charge').value = rate.charge;
    App.openModal('edit-rate-modal');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
