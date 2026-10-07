<?php
$pageTitle = $pageTitle ?? 'Dashboard';
$pageSubtitle = $pageSubtitle ?? '';
$tenant = Tenant::getCurrent();
$currentUser = Auth::user();
?>
<main class="app-main">
    <?php if (!empty($_SESSION['impersonator'])): ?>
    <div style="background: linear-gradient(135deg, #ef4444, #dc2626); color: #fff; padding: 10px 20px; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; font-size: 0.88rem; font-weight: 600;">
        <span>
            <i class="fas fa-user-secret"></i>
            Impersonation mode: <?php echo e($_SESSION['impersonator']['username'] ?? 'Super Admin'); ?> is viewing <strong><?php echo e($tenant['gym_name'] ?? 'this gym'); ?></strong> as gym administrator.
        </span>
        <form method="POST" action="<?php echo base_url('/superadmin/stop-impersonate.php'); ?>" style="margin: 0;">
            <?php echo Auth::csrfField(); ?>
            <button type="submit" class="btn btn-secondary btn-sm" style="background: #fff; color: #dc2626; border: none;">
                <i class="fas fa-sign-out-alt"></i> Stop Impersonating
            </button>
        </form>
    </div>
    <?php endif; ?>
    <header class="app-topbar">
        <div class="topbar-left">
            <button class="topbar-toggle-btn" aria-label="Toggle Navigation">
                <i class="fas fa-bars"></i>
            </button>
            <div class="page-title-box">
                <h1><?php echo e($pageTitle); ?></h1>
                <?php if (!empty($pageSubtitle)): ?>
                    <p><?php echo e($pageSubtitle); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="topbar-right">
            <!-- Gym Badge -->
            <div style="display: flex; align-items: center; gap: 8px; background: var(--bg-app); padding: 4px 14px; border-radius: var(--radius-full); border: 1px solid var(--border-color); font-size: 0.85rem; font-weight: 600;">
                <?php if (!empty($tenant['logo']) && ($currentUser['role'] ?? '') !== 'super_admin'): ?>
                    <img src="<?php echo e(str_starts_with($tenant['logo'], 'http') ? $tenant['logo'] : base_url('/uploads/logos/' . $tenant['logo'])); ?>" style="width: 22px; height: 22px; object-fit: contain; border-radius: 4px;" />
                <?php elseif (($currentUser['role'] ?? '') === 'super_admin'): ?>
                    <i class="fas fa-shield-alt" style="color: var(--lime);"></i>
                <?php else: ?>
                    <i class="fas fa-map-marker-alt" style="color: var(--primary);"></i>
                <?php endif; ?>
                <span><?php echo e(($currentUser['role'] ?? '') === 'super_admin' ? 'Fitisify Master SaaS' : ($tenant['gym_name'] ?? 'Main Gym')); ?></span>
            </div>

            <!-- Theme Toggle Button -->
            <button type="button" class="btn-icon theme-toggle-btn" title="Toggle Dark/Light Mode">
                <i class="fas fa-moon"></i>
            </button>

            <!-- Quick Add / Action Shortcut -->
            <?php if (($currentUser['role'] ?? '') === 'gym_admin' || ($currentUser['role'] ?? '') === 'staff'): ?>
            <a href="<?php echo base_url('/admin/member-entry'); ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-user-plus"></i>
                <span class="d-none d-md-inline">New Member</span>
            </a>
            <?php endif; ?>

            <!-- Logout Link -->
            <a href="<?php echo base_url('/logout'); ?>" class="btn-icon" title="Logout" style="color: var(--danger);">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </header>

    <div class="app-content">
