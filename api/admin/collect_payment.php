<?php
/**
 * Gym Admin Instant Payment & Due Collection API
 * Allows 1-Tap Cash / UPI payment recording from Mobile App, clears pending dues, updates invoice, and generates receipt.
 */

require_once __DIR__ . '/middleware.php';

$auth = AdminAuthMiddleware::authenticate();
$tenant = $auth['tenant'];
$tenantId = (int)$auth['tenant_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::error('Method not allowed', 405);
}

// 0. Ensure schema compatibility
static $schemaChecked = false;
if (!$schemaChecked) {
    try {
        if (!api_column_exists('members', 'due_amount')) {
            @DB::query("ALTER TABLE `members` ADD COLUMN `due_amount` decimal(10,2) NOT NULL DEFAULT 0.00");
        }
        if (!api_column_exists('members', 'due_date')) {
            @DB::query("ALTER TABLE `members` ADD COLUMN `due_date` date DEFAULT NULL");
        }
        if (!api_column_exists('invoices', 'paid_amount')) {
            @DB::query("ALTER TABLE `invoices` ADD COLUMN `paid_amount` decimal(10,2) NOT NULL DEFAULT 0.00");
        }
        if (!api_column_exists('invoices', 'due_date')) {
            @DB::query("ALTER TABLE `invoices` ADD COLUMN `due_date` date DEFAULT NULL");
        }
    } catch (Throwable $e) {
        error_log("Schema auto-alter ignored: " . $e->getMessage());
    }
    $schemaChecked = true;
}

$input = get_json_input();
if (empty($input)) {
    $input = $_POST;
}

$memberId = (int)($input['member_id'] ?? 0);
$amountCollected = (float)($input['amount_collected'] ?? $input['amount'] ?? 0.0);
$paymentMethod = trim($input['payment_method'] ?? 'Cash');
$paymentDate = !empty($input['payment_date']) ? trim($input['payment_date']) : date('Y-m-d');
$paymentType = trim($input['payment_type'] ?? 'due_clearance'); // 'due_clearance' or 'renewal'
$newDueDate = !empty($input['new_due_date']) ? trim($input['new_due_date']) : null;
$notes = trim($input['notes'] ?? ('Cash collected by Gym Admin on ' . date('d M Y')));

if ($memberId <= 0) {
    ApiResponse::error('Member ID is required', 400);
}
if ($amountCollected <= 0) {
    ApiResponse::error('Amount collected must be greater than 0', 400);
}

// 1. Verify Member
$member = DB::fetchOne("SELECT * FROM members WHERE user_id = ? AND tenant_id = ?", [$memberId, $tenantId]);
if (!$member) {
    ApiResponse::notFound('Member not found in this gym.');
}

$branchId = (int)($member['branch_id'] ?? $tenant['branch_id'] ?? 1);
if ($branchId <= 0) {
    $branchId = 1;
}

DB::beginTransaction();
try {
    $currency = !empty($tenant['currency']) ? $tenant['currency'] : '₹';
    $gymName = !empty($tenant['gym_name']) ? $tenant['gym_name'] : 'Our Gym';

    if ($paymentType === 'renewal') {
        // --- MEMBERSHIP RENEWAL FLOW ---
        $planMonths = max(1, (int)($input['plan_months'] ?? $member['plan'] ?? 1));
        $services = trim($input['services'] ?? $member['services'] ?? 'General Fitness');
        $totalPlanFee = (float)($input['total_plan_fee'] ?? $amountCollected);
        $remainingDue = max(0.0, $totalPlanFee - $amountCollected);

        // Generate Invoice Number
        $invCount = (int)DB::fetchValue("SELECT COUNT(*) FROM invoices WHERE tenant_id = ?", [$tenantId]);
        $tenantSlug = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $tenant['slug'] ?? $tenant['gym_name'] ?? 'GYM'), 0, 4));
        $invoiceNumber = 'INV-' . $tenantSlug . '-' . date('Ym') . '-' . str_pad($invCount + 1, 4, '0', STR_PAD_LEFT);

        $invStatus = ($remainingDue <= 0) ? 'Paid' : 'Partial';

        $invCols = [
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'member_id' => $memberId,
            'invoice_number' => $invoiceNumber,
            'service_name' => $services,
            'plan_months' => $planMonths,
            'amount' => $totalPlanFee,
            'paid_amount' => $amountCollected,
            'discount' => 0.00,
            'payment_method' => $paymentMethod,
            'payment_date' => $paymentDate,
            'status' => $invStatus,
            'transaction_ref' => strtoupper($paymentMethod) . '-' . strtoupper(substr(md5(uniqid()), 0, 8)),
            'notes' => $notes,
            'created_by' => $auth['user_id'] ?? null,
            'created_at' => date('Y-m-d H:i:s')
        ];

        if (api_column_exists('invoices', 'due_date')) {
            $invCols['due_date'] = $remainingDue > 0 ? $newDueDate : null;
        }

        $invId = DB::insert('invoices', $invCols);

        // Calculate Start Date and Expiry Date
        $startDate = $paymentDate;
        $expiryDate = date('Y-m-d', strtotime("+$planMonths months", strtotime($startDate)));

        if (api_table_exists('member_subscriptions')) {
            DB::insert('member_subscriptions', [
                'tenant_id' => $tenantId,
                'member_id' => $memberId,
                'plan_name_snapshot' => $services,
                'plan_price_snapshot' => $totalPlanFee,
                'plan_duration_snapshot' => $planMonths,
                'start_date' => $startDate,
                'expiry_date' => $expiryDate,
                'status' => 'active',
                'payment_id' => $invId,
                'queue_position' => 1,
                'activated_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }

        // Update Member
        $memberUpdate = [
            'services' => $services,
            'amount' => $totalPlanFee,
            'plan' => $planMonths,
            'status' => 'Active',
            'paid_date' => $startDate
        ];
        if (api_column_exists('members', 'due_amount')) {
            $memberUpdate['due_amount'] = $remainingDue;
        }
        if (api_column_exists('members', 'due_date')) {
            $memberUpdate['due_date'] = $remainingDue > 0 ? $newDueDate : null;
        }

        DB::update('members', $memberUpdate, 'user_id = ? AND tenant_id = ?', [$memberId, $tenantId]);

        $finalDue = $remainingDue;
        $lastReceiptId = $invId;
        $lastInvoiceNumber = $invoiceNumber;

    } else {
        // --- INSTANT DUE CLEARANCE / PARTIAL SETTLEMENT FLOW ---
        $openInvoices = DB::fetchAll(
            "SELECT * FROM invoices 
             WHERE tenant_id = ? AND member_id = ? AND status IN ('Partial', 'Unpaid', 'Pending')
             ORDER BY payment_date ASC, id ASC",
            [$tenantId, $memberId]
        );

        $remainingToAllocate = $amountCollected;
        $lastReceiptId = 0;
        $lastInvoiceNumber = '';

        foreach ($openInvoices as $inv) {
            if ($remainingToAllocate <= 0) break;

            $invDue = (float)$inv['amount'] - (float)$inv['paid_amount'];
            if ($invDue <= 0) continue;

            $payForThis = min($remainingToAllocate, $invDue);
            $newPaid = (float)$inv['paid_amount'] + $payForThis;
            $newStatus = ($newPaid >= (float)$inv['amount']) ? 'Paid' : 'Partial';

            $updateData = [
                'paid_amount' => $newPaid,
                'status' => $newStatus,
                'notes' => $inv['notes'] . " | Paid {$currency}{$payForThis} via {$paymentMethod} on {$paymentDate}"
            ];

            if (api_column_exists('invoices', 'due_date')) {
                $updateData['due_date'] = ($newStatus === 'Paid') ? null : $newDueDate;
            }

            DB::update('invoices', $updateData, 'id = ?', [$inv['id']]);

            $remainingToAllocate -= $payForThis;
            $lastReceiptId = (int)$inv['id'];
            $lastInvoiceNumber = $inv['invoice_number'];
        }

        // If no open invoice existed or excess paid, create a dedicated payment settlement invoice
        if ($remainingToAllocate > 0 || empty($openInvoices)) {
            $invCount = (int)DB::fetchValue("SELECT COUNT(*) FROM invoices WHERE tenant_id = ?", [$tenantId]);
            $tenantSlug = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $tenant['slug'] ?? $tenant['gym_name'] ?? 'GYM'), 0, 4));
            $invoiceNumber = 'INV-' . $tenantSlug . '-' . date('Ym') . '-' . str_pad($invCount + 1, 4, '0', STR_PAD_LEFT);

            $lastReceiptId = DB::insert('invoices', [
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'member_id' => $memberId,
                'invoice_number' => $invoiceNumber,
                'service_name' => 'Due Balance Payment (' . $member['services'] . ')',
                'plan_months' => (int)($member['plan'] ?? 1),
                'amount' => $amountCollected,
                'paid_amount' => $amountCollected,
                'discount' => 0.00,
                'payment_method' => $paymentMethod,
                'payment_date' => $paymentDate,
                'due_date' => null,
                'status' => 'Paid',
                'transaction_ref' => strtoupper($paymentMethod) . '-' . strtoupper(substr(md5(uniqid()), 0, 8)),
                'notes' => $notes,
                'created_by' => $auth['user_id'] ?? null,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            $lastInvoiceNumber = $invoiceNumber;
        }

        // Recompute Total Remaining Dues for Member
        $recalcDue = (float)DB::fetchValue(
            "SELECT COALESCE(SUM(GREATEST(0, amount - paid_amount)), 0) 
             FROM invoices 
             WHERE tenant_id = ? AND member_id = ? AND status IN ('Partial', 'Unpaid', 'Pending')",
            [$tenantId, $memberId]
        );

        $memberUpdate = [];
        if (api_column_exists('members', 'due_amount')) {
            $memberUpdate['due_amount'] = $recalcDue;
        }
        if (api_column_exists('members', 'due_date')) {
            $memberUpdate['due_date'] = ($recalcDue > 0) ? $newDueDate : null;
        }
        if (!empty($memberUpdate)) {
            DB::update('members', $memberUpdate, 'user_id = ? AND tenant_id = ?', [$memberId, $tenantId]);
        }

        $finalDue = $recalcDue;
    }

    DB::commit();

    // Generate WhatsApp Receipt message
    $waText = "Receipt from *" . $gymName . "*:\n"
            . "Dear *" . $member['fullname'] . "*,\n"
            . "We have received *" . $currency . number_format($amountCollected, 2) . "* via *" . $paymentMethod . "* on " . date('d M Y', strtotime($paymentDate)) . ".\n"
            . "Invoice No: *" . $lastInvoiceNumber . "*\n";

    if ($finalDue > 0) {
        $waText .= "Remaining Due Balance: *" . $currency . number_format($finalDue, 2) . "*\n";
        if ($newDueDate) {
            $waText .= "Due Promise Date: *" . date('d M Y', strtotime($newDueDate)) . "*\n";
        }
    } else {
        $waText .= "Status: *✓ FULLY PAID (Zero Dues)*\n";
    }
    $waText .= "Thank you for your payment!";

    ApiResponse::success([
        'member_id' => $memberId,
        'fullname' => $member['fullname'],
        'amount_collected' => $amountCollected,
        'payment_method' => $paymentMethod,
        'remaining_due' => $finalDue,
        'due_date' => $finalDue > 0 ? $newDueDate : null,
        'invoice_id' => $lastReceiptId,
        'invoice_number' => $lastInvoiceNumber,
        'receipt_url' => base_url('/api/member/receipt_html.php?id=' . $lastReceiptId),
        'whatsapp_message' => $waText
    ], 'Payment of ' . $currency . number_format($amountCollected, 2) . ' recorded successfully!');

} catch (Throwable $e) {
    DB::rollback();
    error_log("Collect Payment API Error: " . $e->getMessage());
    ApiResponse::error('Failed to record payment: ' . $e->getMessage(), 500);
}
