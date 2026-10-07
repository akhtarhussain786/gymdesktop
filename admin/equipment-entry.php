<?php
require_once __DIR__ . '/../core/auth.php';
Auth::requireAuth(['gym_admin', 'staff']);

$page = 'equipment';
$pageTitle = 'Add Equipment / Asset';
$pageSubtitle = 'Record new gym workout equipment or fitness machinery';
$tenantId = Tenant::getTenantId();

$error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_equipment'])) {
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

    if (empty($name)) {
        $error = "Equipment name is required.";
    } else {
        DB::insert('equipment', [
            'tenant_id' => $tenantId,
            'name' => $name,
            'amount' => $amount,
            'quantity' => $quantity,
            'vendor' => $vendor,
            'description' => $description,
            'address' => $address,
            'contact' => $contact,
            'date' => $date,
            'status' => $status
        ]);

        Auth::auditLog('ADD_EQUIPMENT', "Added equipment $name ($quantity units)");
        redirect('equipment.php', 'success', "Equipment '$name' added successfully!");
    }
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/topbar.php';
?>

<div class="card" style="max-width: 800px; margin: 0 auto;">
    <div class="card-header">
        <div class="card-title">
            <i class="fas fa-dumbbell"></i>
            <span>New Equipment Entry</span>
        </div>
        <a href="equipment.php" class="btn btn-secondary btn-sm">
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
                    <label class="form-label">Equipment / Machine Name *</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Commercial Treadmill, Smith Machine" required />
                </div>
                <div class="form-group">
                    <label class="form-label">Operational Status</label>
                    <select name="status" class="form-select">
                        <option value="Operational">Operational / In Use</option>
                        <option value="Under Maintenance">Under Maintenance</option>
                        <option value="Out of Order">Out of Order</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Unit Cost (Price per item) *</label>
                    <input type="number" step="0.01" name="amount" class="form-control" placeholder="0.00" required />
                </div>
                <div class="form-group">
                    <label class="form-label">Quantity Purchased *</label>
                    <input type="number" name="quantity" class="form-control" value="1" min="1" required />
                </div>
                <div class="form-group">
                    <label class="form-label">Purchase Date *</label>
                    <input type="date" name="date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required />
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Vendor / Manufacturer Name</label>
                    <input type="text" name="vendor" class="form-control" placeholder="Supplier Name" />
                </div>
                <div class="form-group">
                    <label class="form-label">Vendor Contact Phone</label>
                    <input type="text" name="contact" class="form-control" placeholder="Vendor Phone" />
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Description / Specifications</label>
                <textarea name="description" class="form-control" rows="2" placeholder="Item description, target muscle groups, warranty info"></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Vendor Address</label>
                <input type="text" name="address" class="form-control" placeholder="Vendor Location" />
            </div>

            <div style="margin-top: 24px; display: flex; justify-content: flex-end; gap: 12px;">
                <a href="equipment.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" name="submit_equipment" value="1" class="btn btn-primary btn-lg">
                    <i class="fas fa-check-circle"></i> Save Equipment Entry
                </button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
