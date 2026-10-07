<?php
require_once __DIR__ . '/../core/auth.php';
Auth::requireAuth(['gym_admin', 'staff']);

$page = 'equipment';
$pageTitle = 'Edit Equipment';
$pageSubtitle = 'Update equipment details, quantity, vendor, or operational status';
$tenantId = Tenant::getTenantId();
$equipId = (int)($_GET['id'] ?? 0);

$equip = DB::fetchOne("SELECT * FROM equipment WHERE id = ? AND tenant_id = ?", [$equipId, $tenantId]);
if (!$equip) {
    redirect('equipment.php', 'error', 'Equipment item not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_equipment'])) {
    Auth::verifyCsrf();

    $name = trim($_POST['name'] ?? '');
    $amount = (float)$_POST['amount'];
    $quantity = (int)$_POST['quantity'];
    $vendor = trim($_POST['vendor'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $date = $_POST['date'] ?? date('Y-m-d');
    $status = $_POST['status'] ?? 'Operational';

    DB::update('equipment', [
        'name' => $name,
        'amount' => $amount,
        'quantity' => $quantity,
        'vendor' => $vendor,
        'description' => $description,
        'address' => $address,
        'contact' => $contact,
        'date' => $date,
        'status' => $status
    ], 'id = ? AND tenant_id = ?', [$equipId, $tenantId]);

    Auth::auditLog('UPDATE_EQUIPMENT', "Updated equipment $name (#$equipId)");
    redirect('equipment.php', 'success', "Equipment '$name' updated successfully!");
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/topbar.php';
?>

<div class="card" style="max-width: 800px; margin: 0 auto;">
    <div class="card-header">
        <div class="card-title">
            <i class="fas fa-edit"></i>
            <span>Edit Equipment: <?php echo e($equip['name']); ?></span>
        </div>
        <a href="equipment.php" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <?php echo Auth::csrfField(); ?>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Equipment Name *</label>
                    <input type="text" name="name" class="form-control" value="<?php echo e($equip['name']); ?>" required />
                </div>
                <div class="form-group">
                    <label class="form-label">Operational Status</label>
                    <select name="status" class="form-select">
                        <option value="Operational" <?php echo ($equip['status'] ?? '') === 'Operational' ? 'selected' : ''; ?>>Operational / In Use</option>
                        <option value="Under Maintenance" <?php echo ($equip['status'] ?? '') === 'Under Maintenance' ? 'selected' : ''; ?>>Under Maintenance</option>
                        <option value="Out of Order" <?php echo ($equip['status'] ?? '') === 'Out of Order' ? 'selected' : ''; ?>>Out of Order</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Unit Cost *</label>
                    <input type="number" step="0.01" name="amount" class="form-control" value="<?php echo e($equip['amount']); ?>" required />
                </div>
                <div class="form-group">
                    <label class="form-label">Quantity *</label>
                    <input type="number" name="quantity" class="form-control" value="<?php echo e($equip['quantity']); ?>" required />
                </div>
                <div class="form-group">
                    <label class="form-label">Purchase Date *</label>
                    <input type="date" name="date" class="form-control" value="<?php echo e($equip['date']); ?>" required />
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Vendor Name</label>
                    <input type="text" name="vendor" class="form-control" value="<?php echo e($equip['vendor']); ?>" />
                </div>
                <div class="form-group">
                    <label class="form-label">Vendor Phone</label>
                    <input type="text" name="contact" class="form-control" value="<?php echo e($equip['contact']); ?>" />
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="2"><?php echo e($equip['description']); ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Vendor Address</label>
                <input type="text" name="address" class="form-control" value="<?php echo e($equip['address']); ?>" />
            </div>

            <div style="margin-top: 24px; display: flex; justify-content: flex-end; gap: 12px;">
                <a href="equipment.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" name="update_equipment" value="1" class="btn btn-primary btn-lg">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
