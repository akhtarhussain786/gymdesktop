<?php
ob_start();

require_once __DIR__ . '/core/db.php';
require_once __DIR__ . '/core/tenant.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/helpers.php';
require_once __DIR__ . '/core/visitor_tracker.php';

// Log visit
VisitorTracker::logVisit('/index2');

// If already logged in, redirect to respective dashboard
if (Auth::check() && !empty($_SESSION['role'])) {
    $role = $_SESSION['role'];
    if ($role === 'super_admin') {
        redirect(base_url('/superadmin/index'));
    } elseif ($role === 'member') {
        redirect(base_url('/customer/pages/index'));
    } elseif ($role === 'trainer') {
        redirect(base_url('/trainer/index'));
    } else {
        redirect(base_url('/admin/index'));
    }
}

$login_error = "";
$flash = get_flash();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    Auth::verifyCsrf();
    $username = $_POST['user'] ?? '';
    $password = $_POST['pass'] ?? '';

    $res = Auth::attempt($username, $password);
    if ($res['success']) {
        $user = $res['user'];
        $role = $user['role'];

        if (!empty($user['must_change_password']) && (int)$user['must_change_password'] === 1) {
            redirect(base_url('/change-password'), 'info', 'Welcome! Please set a permanent password for your account.');
        }

        if ($role === 'super_admin') {
            redirect(base_url('/superadmin/index'), 'success', 'Welcome back, Super Admin!');
        } elseif ($role === 'member') {
            redirect(base_url('/customer/pages/index'), 'success', 'Welcome back to your fitness dashboard!');
        } elseif ($role === 'trainer') {
            redirect(base_url('/trainer/index'), 'success', 'Welcome back, Trainer!');
        } else {
            redirect(base_url('/admin/index'), 'success', 'Welcome back, ' . ($user['fullname'] ?? 'Admin') . '!');
        }
    } else {
        $login_error = $res['message'] ?? 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Login | Fitisify Gym SaaS Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Outfit:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/app.css" />
    <style>
        body {
            background: radial-gradient(circle at 10% 20%, rgba(59, 130, 246, 0.15) 0%, transparent 40%),
                        radial-gradient(circle at 90% 80%, rgba(16, 185, 129, 0.12) 0%, transparent 40%),
                        var(--bg-app);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .auth-card {
            width: 100%;
            max-width: 440px;
            background: var(--bg-surface);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-xl);
            padding: 36px 32px;
            position: relative;
            z-index: 10;
        }

        .auth-brand {
            text-align: center;
            margin-bottom: 28px;
        }

        .auth-logo-badge {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            margin-bottom: 14px;
            box-shadow: 0 8px 20px rgba(59, 130, 246, 0.35);
        }

        .auth-brand h2 {
            font-family: var(--font-display);
            font-size: 1.65rem;
            font-weight: 800;
            color: var(--text-main);
            letter-spacing: -0.5px;
        }

        .auth-brand p {
            font-size: 0.88rem;
            color: var(--text-muted);
            margin-top: 4px;
        }

        .input-with-icon {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-with-icon > i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
            transition: color 0.2s;
            pointer-events: none;
            z-index: 2;
        }

        .input-with-icon .form-control {
            width: 100%;
            padding-left: 42px;
            padding-right: 44px;
        }

        .input-with-icon .form-control:focus ~ i {
            color: var(--primary);
        }

        .password-toggle-btn {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            transition: color 0.2s;
            z-index: 10;
        }

        .password-toggle-btn i {
            position: static !important;
            transform: none !important;
            color: inherit !important;
        }

        .password-toggle-btn:hover {
            color: var(--primary);
        }

        .role-hint {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid var(--border-color);
            font-size: 0.82rem;
            color: var(--text-muted);
        }

        .nexora-footer {
            margin-top: 20px;
            text-align: center;
            font-size: 0.82rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding-top: 14px;
            border-top: 1px dashed rgba(255, 255, 255, 0.1);
        }

        .nexora-badge {
            color: var(--primary);
            font-weight: 800;
            letter-spacing: 0.5px;
            font-family: var(--font-display);
        }
    </style>
</head>
<body>

<div class="auth-card">
    <div class="auth-brand">
        <div class="auth-logo-badge">
            <i class="fas fa-cubes"></i>
        </div>
        <h2>FITISIFY GYM SAAS</h2>
        <p>Multi-Tenant Gym Management Portal</p>
    </div>

    <?php if (empty($login_error) && !empty($flash['message'])): ?>
        <div style="background: rgba(59, 130, 246, 0.12); border: 1px solid rgba(59, 130, 246, 0.25); color: <?php echo ($flash['type'] ?? '') === 'error' ? '#ef4444' : '#3b82f6'; ?>; padding: 12px 14px; border-radius: var(--radius-md); font-size: 0.88rem; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-info-circle" style="font-size: 1.1rem;"></i>
            <div><?php echo e($flash['message']); ?></div>
        </div>
    <?php endif; ?>

    <?php if (!empty($login_error)): ?>
        <div style="background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.25); color: #ef4444; padding: 12px 14px; border-radius: var(--radius-md); font-size: 0.88rem; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-exclamation-circle" style="font-size: 1.1rem;"></i>
            <div><?php echo e($login_error); ?></div>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <?php echo Auth::csrfField(); ?>
        
        <div class="form-group">
            <label class="form-label">Username or Email</label>
            <div class="input-with-icon">
                <input type="text" name="user" class="form-control" placeholder="Enter your username or email" required autofocus autocomplete="username" />
                <i class="fas fa-user"></i>
            </div>
        </div>

        <div class="form-group">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                <label class="form-label" style="margin-bottom: 0;">Password</label>
                <a href="<?php echo base_url('/forgot-password'); ?>" style="font-size: 0.8rem; color: var(--primary);">Forgot password?</a>
            </div>
            <div class="input-with-icon">
                <input type="password" id="passwordInput" name="pass" class="form-control" placeholder="Enter your password" required autocomplete="current-password" />
                <i class="fas fa-lock"></i>
                <button type="button" class="password-toggle-btn" id="togglePasswordBtn" title="Toggle password visibility">
                    <i class="far fa-eye" id="togglePasswordIcon"></i>
                </button>
            </div>
        </div>

        <div style="margin-top: 24px;">
            <button type="submit" name="login" value="1" class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 1rem;">
                <i class="fas fa-sign-in-alt"></i> Sign In to Account
            </button>
        </div>
    </form>

    <div style="margin-top: 20px; display: flex; flex-direction: column; gap: 8px;">
        <a href="<?php echo base_url('/register-gym'); ?>" class="btn btn-secondary btn-sm" style="width: 100%;">
            <i class="fas fa-plus-circle"></i> Register New Gym / Start Free Trial
        </a>
        <a href="<?php echo base_url('/'); ?>" style="text-align: center; font-size: 0.85rem; color: var(--primary); font-weight: 600; text-decoration: none; padding: 4px;">
            <i class="fas fa-sparkles"></i> Explore SaaS Platform Features & Pricing &rarr;
        </a>
    </div>

    <div class="role-hint">
        <i class="fas fa-shield-alt" style="color: var(--secondary);"></i>
        <span>Supports Super Admin, Gym Admin, Staff, Trainer & Member logins</span>
    </div>

    <div class="nexora-footer">
        <span>Powered & Developed by</span>
        <span class="nexora-badge">NEXORALAB TECHNOLOGY</span>
    </div>
</div>

<script src="assets/js/app.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const passwordInput = document.getElementById('passwordInput');
    const toggleBtn = document.getElementById('togglePasswordBtn');
    const toggleIcon = document.getElementById('togglePasswordIcon');

    if (toggleBtn && passwordInput && toggleIcon) {
        toggleBtn.addEventListener('click', function() {
            const isPassword = passwordInput.getAttribute('type') === 'password';
            passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
            
            if (isPassword) {
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        });
    }
});
</script>
</body>
</html>