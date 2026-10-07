<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helpers.php';
Auth::requireAuth('super_admin');

$page = 'super_plans';
$pageTitle = 'Subscription Plans & Tiers';
$pageSubtitle = 'Configure SaaS subscription packages, pricing, and resource quotas';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();

    if (isset($_POST['save_plan'])) {
        $name = trim($_POST['name'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $billing_cycle = $_POST['billing_cycle'] ?? 'monthly';
        $max_members = (int)($_POST['max_members'] ?? 100);
        $max_staff = (int)($_POST['max_staff'] ?? 5);
        $max_branches = (int)($_POST['max_branches'] ?? 1);
        $id = (int)($_POST['id'] ?? 0);
        if (!in_array($billing_cycle, ['trial', 'monthly', 'quarterly', 'yearly', 'custom'], true)) {
            $billing_cycle = 'monthly';
        }
        // trial_days is not on the form: keep the stored value when editing instead of resetting it to 0
        if (isset($_POST['trial_days'])) {
            $trial_days = max(0, (int)$_POST['trial_days']);
        } elseif ($id > 0) {
            $trial_days = (int)DB::fetchValue("SELECT trial_days FROM subscription_plans WHERE id = ?", [$id]);
        } else {
            $trial_days = 14;
        }

        // "price" is the price of ONE billing cycle. Derive per-month and per-year prices from it
        // (yearly default = 10 x monthly, i.e. standard 2-month SaaS discount).
        if ($billing_cycle === 'trial') {
            $price = 0.0;
            $price_monthly = 0.0;
            $price_yearly = 0.0;
        } elseif ($billing_cycle === 'yearly') {
            $price_monthly = round($price / 12, 2);
            $price_yearly = $price;
        } elseif ($billing_cycle === 'quarterly') {
            $price_monthly = round($price / 3, 2);
            $price_yearly = round($price_monthly * 10, 2);
        } else {
            $price_monthly = $price;
            $price_yearly = round($price * 10, 2);
        }

        if (empty($name)) {
            set_flash('error', 'Package name is required.');
        } elseif ($price < 0 || ($billing_cycle !== 'trial' && $price <= 0)) {
            set_flash('error', 'Paid plans must have a price greater than zero (use the Trial cycle for free plans).');
        } else {
            if ($id > 0) {
                DB::update('subscription_plans', [
                    'name' => $name,
                    'price' => $price,
                    'price_monthly' => $price_monthly,
                    'price_yearly' => $price_yearly,
                    'billing_cycle' => $billing_cycle,
                    'max_members' => $max_members,
                    'max_staff' => $max_staff,
                    'max_branches' => $max_branches,
                    'trial_days' => $trial_days
                ], 'id = ?', [$id]);
                Auth::auditLog('UPDATE_SAAS_PLAN', "Updated plan '$name' (#$id)");
                set_flash('success', "Subscription package '$name' updated successfully.");
            } else {
                $baseSlug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
                $slug = $baseSlug ?: 'plan';
                
                // Ensure unique slug
                $existing = DB::fetchOne("SELECT id FROM subscription_plans WHERE slug = ?", [$slug]);
                if ($existing) {
                    $slug = $baseSlug . '-' . time();
                }

                $newId = DB::insert('subscription_plans', [
                    'name' => $name,
                    'slug' => $slug,
                    'price' => $price,
                    'price_monthly' => $price_monthly,
                    'price_yearly' => $price_yearly,
                    'billing_cycle' => $billing_cycle,
                    'max_members' => $max_members,
                    'max_staff' => $max_staff,
                    'max_branches' => $max_branches,
                    'trial_days' => $trial_days,
                    'is_active' => 1
                ]);

                Auth::auditLog('CREATE_SAAS_PLAN', "Created plan '$name' (#$newId)");
                set_flash('success', "New subscription package '$name' created successfully!");
            }
        }
        redirect(base_url('/superadmin/plans.php'));
    }
}

// Handle Delete Plan (POST + CSRF only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    Auth::verifyCsrf();
    $delId = (int)$_POST['delete_id'];
    $gymCount = (int)DB::fetchValue("SELECT COUNT(*) FROM tenants WHERE subscription_plan_id = ?", [$delId]);
    $paymentCount = (int)DB::fetchValue("SELECT COUNT(*) FROM saas_payments WHERE plan_id = ?", [$delId]);
    $pendingRegs = (int)DB::fetchValue("SELECT COUNT(*) FROM pending_onboardings WHERE plan_id = ? AND status = 'pending'", [$delId]);
    if ($gymCount > 0) {
        set_flash('error', "Cannot delete this plan because $gymCount active gym(s) are currently subscribed to it.");
    } elseif ($paymentCount > 0 || $pendingRegs > 0) {
        // Keep billing history intact: deactivate instead of deleting
        DB::update('subscription_plans', ['is_active' => 0], 'id = ?', [$delId]);
        Auth::auditLog('DEACTIVATE_SAAS_PLAN', "Deactivated plan #$delId (has payment history)");
        set_flash('success', "Plan has payment history, so it was deactivated instead of deleted.");
    } else {
        DB::delete('subscription_plans', 'id = ?', [$delId]);
        Auth::auditLog('DELETE_SAAS_PLAN', "Deleted plan #$delId");
        set_flash('success', "Subscription package deleted successfully.");
    }
    redirect(base_url('/superadmin/plans.php'));
}

$plans = DB::fetchAll("SELECT p.*, COALESCE(NULLIF(p.price_monthly, 0), NULLIF(p.price, 0), 0) as display_price, (SELECT COUNT(*) FROM tenants t WHERE t.subscription_plan_id = p.id) as gym_count FROM subscription_plans p ORDER BY display_price ASC, p.id ASC");

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/topbar.php';
?>

<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="fas fa-tags"></i>
            <span>SaaS Subscription Packages</span>
        </div>
        <button type="button" class="btn btn-primary btn-sm" onclick="newPlan()">
            <i class="fas fa-plus"></i> Add New Plan
        </button>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Package Name</th>
                        <th>Price (₹ INR)</th>
                        <th>Billing Cycle</th>
                        <th>Max Members</th>
                        <th>Max Staff</th>
                        <th>Max Branches</th>
                        <th>Active Gyms</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($plans)): ?>
                        <tr>
                            <td colspan="8" style="text-align:center; padding: 40px; color: var(--text-muted);">
                                No subscription plans found. Click "Add New Plan" to create your first package.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($plans as $p): ?>
                            <tr>
                                <td>
                                    <strong style="font-size: 1rem; color: var(--text-main);"><?php echo e($p['name']); ?></strong>
                                    <div style="font-size: 0.75rem; color: var(--text-muted); font-family: monospace;"><?php echo e($p['slug']); ?></div>
                                </td>
                                <td>
                                    <span style="font-size: 1.15rem; font-weight: 700; color: var(--primary);">
                                        ₹<?php echo number_format((float)$p['display_price'], 2); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge badge-info"><?php echo ucfirst($p['billing_cycle']); ?></span>
                                </td>
                                <td><strong><?php echo number_format($p['max_members']); ?></strong> members</td>
                                <td><strong><?php echo number_format($p['max_staff']); ?></strong> staff</td>
                                <td><strong><?php echo number_format($p['max_branches']); ?></strong> branch(es)</td>
                                <td>
                                    <span class="status-badge badge-success">
                                        <strong><?php echo $p['gym_count']; ?></strong> gyms
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 6px;">
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="editPlan(<?php echo e(json_encode($p)); ?>)">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <?php if ((int)$p['gym_count'] === 0): ?>
                                            <form method="POST" action="" style="margin: 0;" onsubmit="return confirm(<?php echo e(json_encode("Are you sure you want to delete the plan '" . $p['name'] . "'?")); ?>);">
                                                <?php echo Auth::csrfField(); ?>
                                                <input type="hidden" name="delete_id" value="<?php echo (int)$p['id']; ?>" />
                                                <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                            </form>
                                        <?php endif; ?>
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

<!-- Plan Modal -->
<div class="modal-backdrop" id="plan-modal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="card-title">
                <i class="fas fa-tag"></i>
                <span id="plan-modal-title">Edit Subscription Plan</span>
            </div>
            <button type="button" class="btn-icon" onclick="App.closeModal('plan-modal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" action="">
            <?php echo Auth::csrfField(); ?>
            <input type="hidden" name="id" id="plan-id" value="0" />
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Plan Name *</label>
                    <input type="text" name="name" id="plan-name" class="form-control" placeholder="e.g. Starter Growth, Pro Elite" required />
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Price (₹ INR) *</label>
                        <input type="number" step="0.01" name="price" id="plan-price" class="form-control" placeholder="e.g. 999.00" required />
                    </div>
                    <div class="form-group">
                        <label class="form-label">Billing Cycle *</label>
                        <select name="billing_cycle" id="plan-cycle" class="form-select">
                            <option value="monthly">Monthly</option>
                            <option value="quarterly">Quarterly</option>
                            <option value="yearly">Yearly</option>
                            <option value="trial">Trial</option>
                            <option value="custom">Custom</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Max Members *</label>
                        <input type="number" name="max_members" id="plan-members" class="form-control" placeholder="e.g. 150" required />
                    </div>
                    <div class="form-group">
                        <label class="form-label">Max Staff Accounts *</label>
                        <input type="number" name="max_staff" id="plan-staff" class="form-control" placeholder="e.g. 5" required />
                    </div>
                    <div class="form-group">
                        <label class="form-label">Max Branches *</label>
                        <input type="number" name="max_branches" id="plan-branches" class="form-control" placeholder="e.g. 1" required />
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="App.closeModal('plan-modal')">Cancel</button>
                <button type="submit" name="save_plan" class="btn btn-primary">Save Package</button>
            </div>
        </form>
    </div>
</div>

<script>
function newPlan() {
    document.getElementById('plan-id').value = '0';
    document.getElementById('plan-name').value = '';
    document.getElementById('plan-price').value = '999.00';
    document.getElementById('plan-cycle').value = 'monthly';
    document.getElementById('plan-members').value = '150';
    document.getElementById('plan-staff').value = '5';
    document.getElementById('plan-branches').value = '1';
    document.getElementById('plan-modal-title').innerText = 'Add New Subscription Plan';
    App.openModal('plan-modal');
}

function editPlan(p) {
    document.getElementById('plan-id').value = p.id;
    document.getElementById('plan-name').value = p.name;
    // Price field is the price of ONE billing cycle (see save handler)
    var cyc = p.billing_cycle || 'monthly';
    var pm = parseFloat(p.price_monthly || 0), py = parseFloat(p.price_yearly || 0);
    var cyclePrice = cyc === 'yearly' ? py : (cyc === 'quarterly' ? Math.round(pm * 3 * 100) / 100 : (cyc === 'trial' ? 0 : pm));
    document.getElementById('plan-price').value = (cyclePrice || p.price || p.display_price || 0).toString();
    document.getElementById('plan-cycle').value = p.billing_cycle || 'monthly';
    document.getElementById('plan-members').value = p.max_members;
    document.getElementById('plan-staff').value = p.max_staff;
    document.getElementById('plan-branches').value = p.max_branches;
    document.getElementById('plan-modal-title').innerText = 'Edit ' + p.name;
    App.openModal('plan-modal');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
