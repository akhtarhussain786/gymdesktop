<?php
/**
 * Gym Admin Expenses API
 * List, Add, and Delete Gym Expenses matching admin/expenses.php
 */

require_once __DIR__ . '/middleware.php';

$auth = AdminAuthMiddleware::authenticate();
$tenant = $auth['tenant'];
$tenantId = (int)$auth['tenant_id'];

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $month = !empty($_GET['month']) ? trim($_GET['month']) : date('Y-m');

    $expenses = DB::fetchAll(
        "SELECT id, title, category, amount, expense_date, vendor, payment_mode, notes, created_at
         FROM expenses 
         WHERE tenant_id = ? AND expense_date LIKE ? 
         ORDER BY expense_date DESC, id DESC",
        [$tenantId, "$month%"]
    );

    $totalExpense = (float)DB::fetchValue(
        "SELECT SUM(amount) FROM expenses WHERE tenant_id = ? AND expense_date LIKE ?",
        [$tenantId, "$month%"]
    );

    $categoryStats = DB::fetchAll(
        "SELECT category, SUM(amount) as total_amount, COUNT(*) as count 
         FROM expenses 
         WHERE tenant_id = ? AND expense_date LIKE ? 
         GROUP BY category",
        [$tenantId, "$month%"]
    );

    ApiResponse::success([
        'month' => $month,
        'total_expense' => $totalExpense,
        'expenses' => $expenses,
        'categories' => $categoryStats
    ], 'Expenses retrieved successfully');
}

if ($method === 'POST') {
    $input = get_json_input();
    if (empty($input)) $input = $_POST;

    $action = trim($input['action'] ?? 'create');

    if ($action === 'delete') {
        $deleteId = (int)($input['id'] ?? 0);
        if ($deleteId <= 0) {
            ApiResponse::error('Expense ID is required', 400);
        }
        DB::delete('expenses', 'id = ? AND tenant_id = ?', [$deleteId, $tenantId]);
        ApiResponse::success(null, 'Expense deleted successfully');
    }

    $title = trim($input['title'] ?? '');
    $category = trim($input['category'] ?? 'General');
    $amount = (float)($input['amount'] ?? 0.0);
    $expenseDate = !empty($input['expense_date']) ? trim($input['expense_date']) : date('Y-m-d');
    $vendor = trim($input['vendor'] ?? '');
    $paymentMode = trim($input['payment_mode'] ?? 'Cash');
    $notes = trim($input['notes'] ?? '');

    if (empty($title)) {
        ApiResponse::error('Expense Title is required.', 422);
    }
    if ($amount <= 0) {
        ApiResponse::error('Expense amount must be greater than 0.', 422);
    }

    $id = DB::insert('expenses', [
        'tenant_id' => $tenantId,
        'branch_id' => (int)($tenant['branch_id'] ?? 1),
        'title' => $title,
        'category' => $category,
        'amount' => $amount,
        'expense_date' => $expenseDate,
        'vendor' => $vendor ?: null,
        'payment_mode' => $paymentMode,
        'notes' => $notes ?: null,
        'created_by' => $auth['user_id'] ?? null
    ]);

    ApiResponse::success(['id' => $id], 'Expense recorded successfully!', 201);
}
