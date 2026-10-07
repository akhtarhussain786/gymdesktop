<?php
require_once __DIR__ . '/../core/auth.php';
Auth::requireAuth(['gym_admin']); // finance: owner-only

$page = 'expenses';
$pageTitle = 'Gym Expenses Management';
$pageSubtitle = 'Track and categorize gym operating expenses, utilities, rent, and maintenance';
$tenantId = Tenant::getTenantId();

// Handle New Expense
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_expense'])) {
    Auth::verifyCsrf();

    $title = trim($_POST['title'] ?? '');
    $category = $_POST['category'] ?? 'General';
    $amount = round((float)($_POST['amount'] ?? 0), 2);
    $expenseDate = $_POST['expense_date'] ?? date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$expenseDate) || strtotime($expenseDate) === false) {
        $expenseDate = date('Y-m-d');
    }
    if ($title === '' || $amount <= 0) {
        redirect('expenses.php', 'error', 'Expense title and a positive amount are required.');
    }
    $vendor = trim($_POST['vendor'] ?? '');
    $paymentMode = $_POST['payment_mode'] ?? 'Cash';
    $notes = trim($_POST['notes'] ?? '');

    DB::insert('expenses', [
        'tenant_id' => $tenantId,
        'branch_id' => 1,
        'title' => $title,
        'category' => $category,
        'amount' => $amount,
        'expense_date' => $expenseDate,
        'vendor' => $vendor,
        'payment_mode' => $paymentMode,
        'notes' => $notes,
        'created_by' => $_SESSION['user_id']
    ]);

    Auth::auditLog('ADD_EXPENSE', "Logged expense '$title' of $amount ($category)");
    redirect('expenses.php', 'success', "Expense '$title' recorded successfully.");
}

// Handle Delete Expense
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    Auth::verifyCsrf();
    $deleteId = (int)$_POST['delete'];
    DB::delete('expenses', 'id = ? AND tenant_id = ?', [$deleteId, $tenantId]);
    redirect('expenses.php', 'success', 'Expense record deleted.');
}

$expenses = DB::fetchAll("SELECT * FROM expenses WHERE tenant_id = ? ORDER BY expense_date DESC, id DESC", [$tenantId]);

// Summary Metrics
$thisMonthExpenses = (float)DB::fetchValue("SELECT SUM(amount) FROM expenses WHERE tenant_id = ? AND expense_date BETWEEN ? AND ?", [$tenantId, date('Y-m-01'), date('Y-m-t')]);
$totalAllTimeExpenses = (float)DB::fetchValue("SELECT SUM(amount) FROM expenses WHERE tenant_id = ?", [$tenantId]);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/topbar.php';
?>

<!-- Expense Summary Stats -->
<div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
    <div class="stat-card stat-danger">
        <div class="stat-info">
            <h3>This Month Expenses</h3>
            <div class="stat-value"><?php echo format_currency($thisMonthExpenses); ?></div>
            <div class="stat-meta"><?php echo date('F Y'); ?></div>
        </div>
        <div class="stat-icon">
            <i class="fas fa-receipt"></i>
        </div>
    </div>

    <div class="stat-card stat-warning">
        <div class="stat-info">
            <h3>Total All-Time Expenses</h3>
            <div class="stat-value"><?php echo format_currency($totalAllTimeExpenses); ?></div>
            <div class="stat-meta">Lifetime logged expenses</div>
        </div>
        <div class="stat-icon">
            <i class="fas fa-coins"></i>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="fas fa-list-alt"></i>
            <span>Expense Records (<?php echo count($expenses); ?>)</span>
        </div>
        <div style="display: flex; gap: 10px;">
            <input type="text" placeholder="Filter expenses..." data-table-search="#expenses-table" class="form-control" style="width: 200px;" />
            <button type="button" class="btn btn-primary btn-sm" onclick="App.openModal('add-expense-modal')">
                <i class="fas fa-plus"></i> Add New Expense
            </button>
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table" id="expenses-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Expense Title</th>
                        <th>Category</th>
                        <th>Amount</th>
                        <th>Vendor / Payee</th>
                        <th>Payment Mode</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($expenses)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                No expense records found. Click "Add New Expense" to create one.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($expenses as $idx => $ex): ?>
                            <tr>
                                <td><?php echo $idx + 1; ?></td>
                                <td>
                                    <strong style="color: var(--text-main);"><?php echo e($ex['title']); ?></strong>
                                    <?php if (!empty($ex['notes'])): ?>
                                        <div style="font-size: 0.78rem; color: var(--text-muted);"><?php echo e($ex['notes']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge badge-warning"><?php echo e($ex['category']); ?></span>
                                </td>
                                <td>
                                    <strong style="color: var(--danger);"><?php echo format_currency($ex['amount']); ?></strong>
                                </td>
                                <td><?php echo e($ex['vendor'] ?: '—'); ?></td>
                                <td><?php echo e($ex['payment_mode']); ?></td>
                                <td><?php echo format_date($ex['expense_date']); ?></td>
                                <td>
                                    <form method="POST" action="expenses.php" style="display:inline;"><?php echo Auth::csrfField(); ?><button type="submit" name="delete" value="<?php echo (int)$ex['id']; ?>" class="btn btn-secondary btn-sm" title="Delete Expense" onclick="return confirm('Delete this expense record?')">
                                        <i class="fas fa-trash" style="color: var(--danger);"></i>
                                    </button></form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Expense Modal -->
<div class="modal-backdrop" id="add-expense-modal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="card-title">
                <i class="fas fa-plus-circle"></i>
                <span>Record New Expense</span>
            </div>
            <button type="button" class="btn-icon" onclick="App.closeModal('add-expense-modal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" action="">
            <?php echo Auth::csrfField(); ?>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Expense Title / Item Description *</label>
                    <input type="text" name="title" class="form-control" placeholder="e.g. Monthly Electricity Bill, Dumbbell Set" required />
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Category *</label>
                        <select name="category" class="form-select">
                            <option value="Utilities">Utilities (Electricity, Water, Internet)</option>
                            <option value="Rent">Gym Facility Rent</option>
                            <option value="Equipment">Equipment & Repairs</option>
                            <option value="Salaries">Staff Salaries & Commissions</option>
                            <option value="Supplements">Supplements & Snacks Inventory</option>
                            <option value="Marketing">Marketing & Advertising</option>
                            <option value="General">General / Miscellaneous</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Amount (<?php echo $tenant['currency']; ?>) *</label>
                        <input type="number" step="0.01" name="amount" class="form-control" placeholder="0.00" required />
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Vendor / Supplier</label>
                        <input type="text" name="vendor" class="form-control" placeholder="Vendor Name" />
                    </div>
                    <div class="form-group">
                        <label class="form-label">Payment Mode</label>
                        <select name="payment_mode" class="form-select">
                            <option value="Cash">Cash</option>
                            <option value="Bank Transfer">Bank Wire / Transfer</option>
                            <option value="UPI">UPI / Digital</option>
                            <option value="Card">Card</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Expense Date</label>
                        <input type="date" name="expense_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required />
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Additional Notes</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Invoice reference number or remarks"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="App.closeModal('add-expense-modal')">Cancel</button>
                <button type="submit" name="add_expense" value="1" class="btn btn-primary">Save Expense Record</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
