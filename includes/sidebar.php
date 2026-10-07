<?php
$currentPage = $page ?? 'dashboard';
$role = $currentUser['role'] ?? '';
$tenant = Tenant::getCurrent();
?>
<aside class="app-sidebar">
    <div class="sidebar-brand">
        <div class="brand-logo" style="overflow: hidden; display: flex; align-items: center; justify-content: center;">
            <?php if (!empty($tenant['logo']) && $role !== 'super_admin'): ?>
                <img src="<?php echo e(str_starts_with($tenant['logo'], 'http') ? $tenant['logo'] : base_url('/uploads/logos/' . $tenant['logo'])); ?>" style="width: 100%; height: 100%; object-fit: contain; border-radius: 6px;" />
            <?php else: ?>
                <i class="fas fa-dumbbell"></i>
            <?php endif; ?>
        </div>
        <div style="overflow: hidden;">
            <div class="brand-text"><?php echo e($role === 'super_admin' ? 'FITISIFY SAAS' : ($tenant['gym_name'] ?? 'FITISIFY GYM')); ?></div>
        </div>
        <?php if ($role === 'super_admin'): ?>
            <span class="tenant-tag" style="background:#ef4444;color:#fff;">SUPER</span>
        <?php else: ?>
            <span class="tenant-tag"><?php echo e($tenant['plan_name'] ?? 'PRO'); ?></span>
        <?php endif; ?>
    </div>

    <ul class="sidebar-menu">
        <?php if ($role === 'super_admin'): ?>
            <!-- Super Admin Navigation -->
            <div class="sidebar-section-title">Platform Admin</div>
            <li class="sidebar-item <?php echo $currentPage === 'super_dashboard' ? 'active' : ''; ?>">
                <a href="<?php echo base_url('/superadmin/index'); ?>" class="sidebar-link">
                    <i class="fas fa-chart-line"></i>
                    <span>Platform Overview</span>
                </a>
            </li>
            <li class="sidebar-item <?php echo $currentPage === 'super_tenants' ? 'active' : ''; ?>">
                <a href="<?php echo base_url('/superadmin/tenants'); ?>" class="sidebar-link">
                    <i class="fas fa-building"></i>
                    <span>Gym Tenants</span>
                </a>
            </li>
            <li class="sidebar-item <?php echo $currentPage === 'super_plans' ? 'active' : ''; ?>">
                <a href="<?php echo base_url('/superadmin/plans'); ?>" class="sidebar-link">
                    <i class="fas fa-tags"></i>
                    <span>Subscription Plans</span>
                </a>
            </li>
            <li class="sidebar-item <?php echo $currentPage === 'super_payments' ? 'active' : ''; ?>">
                <a href="<?php echo base_url('/superadmin/payments'); ?>" class="sidebar-link">
                    <i class="fas fa-credit-card"></i>
                    <span>SaaS Payments & Orders</span>
                </a>
            </li>
            <li class="sidebar-item <?php echo $currentPage === 'super_testimonials' ? 'active' : ''; ?>">
                <a href="<?php echo base_url('/superadmin/testimonials'); ?>" class="sidebar-link">
                    <i class="fas fa-quote-right"></i>
                    <span>Testimonials</span>
                </a>
            </li>
            <li class="sidebar-item <?php echo $currentPage === 'super_leads' ? 'active' : ''; ?>">
                <a href="<?php echo base_url('/superadmin/visitor-leads'); ?>" class="sidebar-link">
                    <i class="fas fa-address-book" style="color: #10b981;"></i>
                    <span>Visitor Leads & Traffic</span>
                </a>
            </li>
            <li class="sidebar-item <?php echo $currentPage === 'super_seo' ? 'active' : ''; ?>">
                <a href="<?php echo base_url('/superadmin/seo-settings'); ?>" class="sidebar-link">
                    <i class="fas fa-robot" style="color: #a855f7;"></i>
                    <span>AI SEO & Metas</span>
                </a>
            </li>
            <li class="sidebar-item <?php echo $currentPage === 'super_settings' ? 'active' : ''; ?>">
                <a href="<?php echo base_url('/superadmin/settings'); ?>" class="sidebar-link">
                    <i class="fas fa-sliders-h"></i>
                    <span>Global Settings</span>
                </a>
            </li>

        <?php elseif ($role === 'gym_admin' || $role === 'staff'): ?>
            <!-- Gym Admin & Staff Navigation -->
            <div class="sidebar-section-title">Main Dashboard</div>
            <li class="sidebar-item <?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>">
                <a href="<?php echo base_url('/admin/index'); ?>" class="sidebar-link">
                    <i class="fas fa-home"></i>
                    <span>Overview</span>
                </a>
            </li>

            <div class="sidebar-section-title">Members & Staff</div>
            <li class="sidebar-item <?php echo $currentPage === 'members' || $currentPage === 'members-entry' ? 'active' : ''; ?>">
                <a href="<?php echo base_url('/admin/members'); ?>" class="sidebar-link">
                    <i class="fas fa-users"></i>
                    <span>Members List</span>
                </a>
            </li>
            <?php if ($role === 'gym_admin'): ?>
            <li class="sidebar-item <?php echo $currentPage === 'staffs' ? 'active' : ''; ?>">
                <a href="<?php echo base_url('/admin/staffs'); ?>" class="sidebar-link">
                    <i class="fas fa-user-shield"></i>
                    <span>Staff & Trainers</span>
                </a>
            </li>
            <?php endif; ?>
            <li class="sidebar-item <?php echo $currentPage === 'attendance' ? 'active' : ''; ?>">
                <a href="<?php echo base_url('/admin/attendance'); ?>" class="sidebar-link">
                    <i class="fas fa-clipboard-check"></i>
                    <span>Attendance</span>
                </a>
            </li>

            <div class="sidebar-section-title">Finance & Sales</div>
            <li class="sidebar-item <?php echo $currentPage === 'payment' ? 'active' : ''; ?>">
                <a href="<?php echo base_url('/admin/payment'); ?>" class="sidebar-link">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <span>Payments & Invoices</span>
                </a>
            </li>
            <?php if ($role === 'gym_admin'): ?>
            <li class="sidebar-item <?php echo $currentPage === 'rates' ? 'active' : ''; ?>">
                <a href="<?php echo base_url('/admin/rates'); ?>" class="sidebar-link">
                    <i class="fas fa-tags"></i>
                    <span>Service Packages & Rates</span>
                </a>
            </li>
            <li class="sidebar-item <?php echo $currentPage === 'expenses' ? 'active' : ''; ?>">
                <a href="<?php echo base_url('/admin/expenses'); ?>" class="sidebar-link">
                    <i class="fas fa-receipt"></i>
                    <span>Expenses</span>
                </a>
            </li>
            <?php endif; ?>
            <li class="sidebar-item <?php echo $currentPage === 'equipment' ? 'active' : ''; ?>">
                <a href="<?php echo base_url('/admin/equipment'); ?>" class="sidebar-link">
                    <i class="fas fa-dumbbell"></i>
                    <span>Equipment Inventory</span>
                </a>
            </li>

            <div class="sidebar-section-title">Fitness Programs</div>
            <li class="sidebar-item <?php echo $currentPage === 'workouts' ? 'active' : ''; ?>">
                <a href="<?php echo base_url('/admin/workouts'); ?>" class="sidebar-link">
                    <i class="fas fa-running"></i>
                    <span>Workout Plans</span>
                </a>
            </li>
            <li class="sidebar-item <?php echo $currentPage === 'diet' ? 'active' : ''; ?>">
                <a href="<?php echo base_url('/admin/diet'); ?>" class="sidebar-link">
                    <i class="fas fa-apple-alt"></i>
                    <span>Diet Plans</span>
                </a>
            </li>
            <li class="sidebar-item <?php echo $currentPage === 'classes' ? 'active' : ''; ?>">
                <a href="<?php echo base_url('/admin/classes'); ?>" class="sidebar-link">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Classes & Schedules</span>
                </a>
            </li>

            <div class="sidebar-section-title">Analytics & Config</div>
            <?php if ($role === 'gym_admin'): ?>
            <li class="sidebar-item <?php echo $currentPage === 'reports' ? 'active' : ''; ?>">
                <a href="<?php echo base_url('/admin/reports'); ?>" class="sidebar-link">
                    <i class="fas fa-chart-pie"></i>
                    <span>Reports & P&L</span>
                </a>
            </li>
            <?php endif; ?>
            <li class="sidebar-item <?php echo $currentPage === 'announcements' ? 'active' : ''; ?>">
                <a href="<?php echo base_url('/admin/manage-announcement'); ?>" class="sidebar-link">
                    <i class="fas fa-bullhorn"></i>
                    <span>Announcements</span>
                </a>
            </li>
            <li class="sidebar-item <?php echo $currentPage === 'inquiries' ? 'active' : ''; ?>">
                <a href="<?php echo base_url('/admin/inquiries'); ?>" class="sidebar-link">
                    <i class="fas fa-life-ring"></i>
                    <span>Member Requests</span>
                </a>
            </li>
            <?php if ($role === 'gym_admin'): ?>
            <li class="sidebar-item <?php echo $currentPage === 'settings' ? 'active' : ''; ?>">
                <a href="<?php echo base_url('/admin/settings'); ?>" class="sidebar-link">
                    <i class="fas fa-palette"></i>
                    <span>Upload Gym Logo & Branding</span>
                </a>
            </li>
            <?php endif; ?>

        <?php elseif ($role === 'trainer'): ?>
            <!-- Trainer Navigation -->
            <div class="sidebar-section-title">Trainer Center</div>
            <li class="sidebar-item <?php echo $currentPage === 'trainer_dashboard' ? 'active' : ''; ?>">
                <a href="<?php echo base_url('/trainer/index'); ?>" class="sidebar-link">
                    <i class="fas fa-home"></i>
                    <span>My Trainees</span>
                </a>
            </li>
            <li class="sidebar-item <?php echo $currentPage === 'workouts' ? 'active' : ''; ?>">
                <a href="<?php echo base_url('/admin/workouts'); ?>" class="sidebar-link">
                    <i class="fas fa-running"></i>
                    <span>Workout Plans</span>
                </a>
            </li>
            <li class="sidebar-item <?php echo $currentPage === 'diet' ? 'active' : ''; ?>">
                <a href="<?php echo base_url('/admin/diet'); ?>" class="sidebar-link">
                    <i class="fas fa-apple-alt"></i>
                    <span>Diet Plans</span>
                </a>
            </li>
            <li class="sidebar-item <?php echo $currentPage === 'classes' ? 'active' : ''; ?>">
                <a href="<?php echo base_url('/admin/classes'); ?>" class="sidebar-link">
                    <i class="fas fa-calendar-alt"></i>
                    <span>My Class Schedule</span>
                </a>
            </li>

        <?php elseif ($role === 'member'): ?>
            <!-- Member Portal Navigation -->
            <div class="sidebar-section-title">Member Portal</div>
            <li class="sidebar-item <?php echo $currentPage === 'member_dashboard' ? 'active' : ''; ?>">
                <a href="<?php echo base_url('/customer/pages/index'); ?>" class="sidebar-link">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>My Dashboard</span>
                </a>
            </li>
            <li class="sidebar-item <?php echo $currentPage === 'member_plans' ? 'active' : ''; ?>">
                <a href="<?php echo base_url('/customer/pages/my-routines'); ?>" class="sidebar-link">
                    <i class="fas fa-dumbbell"></i>
                    <span>My Workout & Diet</span>
                </a>
            </li>
            <li class="sidebar-item <?php echo $currentPage === 'member_attendance' ? 'active' : ''; ?>">
                <a href="<?php echo base_url('/customer/pages/my-attendance'); ?>" class="sidebar-link">
                    <i class="fas fa-calendar-check"></i>
                    <span>Attendance Log</span>
                </a>
            </li>
            <li class="sidebar-item <?php echo $currentPage === 'member_invoices' ? 'active' : ''; ?>">
                <a href="<?php echo base_url('/customer/pages/my-invoices'); ?>" class="sidebar-link">
                    <i class="fas fa-receipt"></i>
                    <span>Payment History</span>
                </a>
            </li>
            <li class="sidebar-item <?php echo $currentPage === 'member_todo' ? 'active' : ''; ?>">
                <a href="<?php echo base_url('/customer/pages/to-do'); ?>" class="sidebar-link">
                    <i class="fas fa-tasks"></i>
                    <span>To-Do Tracker</span>
                </a>
            </li>
            <li class="sidebar-item <?php echo $currentPage === 'member_announcements' ? 'active' : ''; ?>">
                <a href="<?php echo base_url('/customer/pages/announcement'); ?>" class="sidebar-link">
                    <i class="fas fa-bullhorn"></i>
                    <span>Gym Updates</span>
                </a>
            </li>
        <?php endif; ?>
    </ul>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="sidebar-user-avatar">
                <?php echo strtoupper(substr($currentUser['fullname'] ?? 'U', 0, 1)); ?>
            </div>
            <div class="sidebar-user-info">
                <div class="sidebar-user-name"><?php echo e($currentUser['fullname'] ?? 'Administrator'); ?></div>
                <div class="sidebar-user-role"><?php echo e(str_replace('_', ' ', $role)); ?></div>
            </div>
            <a href="<?php echo base_url('/logout'); ?>" title="Logout" class="btn-icon" style="border:none;background:none;color:#ef4444;width:30px;height:30px;">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </div>
</aside>
