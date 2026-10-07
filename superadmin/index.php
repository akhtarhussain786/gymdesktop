<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/subscription_engine.php';
require_once __DIR__ . '/../core/demo_seeder.php';
require_once __DIR__ . '/../core/mailer.php';

Auth::requireAuth('super_admin');

$page = 'super_dashboard';
$pageTitle = 'Platform Control Center';
$pageSubtitle = 'Global SaaS metrics, gym tenant statuses, and subscription health';

// Handle SuperAdmin Quick Actions (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();

    // 1. Purge Orphaned Ghost Records
    if (isset($_POST['action']) && $_POST['action'] === 'purge_orphans') {
        $res = DemoSeeder::purgeOrphans();
        if ($res['success']) {
            $count = $res['total_deleted'];
            Auth::auditLog('SUPER_PURGE_ORPHANS', "Super Admin purged {$count} orphaned records from database.");
            set_flash('success', "Database Sanitation Complete: Successfully purged {$count} orphaned ghost records.");
        } else {
            set_flash('error', "Failed to purge orphans: " . ($res['error'] ?? 'Unknown error'));
        }
        redirect('index.php');
    }

    // 2. Seed / Reset Pristine Demo Gym
    if (isset($_POST['action']) && $_POST['action'] === 'seed_demo_gym') {
        $force = !empty($_POST['force_reset']);
        $res = DemoSeeder::seedDemoGym($force);
        if ($res['success']) {
            Auth::auditLog('SUPER_SEED_DEMO_GYM', "Super Admin initialized DEMO FITNESS & GYM showcase account.");
            $creds = $res['credentials'] ?? [];
            set_flash('success', "Demo Gym Ready! Gym: <strong>DEMO FITNESS & GYM</strong> (Code: <code>{$creds['gym_code']}</code>) | Admin: <code>{$creds['admin_username']}</code> / <code>{$creds['admin_password']}</code>");
        } else {
            set_flash('error', "Failed to seed demo gym: " . ($res['error'] ?? 'Unknown error'));
        }
        redirect('index.php');
    }

    // 3. Dispatch Expiry Reminder Email
    if (isset($_POST['action']) && $_POST['action'] === 'send_expiry_mail') {
        $targetTenantId = (int)($_POST['tenant_id'] ?? 0);
        if ($targetTenantId > 0) {
            $t = DB::fetchOne("SELECT t.*, sp.name as plan_name FROM tenants t LEFT JOIN subscription_plans sp ON t.subscription_plan_id = sp.id WHERE t.id = ?", [$targetTenantId]);
            if ($t && !empty($t['email'])) {
                $today = date('Y-m-d');
                $expiry = date('Y-m-d', strtotime($t['subscription_expiry']));
                $diff = (int)ceil((strtotime($expiry) - strtotime($today)) / 86400);

                $mailResult = Mailer::sendSaasExpiryReminderEmail([
                    'to_email'       => $t['email'],
                    'owner_name'     => $t['owner_name'] ?: 'Gym Owner',
                    'gym_name'       => $t['gym_name'] ?: 'Your Gym',
                    'plan_name'      => $t['plan_name'] ?: 'SaaS Plan',
                    'expiry_date'    => $expiry,
                    'days_remaining' => max(0, $diff),
                    'tenant_id'      => (int)$t['id']
                ]);

                if (!empty($mailResult['success'])) {
                    Auth::auditLog('SAAS_EXPIRY_REMINDER_MANUAL', "Dispatched SaaS renewal reminder to {$t['email']}", $targetTenantId);
                    set_flash('success', "Renewal reminder email dispatched successfully to <strong>{$t['email']}</strong>.");
                } elseif (!empty($mailResult['skipped'])) {
                    set_flash('info', "Reminder email was skipped: " . ($mailResult['reason'] ?? 'Already sent recently'));
                } else {
                    set_flash('error', "Failed to send reminder: " . ($mailResult['error'] ?? 'SMTP error'));
                }
            } else {
                set_flash('error', "Tenant email not found.");
            }
        }
        redirect('index.php');
    }

    // 4. Extend SaaS Subscription
    if (isset($_POST['action']) && $_POST['action'] === 'extend_subscription') {
        $targetTenantId = (int)($_POST['tenant_id'] ?? 0);
        $days = (int)($_POST['days'] ?? 30);
        if ($targetTenantId > 0 && $days > 0) {
            $t = DB::fetchOne("SELECT subscription_expiry FROM tenants WHERE id = ?", [$targetTenantId]);
            if ($t) {
                $baseTs = strtotime($t['subscription_expiry']);
                if ($baseTs < time()) {
                    $baseTs = time();
                }
                $newExpiry = date('Y-m-d 23:59:59', strtotime("+{$days} days", $baseTs));
                DB::update('tenants', [
                    'subscription_expiry' => $newExpiry,
                    'status' => 'active'
                ], 'id = ?', [$targetTenantId]);

                Auth::auditLog('SUPER_EXTEND_SUB', "Extended tenant #{$targetTenantId} subscription by {$days} days until {$newExpiry}", $targetTenantId);
                set_flash('success', "Subscription extended by {$days} days. New expiry: " . format_date($newExpiry));
            }
        }
        redirect('index.php');
    }
}

// Platform Statistics (Accurate & Isolated)
$totalTenants = (int)DB::fetchValue("SELECT COUNT(*) FROM tenants");
$activeTenants = (int)DB::fetchValue("SELECT COUNT(*) FROM tenants WHERE status = 'active'");
$trialTenants = (int)DB::fetchValue("SELECT COUNT(*) FROM tenants WHERE status = 'trial'");
$suspendedTenants = (int)DB::fetchValue("SELECT COUNT(*) FROM tenants WHERE status IN ('suspended','inactive')");

// Strict Tenant-Scoped Metrics to prevent Ghost / Orphaned Counters
$totalPlatformMembers = (int)DB::fetchValue("SELECT COUNT(*) FROM members m INNER JOIN tenants t ON m.tenant_id = t.id WHERE COALESCE(m.status, '') NOT IN ('Trash', 'trash')");
$totalPlatformStaff = (int)DB::fetchValue("SELECT COUNT(*) FROM staffs s INNER JOIN tenants t ON s.tenant_id = t.id WHERE COALESCE(s.status, '') NOT IN ('Trash', 'trash')");
$totalSaasRevenue = (float)DB::fetchValue("SELECT COALESCE(SUM(total_payable), 0) FROM saas_payments WHERE status = 'approved'");

// Check database orphaned rows count
$orphanedCount = DemoSeeder::countOrphans();

// Fetch tenants expiring in <= 15 days or already expired within 30 days
$expiringTenants = DB::fetchAll("SELECT t.*, p.name as plan_name,
                                (SELECT COUNT(*) FROM members m WHERE m.tenant_id = t.id AND m.status <> 'Trash') as member_count 
                                FROM tenants t 
                                LEFT JOIN subscription_plans p ON t.subscription_plan_id = p.id 
                                WHERE t.subscription_expiry IS NOT NULL 
                                  AND t.subscription_expiry BETWEEN DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND DATE_ADD(CURDATE(), INTERVAL 15 DAY)
                                ORDER BY t.subscription_expiry ASC LIMIT 5");

// Fetch recent tenants
$recentTenants = DB::fetchAll("SELECT t.*, p.name as plan_name, 
                               (SELECT COUNT(*) FROM members m WHERE m.tenant_id = t.id AND m.status <> 'Trash') as member_count 
                               FROM tenants t 
                               LEFT JOIN subscription_plans p ON t.subscription_plan_id = p.id 
                               ORDER BY t.created_at DESC LIMIT 8");

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/topbar.php';
?>

<!-- Quick Action & System Health Notification Banner -->
<?php if ($orphanedCount > 0): ?>
    <div class="alert alert-warning" style="display: flex; align-items: center; justify-content: space-between; gap: 15px; margin-bottom: 24px; background: rgba(245, 158, 11, 0.12); border: 1px solid rgba(245, 158, 11, 0.35); border-radius: var(--radius-md); padding: 14px 20px;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <i class="fas fa-broom" style="color: #f59e0b; font-size: 1.3rem;"></i>
            <div>
                <strong style="color: #f59e0b;">Database Notice:</strong>
                <span>Found <strong><?php echo $orphanedCount; ?></strong> orphaned records from deleted/test gyms. Clean them to keep reports 100% accurate.</span>
            </div>
        </div>
        <form method="POST" action="" style="margin: 0;">
            <?php echo Auth::csrfField(); ?>
            <input type="hidden" name="action" value="purge_orphans" />
            <button type="submit" class="btn btn-warning btn-sm" style="background: #f59e0b; color: #000; font-weight: 700;">
                <i class="fas fa-trash-alt"></i> Purge Ghost Data Now
            </button>
        </form>
    </div>
<?php endif; ?>

<!-- Empty State / Seed Demo Gym Prompt -->
<?php if ($totalTenants === 0): ?>
    <div class="card" style="background: linear-gradient(135deg, rgba(204,255,0,0.06), rgba(0,242,254,0.04)); border: 1px dashed var(--lime-border); margin-bottom: 24px; padding: 24px;">
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 20px; flex-wrap: wrap;">
            <div>
                <h2 style="font-size: 1.25rem; font-weight: 800; color: #fff; margin: 0 0 6px 0; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-rocket" style="color: var(--lime);"></i> Setup Showcase Demo Gym
                </h2>
                <p style="color: var(--text-muted); margin: 0; font-size: 0.9rem; max-width: 680px;">
                    No gyms are registered in your SaaS platform right now. Click below to instantly seed <strong>DEMO FITNESS & GYM</strong> with 5 realistic members, 2 trainers, workout & diet plans, and verified invoices for client presentations.
                </p>
            </div>
            <form method="POST" action="" style="margin: 0;">
                <?php echo Auth::csrfField(); ?>
                <input type="hidden" name="action" value="seed_demo_gym" />
                <input type="hidden" name="force_reset" value="1" />
                <button type="submit" class="btn btn-primary" style="font-weight: 800; padding: 12px 24px; box-shadow: 0 0 20px rgba(204,255,0,0.3);">
                    <i class="fas fa-magic"></i> Seed Clean Demo Gym
                </button>
            </form>
        </div>
    </div>
<?php endif; ?>

<!-- Platform Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-info">
            <h3>Registered Gyms</h3>
            <div class="stat-value"><?php echo $totalTenants; ?></div>
            <div class="stat-meta">
                <span style="color: var(--secondary);"><i class="fas fa-check-circle"></i> <?php echo $activeTenants; ?> Active</span>
                <span style="color: var(--text-light); margin: 0 4px;">•</span>
                <span style="color: var(--accent);"><?php echo $trialTenants; ?> Trial</span>
            </div>
        </div>
        <div class="stat-icon">
            <i class="fas fa-building"></i>
        </div>
    </div>

    <div class="stat-card stat-success">
        <div class="stat-info">
            <h3>Platform Members</h3>
            <div class="stat-value"><?php echo number_format($totalPlatformMembers); ?></div>
            <div class="stat-meta">Across active gym tenants</div>
        </div>
        <div class="stat-icon">
            <i class="fas fa-users"></i>
        </div>
    </div>

    <div class="stat-card stat-warning">
        <div class="stat-info">
            <h3>Platform Staff</h3>
            <div class="stat-value"><?php echo number_format($totalPlatformStaff); ?></div>
            <div class="stat-meta">Trainers & Administrators</div>
        </div>
        <div class="stat-icon">
            <i class="fas fa-user-shield"></i>
        </div>
    </div>

    <div class="stat-card stat-info">
        <div class="stat-info">
            <h3>SaaS Platform Revenue</h3>
            <div class="stat-value">₹<?php echo number_format($totalSaasRevenue, 2); ?></div>
            <div class="stat-meta">From SaaS purchases & renewals</div>
        </div>
        <div class="stat-icon">
            <i class="fas fa-rupee-sign"></i>
        </div>
    </div>
</div>

<!-- SaaS Expiry Radar Widget (If Any Gyms Expiring Soon) -->
<?php if (!empty($expiringTenants)): ?>
<div class="card" style="margin-bottom: 24px; border: 1px solid rgba(239, 68, 68, 0.3);">
    <div class="card-header" style="background: rgba(239, 68, 68, 0.05);">
        <div class="card-title">
            <i class="fas fa-bell" style="color: #ef4444;"></i>
            <span>SaaS Subscription Expiry Radar (Urgent Renewals)</span>
        </div>
        <div style="font-size: 0.8rem; color: var(--text-muted);">
            Automated emails are sent 5 days before expiry
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Gym Tenant</th>
                        <th>Owner / Contact</th>
                        <th>Plan</th>
                        <th>Expiry Date</th>
                        <th>Days Left</th>
                        <th>Renewal Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($expiringTenants as $et): 
                        $expTs = strtotime($et['subscription_expiry']);
                        $daysLeft = (int)ceil(($expTs - time()) / 86400);
                        $isExpired = ($daysLeft < 0);
                    ?>
                    <tr>
                        <td>
                            <strong style="color: var(--text-main);"><?php echo e($et['gym_name']); ?></strong>
                            <div style="font-size: 0.78rem; color: var(--text-muted);">Code: <code><?php echo e($et['gym_code'] ?? $et['slug']); ?></code></div>
                        </td>
                        <td>
                            <div><?php echo e($et['owner_name']); ?></div>
                            <div style="font-size: 0.78rem; color: var(--text-muted);"><?php echo e($et['email']); ?></div>
                        </td>
                        <td>
                            <span class="status-badge badge-info"><?php echo e($et['plan_name'] ?? 'Pro'); ?></span>
                        </td>
                        <td>
                            <?php echo format_date($et['subscription_expiry']); ?>
                        </td>
                        <td>
                            <?php if ($isExpired): ?>
                                <span class="status-badge badge-danger">Expired <?php echo abs($daysLeft); ?>d ago</span>
                            <?php elseif ($daysLeft <= 3): ?>
                                <span class="status-badge badge-danger" style="animation: pulse 2s infinite;"><?php echo $daysLeft; ?> Days Left</span>
                            <?php elseif ($daysLeft <= 7): ?>
                                <span class="status-badge badge-warning"><?php echo $daysLeft; ?> Days Left</span>
                            <?php else: ?>
                                <span class="status-badge badge-info"><?php echo $daysLeft; ?> Days Left</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display: flex; gap: 6px; align-items: center;">
                                <!-- Dispatch Expiry Email -->
                                <form method="POST" action="" style="margin: 0;">
                                    <?php echo Auth::csrfField(); ?>
                                    <input type="hidden" name="action" value="send_expiry_mail" />
                                    <input type="hidden" name="tenant_id" value="<?php echo (int)$et['id']; ?>" />
                                    <button type="submit" class="btn btn-secondary btn-sm" title="Dispatch Expiry Reminder Email">
                                        <i class="fas fa-paper-plane" style="color: #38bdf8;"></i> Send Reminder
                                    </button>
                                </form>

                                <!-- Extend 30 Days -->
                                <form method="POST" action="" style="margin: 0;">
                                    <?php echo Auth::csrfField(); ?>
                                    <input type="hidden" name="action" value="extend_subscription" />
                                    <input type="hidden" name="tenant_id" value="<?php echo (int)$et['id']; ?>" />
                                    <input type="hidden" name="days" value="30" />
                                    <button type="submit" class="btn btn-secondary btn-sm" title="Extend Subscription by 30 Days">
                                        <i class="fas fa-calendar-plus" style="color: #10b981;"></i> +30 Days
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Tenants List Table Card -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="fas fa-dumbbell"></i>
            <span>Registered Gym Tenants & Subscriptions</span>
        </div>
        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <input type="text" placeholder="Filter gyms..." data-table-search="#tenants-table" class="form-control" style="width: 200px; height: 36px;" />
            
            <!-- Quick Reset Demo Gym Button -->
            <form method="POST" action="" style="margin: 0;" onsubmit="return confirm('Reset or seed showcase DEMO FITNESS & GYM account?');">
                <?php echo Auth::csrfField(); ?>
                <input type="hidden" name="action" value="seed_demo_gym" />
                <input type="hidden" name="force_reset" value="0" />
                <button type="submit" class="btn btn-secondary btn-sm" title="Seed / Sync Demo Gym">
                    <i class="fas fa-sync-alt"></i> Sync Demo Gym
                </button>
            </form>

            <a href="<?php echo base_url('/superadmin/tenants.php'); ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> Manage & Add Gyms
            </a>
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table" id="tenants-table">
                <thead>
                    <tr>
                        <th>Gym Business</th>
                        <th>Owner Details</th>
                        <th>Subscription Tier</th>
                        <th>Active Members</th>
                        <th>Status</th>
                        <th>Expiry Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentTenants)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                No gym tenants registered yet. Click <strong>Sync Demo Gym</strong> above to seed a showcase gym.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentTenants as $t): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 700; color: var(--text-main);"><?php echo e($t['gym_name']); ?></div>
                                    <div style="font-size: 0.8rem; color: var(--text-muted);">
                                        <code><?php echo e($t['gym_code'] ?? $t['slug']); ?></code>
                                    </div>
                                </td>
                                <td>
                                    <div><?php echo e($t['owner_name']); ?></div>
                                    <div style="font-size: 0.8rem; color: var(--text-muted);"><?php echo e($t['email']); ?> • <?php echo e($t['phone']); ?></div>
                                </td>
                                <td>
                                    <span class="status-badge badge-info"><?php echo e($t['plan_name'] ?? 'Pro'); ?></span>
                                </td>
                                <td>
                                    <strong><?php echo (int)$t['member_count']; ?></strong> members
                                </td>
                                <td>
                                    <?php echo status_badge($t['status']); ?>
                                </td>
                                <td>
                                    <div><?php echo format_date($t['subscription_expiry']); ?></div>
                                    <?php 
                                        $remDays = (int)ceil((strtotime($t['subscription_expiry']) - time()) / 86400);
                                        if ($remDays < 0) {
                                            echo '<span style="font-size: 0.72rem; color: #ef4444; font-weight: 700;">Expired</span>';
                                        } elseif ($remDays <= 5) {
                                            echo '<span style="font-size: 0.72rem; color: #f59e0b; font-weight: 700;">' . $remDays . ' days left</span>';
                                        }
                                    ?>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 6px; align-items: center;">
                                        <!-- Impersonate Button -->
                                        <form method="POST" action="<?php echo base_url('/superadmin/impersonate.php'); ?>" style="display: inline; margin: 0;">
                                            <?php echo Auth::csrfField(); ?>
                                            <input type="hidden" name="tenant_id" value="<?php echo (int)$t['id']; ?>" />
                                            <button type="submit" class="btn btn-secondary btn-sm" title="Log in as Gym Admin">
                                                <i class="fas fa-external-link-alt"></i> Access
                                            </button>
                                        </form>

                                        <!-- Edit Tenant -->
                                        <a href="<?php echo base_url('/superadmin/tenants.php?edit=' . $t['id']); ?>" class="btn btn-secondary btn-sm" title="Edit Tenant">
                                            <i class="fas fa-edit"></i>
                                        </a>
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
