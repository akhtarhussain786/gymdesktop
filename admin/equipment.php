<?php
require_once __DIR__ . '/../core/auth.php';
Auth::requireAuth(['gym_admin', 'staff']);

$page = 'equipment';
$pageTitle = 'Gym Equipment Inventory';
$pageSubtitle = 'Manage gym workout machines, free weights, vendors, and operational statuses';
$tenantId = Tenant::getTenantId();

// Handle Delete Equipment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    Auth::verifyCsrf();
    $deleteId = (int)$_POST['delete'];
    DB::delete('equipment', 'id = ? AND tenant_id = ?', [$deleteId, $tenantId]);
    redirect('equipment.php', 'success', 'Equipment item removed.');
}

$equipments = DB::fetchAll("SELECT * FROM equipment WHERE tenant_id = ? ORDER BY id DESC", [$tenantId]);

// Summary
$totalEquipCount = (int)DB::fetchValue("SELECT SUM(quantity) FROM equipment WHERE tenant_id = ?", [$tenantId]);
$totalEquipValue = (float)DB::fetchValue("SELECT SUM(amount * quantity) FROM equipment WHERE tenant_id = ?", [$tenantId]);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/topbar.php';
?>

<!-- Equipment Summary Stats -->
<div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
    <div class="stat-card stat-info">
        <div class="stat-info">
            <h3>Total Equipment Units</h3>
            <div class="stat-value"><?php echo $totalEquipCount; ?></div>
            <div class="stat-meta">Across <?php echo count($equipments); ?> unique machine types</div>
        </div>
        <div class="stat-icon">
            <i class="fas fa-dumbbell"></i>
        </div>
    </div>

    <div class="stat-card stat-success">
        <div class="stat-info">
            <h3>Total Asset Valuation</h3>
            <div class="stat-value"><?php echo format_currency($totalEquipValue); ?></div>
            <div class="stat-meta">Gym equipment assets</div>
        </div>
        <div class="stat-icon">
            <i class="fas fa-coins"></i>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="fas fa-tools"></i>
            <span>Equipment List (<?php echo count($equipments); ?>)</span>
        </div>
        <div style="display: flex; gap: 10px;">
            <input type="text" placeholder="Filter equipment..." data-table-search="#equip-table" class="form-control" style="width: 200px;" />
            <a href="equipment-entry.php" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> Add Equipment
            </a>
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table" id="equip-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Equipment Name</th>
                        <th>Quantity</th>
                        <th>Unit Price</th>
                        <th>Total Value</th>
                        <th>Vendor / Contact</th>
                        <th>Status</th>
                        <th>Purchase Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($equipments)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                No equipment items recorded yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($equipments as $idx => $eq): ?>
                            <tr>
                                <td><?php echo $idx + 1; ?></td>
                                <td>
                                    <strong style="color: var(--text-main);"><?php echo e($eq['name']); ?></strong>
                                    <?php if (!empty($eq['description'])): ?>
                                        <div style="font-size: 0.78rem; color: var(--text-muted);"><?php echo e($eq['description']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?php echo $eq['quantity']; ?></strong> pcs</td>
                                <td><?php echo format_currency($eq['amount']); ?></td>
                                <td><strong style="color: var(--primary);"><?php echo format_currency($eq['amount'] * $eq['quantity']); ?></strong></td>
                                <td>
                                    <div><?php echo e($eq['vendor']); ?></div>
                                    <div style="font-size: 0.78rem; color: var(--text-muted);"><?php echo e($eq['contact']); ?></div>
                                </td>
                                <td><span class="status-badge badge-success"><?php echo e($eq['status'] ?? 'Operational'); ?></span></td>
                                <td><?php echo format_date($eq['date']); ?></td>
                                <td>
                                    <div style="display: flex; gap: 6px;">
                                        <a href="edit-equipmentform.php?id=<?php echo $eq['id']; ?>" class="btn btn-secondary btn-sm" title="Edit Equipment">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form method="POST" action="equipment.php" style="display:inline;"><?php echo Auth::csrfField(); ?><button type="submit" name="delete" value="<?php echo (int)$eq['id']; ?>" class="btn btn-secondary btn-sm" title="Delete Equipment" onclick="return confirm('Delete this equipment entry?')">
                                            <i class="fas fa-trash" style="color: var(--danger);"></i>
                                        </button></form>
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

<?php include __DIR__ . '/../includes/footer.php'; ?>
