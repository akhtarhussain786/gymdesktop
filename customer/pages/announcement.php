<?php
require_once __DIR__ . '/../../core/auth.php';
Auth::requireAuth('member');

$page = 'member_announcements';
$pageTitle = 'Gym Notices & Announcements';
$pageSubtitle = 'Stay up-to-date with gym notices, schedules, holidays, and events';
$tenantId = Tenant::getTenantId();

$announcements = DB::fetchAll("SELECT * FROM announcements WHERE tenant_id = ? ORDER BY date DESC, id DESC", [$tenantId]);

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
include __DIR__ . '/../../includes/topbar.php';
?>

<div class="card" style="max-width: 800px; margin: 0 auto;">
    <div class="card-header">
        <div class="card-title">
            <i class="fas fa-bullhorn"></i>
            <span>Official Gym Announcements</span>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($announcements)): ?>
            <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                <i class="fas fa-bell-slash" style="font-size: 2rem; margin-bottom: 12px; opacity: 0.5;"></i>
                <p>No announcements published at the moment.</p>
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 16px;">
                <?php foreach ($announcements as $a): ?>
                    <div style="background: var(--bg-app); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 18px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                            <span class="status-badge badge-info">
                                <i class="fas fa-calendar-alt"></i> <?php echo format_date($a['date']); ?>
                            </span>
                        </div>
                        <div style="font-size: 0.95rem; color: var(--text-main); line-height: 1.6;">
                            <?php echo e($a['message']); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
