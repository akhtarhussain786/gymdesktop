<?php
/**
 * Superadmin Real Website Visitors & Leads Intelligence Console
 */

require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/visitor_tracker.php';
require_once __DIR__ . '/../core/helpers.php';

Auth::requireAuth('super_admin');

$page = 'super_leads';
$pageTitle = 'Real Website Visitors & Lead Intelligence';
$pageSubtitle = 'Monitor live real visitors and connect directly with captured leads (Name, Phone Number, WhatsApp & City).';

// Handle CSV Export
if (isset($_GET['action']) && $_GET['action'] === 'export_leads_csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=fitisify_real_visitor_leads_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Full Name', 'Phone', 'Email', 'Gym Name', 'City', 'Interested Plan', 'IP Address', 'Source Page', 'Status', 'Date Captured']);

    $leads = DB::fetchAll("SELECT * FROM website_leads ORDER BY id DESC");
    // Neutralise spreadsheet formula injection from publicly submitted lead fields
    $csvSafe = function ($v) {
        $v = (string)($v ?? '');
        return ($v !== '' && strpbrk($v[0], "=+-@\t\r") !== false) ? "'" . $v : $v;
    };
    foreach ($leads as $l) {
        $l = array_map($csvSafe, $l);
        fputcsv($output, [
            $l['id'],
            $l['full_name'],
            $l['phone'],
            $l['email'],
            $l['gym_name'],
            $l['city'],
            $l['interested_plan'],
            $l['ip_address'],
            $l['source_page'],
            $l['status'],
            $l['created_at']
        ]);
    }
    fclose($output);
    exit();
}

// Handle Update Lead Status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_lead_status'])) {
    Auth::verifyCsrf();
    $leadId = (int)($_POST['lead_id'] ?? 0);
    $newStatus = trim($_POST['status'] ?? 'new');
    $notes = trim($_POST['notes'] ?? '');
    $allowedLeadStatuses = ['new', 'contacted', 'demo_scheduled', 'converted', 'closed'];
    if (!in_array($newStatus, $allowedLeadStatuses, true)) {
        redirect(base_url('/superadmin/visitor-leads'), 'error', 'Invalid lead status.');
    }

    if ($leadId > 0) {
        DB::update('website_leads', ['status' => $newStatus, 'notes' => $notes], 'id = ?', [$leadId]);
        Auth::auditLog('LEAD_STATUS_UPDATE', "Updated lead #{$leadId} status to {$newStatus}");
        redirect(base_url('/superadmin/visitor-leads'), 'success', 'Lead status updated successfully.');
    }
}

$metrics = VisitorTracker::getMetrics();
$csrfToken = Auth::csrfToken();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/topbar.php';
?>

<style>
.metric-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 20px;
    margin-bottom: 24px;
}
.metric-card {
    background: var(--bg-card, #1e293b);
    border: 1px solid var(--border-color, rgba(255,255,255,0.08));
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 16px;
}
.metric-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
}
.wa-btn {
    background: #22c55e;
    color: #fff !important;
    padding: 6px 12px;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 700;
    font-size: 0.8rem;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s;
}
.wa-btn:hover {
    background: #16a34a;
    transform: translateY(-1px);
}
.call-btn {
    background: #3b82f6;
    color: #fff !important;
    padding: 6px 12px;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 700;
    font-size: 0.8rem;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s;
}
.call-btn:hover {
    background: #2563eb;
    transform: translateY(-1px);
}
.lead-status-select {
    background: #0f172a;
    color: #fff;
    border: 1px solid rgba(255,255,255,0.15);
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 600;
}
</style>

<!-- Metric Cards Header -->
<div class="metric-grid">
    <div class="metric-card">
        <div class="metric-icon" style="background: rgba(163, 230, 53, 0.15); color: var(--primary, #a3e635);">
            <i class="fas fa-user-plus"></i>
        </div>
        <div>
            <div style="font-size: 0.82rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Total Real Leads</div>
            <div style="font-size: 1.6rem; font-weight: 800; color: #fff;"><?php echo number_format($metrics['total_leads']); ?></div>
        </div>
    </div>

    <div class="metric-card">
        <div class="metric-icon" style="background: rgba(59, 130, 246, 0.15); color: #60a5fa;">
            <i class="fas fa-fire"></i>
        </div>
        <div>
            <div style="font-size: 0.82rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">New Leads Today</div>
            <div style="font-size: 1.6rem; font-weight: 800; color: #60a5fa;"><?php echo number_format($metrics['new_leads_today']); ?></div>
        </div>
    </div>

    <div class="metric-card">
        <div class="metric-icon" style="background: rgba(168, 85, 247, 0.15); color: #c084fc;">
            <i class="fas fa-users"></i>
        </div>
        <div>
            <div style="font-size: 0.82rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Visitors Today</div>
            <div style="font-size: 1.6rem; font-weight: 800; color: #fff;"><?php echo number_format($metrics['total_visitors_today']); ?></div>
        </div>
    </div>

    <div class="metric-card">
        <div class="metric-icon" style="background: rgba(245, 158, 11, 0.15); color: #fbbf24;">
            <i class="fas fa-chart-line"></i>
        </div>
        <div>
            <div style="font-size: 0.82rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">All-Time Visitors</div>
            <div style="font-size: 1.6rem; font-weight: 800; color: #fbbf24;"><?php echo number_format($metrics['total_visitors_all']); ?></div>
        </div>
    </div>
</div>

<div class="card" style="margin-bottom: 30px;">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
        <div class="card-title">
            <i class="fas fa-address-book" style="color: var(--primary);"></i>
            <span>Captured Real Visitor Inquiries & Leads</span>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="<?php echo base_url('/superadmin/visitor-leads?action=export_leads_csv'); ?>" class="btn btn-outline btn-sm" style="border-color: rgba(255,255,255,0.2); color: #fff;">
                <i class="fas fa-file-csv"></i> Export Leads CSV
            </a>
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($metrics['recent_leads'])): ?>
            <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                <i class="fas fa-inbox" style="font-size: 2.5rem; margin-bottom: 12px; opacity: 0.4;"></i>
                <p>No visitor leads captured yet. Real visitors filling the interactive contact/demo forms will appear here immediately with verified Phone Number & Name.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table" style="margin-bottom: 0;">
                    <thead>
                        <tr>
                            <th>Visitor Name</th>
                            <th>Phone & WhatsApp Action</th>
                            <th>Email Address</th>
                            <th>Gym / City</th>
                            <th>Interested Plan</th>
                            <th>Status</th>
                            <th>Date & Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($metrics['recent_leads'] as $lead): ?>
                            <?php
                            $cleanPhone = preg_replace('/[^0-9]/', '', $lead['phone']);
                            if (strlen($cleanPhone) === 10) {
                                $cleanPhone = '91' . $cleanPhone; // Default India prefix for WhatsApp
                            }
                            $waUrl = "https://wa.me/{$cleanPhone}?text=" . urlencode("Hello {$lead['full_name']}, thank you for your inquiry on Fitisify OS Gym Platform. How can we help you launch your gym management system?");
                            ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 700; color: #fff; font-size: 0.95rem;">
                                        <?php echo e($lead['full_name']); ?>
                                    </div>
                                    <div style="font-size: 0.78rem; color: var(--text-muted);">
                                        IP: <?php echo e($lead['ip_address']); ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 700; color: #a3e635; font-size: 0.92rem; margin-bottom: 6px;">
                                        <?php echo e($lead['phone']); ?>
                                    </div>
                                    <div style="display: flex; gap: 6px;">
                                        <a href="<?php echo e($waUrl); ?>" target="_blank" rel="noopener noreferrer" class="wa-btn" title="Chat on WhatsApp">
                                            <i class="fab fa-whatsapp"></i> WhatsApp
                                        </a>
                                        <a href="tel:<?php echo e($lead['phone']); ?>" class="call-btn" title="Call directly">
                                            <i class="fas fa-phone"></i> Call
                                        </a>
                                    </div>
                                </td>
                                <td>
                                    <div style="color: #e2e8f0; font-size: 0.88rem;">
                                        <?php echo !empty($lead['email']) ? e($lead['email']) : '<span style="color:var(--text-muted);">—</span>'; ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 600; color: #fff; font-size: 0.88rem;">
                                        <?php echo !empty($lead['gym_name']) ? e($lead['gym_name']) : 'Fitness Facility'; ?>
                                    </div>
                                    <div style="font-size: 0.78rem; color: var(--text-muted);">
                                        <?php echo !empty($lead['city']) ? e($lead['city']) : 'India'; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge" style="background: rgba(168, 85, 247, 0.15); color: #c084fc; border: 1px solid rgba(168, 85, 247, 0.3); font-size: 0.8rem;">
                                        <?php echo e($lead['interested_plan']); ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" style="margin:0;">
                                        <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
                                        <input type="hidden" name="lead_id" value="<?php echo (int)$lead['id']; ?>">
                                        <select name="status" class="lead-status-select" onchange="this.form.submit()">
                                            <option value="new" <?php echo $lead['status'] === 'new' ? 'selected' : ''; ?>>🟢 New Lead</option>
                                            <option value="contacted" <?php echo $lead['status'] === 'contacted' ? 'selected' : ''; ?>>🟡 Contacted</option>
                                            <option value="demo_scheduled" <?php echo $lead['status'] === 'demo_scheduled' ? 'selected' : ''; ?>>🔵 Demo Scheduled</option>
                                            <option value="converted" <?php echo $lead['status'] === 'converted' ? 'selected' : ''; ?>>🟣 Converted Client</option>
                                            <option value="closed" <?php echo $lead['status'] === 'closed' ? 'selected' : ''; ?>>⚪ Closed / Lost</option>
                                        </select>
                                        <input type="hidden" name="update_lead_status" value="1">
                                    </form>
                                </td>
                                <td>
                                    <div style="font-size: 0.82rem; color: #cbd5e1;">
                                        <?php echo date('M d, Y', strtotime($lead['created_at'])); ?>
                                    </div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);">
                                        <?php echo date('h:i A', strtotime($lead['created_at'])); ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Real Visitor Live Traffic Logs -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="fas fa-globe-americas" style="color: #60a5fa;"></i>
            <span>Recent Live Website Visitors Traffic Log</span>
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($metrics['recent_visitors'])): ?>
            <div style="text-align: center; padding: 30px; color: var(--text-muted);">
                No visitor traffic logged yet.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table" style="margin-bottom: 0;">
                    <thead>
                        <tr>
                            <th>Visitor IP</th>
                            <th>Device & OS</th>
                            <th>Browser</th>
                            <th>Page Visited</th>
                            <th>Referrer Source</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($metrics['recent_visitors'] as $v): ?>
                            <tr>
                                <td>
                                    <div style="font-family: monospace; font-weight: 600; color: #fff;">
                                        <?php echo e($v['ip_address']); ?>
                                    </div>
                                    <?php if (!empty($v['lead_id'])): ?>
                                        <span class="badge" style="background:#22c55e; color:#000; font-size:0.7rem; font-weight:800;">
                                            <i class="fas fa-check-circle"></i> LEAD #<?php echo (int)$v['lead_id']; ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-size: 0.88rem; color: #e2e8f0;">
                                        <?php if ($v['device'] === 'Mobile'): ?>
                                            <i class="fas fa-mobile-alt" style="color: var(--primary);"></i>
                                        <?php elseif ($v['device'] === 'Tablet'): ?>
                                            <i class="fas fa-tablet-alt" style="color: #60a5fa;"></i>
                                        <?php else: ?>
                                            <i class="fas fa-desktop" style="color: #c084fc;"></i>
                                        <?php endif; ?>
                                        <?php echo e($v['device']); ?> &middot; <?php echo e($v['os']); ?>
                                    </div>
                                </td>
                                <td>
                                    <span style="font-size: 0.85rem; color: var(--text-muted);">
                                        <?php echo e($v['browser']); ?>
                                    </span>
                                </td>
                                <td>
                                    <code style="color: var(--primary); font-size: 0.82rem;"><?php echo e($v['page_visited']); ?></code>
                                </td>
                                <td>
                                    <div style="font-size: 0.8rem; color: var(--text-muted); max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        <?php echo !empty($v['referrer']) ? e($v['referrer']) : 'Direct Visit'; ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-size: 0.8rem; color: #94a3b8;">
                                        <?php echo date('M d, H:i', strtotime($v['created_at'])); ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
