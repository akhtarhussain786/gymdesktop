<?php
/**
 * Ultra-Premium PDF & Printable Receipt Generator for Gym SaaS
 * Generates beautiful, responsive, print-to-PDF ready receipts with auto-print.
 */

class PDFReceipt {
    public static function output($invoice, $tenant, $member) {
        $invNum = $invoice['invoice_number'] ?? 'INV-0000';
        $gymName = $tenant['gym_name'] ?? 'Gym Fitness';
        $gymPhone = $tenant['phone'] ?? '';
        $gymEmail = $tenant['email'] ?? '';
        $gymAddress = $tenant['address'] ?? '';
        $currency = $tenant['currency'] ?: '₹';

        $logoUrl = null;
        if (!empty($tenant['logo'])) {
            if (str_starts_with($tenant['logo'], 'http')) {
                $logoUrl = $tenant['logo'];
            } elseif (file_exists(__DIR__ . '/../uploads/logos/' . basename($tenant['logo']))) {
                $logoUrl = base_url('/uploads/logos/' . basename($tenant['logo']));
            }
            // Missing file: fall back to the initials badge instead of a broken image
        }

        $memberName = $member['fullname'] ?? 'Gym Member';
        $memberContact = $member['contact'] ?? '';
        $memberId = 'MEM-' . str_pad($member['user_id'] ?? 0, 4, '0', STR_PAD_LEFT);

        $service = $invoice['service_name'] ?? 'Fitness Membership';
        $months = (int)($invoice['plan_months'] ?? 1);
        $totalAmt = (float)($invoice['total_amount'] ?? $invoice['amount'] ?? 0);
        $paidAmt = (float)($invoice['paid_amount'] ?? 0);
        $discount = (float)($invoice['discount'] ?? 0);
        $dueAmt = max(0, $totalAmt - $paidAmt - $discount);
        $date = format_date($invoice['payment_date'] ?? date('Y-m-d'));
        $status = ucfirst(strtolower($invoice['status'] ?? 'paid'));
        $method = $invoice['payment_method'] ?? 'Cash';
        $ref = $invoice['transaction_ref'] ?? 'N/A';
        $footer = $tenant['invoice_footer'] ?? 'Thank you for choosing ' . $gymName . '!';

        header('Content-Type: text/html; charset=UTF-8');
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #<?php echo e($invNum); ?> - <?php echo e($gymName); ?></title>
    <style>
        @page { size: A4; margin: 12mm; }
        body { font-family: 'Segoe UI', system-ui, -apple-system, Roboto, sans-serif; background: #f1f5f9; color: #0f172a; margin: 0; padding: 24px; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .receipt-card { max-width: 650px; margin: 0 auto; background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 12px 30px rgba(0,0,0,0.06); padding: 36px; position: relative; }
        .brand-bar { height: 6px; background: linear-gradient(90deg, #2563eb, #3b82f6, #10b981); border-radius: 16px 16px 0 0; position: absolute; top: 0; left: 0; right: 0; }
        .header { display: flex; align-items: flex-start; justify-content: space-between; border-bottom: 2px solid #e2e8f0; padding-bottom: 20px; margin-bottom: 24px; margin-top: 6px; }
        .logo-box { width: 60px; height: 60px; border-radius: 14px; background: #f8fafc; border: 1px solid #cbd5e1; display: flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0; }
        .logo-box img { width: 100%; height: 100%; object-fit: contain; }
        .gym-title { font-size: 1.5rem; font-weight: 800; color: #0f172a; margin: 0; line-height: 1.2; letter-spacing: -0.5px; }
        .gym-subtitle { font-size: 0.84rem; color: #64748b; margin-top: 3px; font-weight: 500; }
        .badge { display: inline-block; padding: 6px 14px; border-radius: 20px; font-size: 0.8rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.6px; }
        .badge-paid { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .badge-due { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px; padding: 18px; margin-bottom: 24px; font-size: 0.88rem; }
        .info-item div:first-child { color: #64748b; font-size: 0.73rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.6px; }
        .info-item div:last-child { color: #0f172a; font-weight: 700; margin-top: 3px; }
        .item-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        .item-table th { background: #f1f5f9; text-align: left; padding: 12px 16px; font-size: 0.78rem; font-weight: 800; color: #475569; text-transform: uppercase; letter-spacing: 0.6px; border-radius: 8px; }
        .item-table td { padding: 14px 16px; font-size: 0.92rem; border-bottom: 1px dashed #e2e8f0; }
        .summary-box { margin-left: auto; max-width: 300px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; }
        .summary-row { display: flex; justify-content: space-between; padding: 6px 0; font-size: 0.9rem; }
        .summary-row-bold { font-size: 1.1rem; font-weight: 800; border-top: 2px solid #cbd5e1; padding-top: 10px; margin-top: 6px; }
        .footer-note { text-align: center; margin-top: 32px; font-size: 0.84rem; color: #64748b; border-top: 1px solid #f1f5f9; padding-top: 20px; font-weight: 500; }
        .no-print { display: flex; gap: 12px; justify-content: center; margin-bottom: 24px; }
        .btn { display: inline-flex; align-items: center; gap: 8px; background: #2563eb; color: #ffffff; padding: 12px 24px; border-radius: 12px; font-weight: 700; text-decoration: none; border: none; cursor: pointer; font-size: 0.95rem; box-shadow: 0 4px 14px rgba(37,99,235,0.25); transition: transform 0.15s; }
        .btn:hover { transform: translateY(-1px); }
        .btn-secondary { background: #64748b; box-shadow: none; }
        @media print { .no-print { display: none !important; } body { background: #ffffff; padding: 0; } .receipt-card { box-shadow: none; border: none; padding: 0; width: 100%; max-width: 100%; } .brand-bar { display: none; } }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()" class="btn">🖨️ Save PDF / Print Receipt</button>
        <button onclick="window.close()" class="btn btn-secondary">Close Window</button>
    </div>

    <div class="receipt-card">
        <div class="brand-bar"></div>
        <div class="header">
            <div style="display: flex; align-items: center; gap: 16px;">
                <div class="logo-box">
                    <?php if ($logoUrl): ?>
                        <img src="<?php echo e($logoUrl); ?>" alt="Gym Logo">
                    <?php else: ?>
                        <span style="font-weight: 900; color: #2563eb; font-size: 1.2rem;">GYM</span>
                    <?php endif; ?>
                </div>
                <div>
                    <h1 class="gym-title"><?php echo e($gymName); ?></h1>
                    <div class="gym-subtitle"><?php echo e($gymPhone); ?> <?php echo $gymEmail ? '• ' . e($gymEmail) : ''; ?></div>
                    <div class="gym-subtitle"><?php echo e($gymAddress); ?></div>
                </div>
            </div>
            <div style="text-align: right;">
                <span class="badge <?php echo strtolower($status) === 'paid' ? 'badge-paid' : 'badge-due'; ?>"><?php echo e($status); ?></span>
                <div style="font-size: 0.9rem; font-weight: 800; color: #2563eb; margin-top: 8px; font-family: monospace; letter-spacing: 0.5px;">#<?php echo e($invNum); ?></div>
            </div>
        </div>

        <div class="info-grid">
            <div class="info-item">
                <div>Billed To Member</div>
                <div><?php echo e($memberName); ?> (<?php echo e($memberId); ?>)</div>
            </div>
            <div class="info-item">
                <div>Contact Phone</div>
                <div><?php echo e($memberContact ?: 'N/A'); ?></div>
            </div>
            <div class="info-item">
                <div>Payment Date</div>
                <div><?php echo e($date); ?></div>
            </div>
            <div class="info-item">
                <div>Payment Method & Ref</div>
                <div><?php echo e($method); ?> (<?php echo e($ref); ?>)</div>
            </div>
        </div>

        <table class="item-table">
            <thead>
                <tr>
                    <th>Service Description</th>
                    <th>Duration</th>
                    <th style="text-align: right;">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong><?php echo e($service); ?></strong><br><span style="font-size: 0.8rem; color: #64748b;">Gym access, training facilities & wellness services</span></td>
                    <td><?php echo $months; ?> Month(s)</td>
                    <td style="text-align: right; font-weight: 700;"><?php echo $currency . number_format($totalAmt, 2); ?></td>
                </tr>
            </tbody>
        </table>

        <div class="summary-box">
            <div class="summary-row">
                <span style="color: #64748b;">Subtotal Rate</span>
                <span><?php echo $currency . number_format($totalAmt, 2); ?></span>
            </div>
            <?php if ($discount > 0): ?>
            <div class="summary-row" style="color: #16a34a;">
                <span>Discount Applied</span>
                <span>-<?php echo $currency . number_format($discount, 2); ?></span>
            </div>
            <?php endif; ?>
            <div class="summary-row summary-row-bold" style="color: #16a34a;">
                <span>Total Paid</span>
                <span><?php echo $currency . number_format($paidAmt, 2); ?></span>
            </div>
            <?php if ($dueAmt > 0): ?>
            <div class="summary-row" style="color: #dc2626; font-weight: 700;">
                <span>Balance Due</span>
                <span><?php echo $currency . number_format($dueAmt, 2); ?></span>
            </div>
            <?php endif; ?>
        </div>

        <div class="footer-note">
            <?php echo e($footer); ?>
        </div>
    </div>

    <script>
        // Auto trigger native PDF print / save dialog on load
        window.addEventListener('load', function() {
            setTimeout(function() {
                window.print();
            }, 300);
        });
    </script>
</body>
</html>
        <?php
        exit;
    }
}
