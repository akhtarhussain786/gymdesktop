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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Outfit:wght@600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo base_url('/assets/css/app.css?v=' . (file_exists(__DIR__ . '/assets/css/app.css') ? filemtime(__DIR__ . '/assets/css/app.css') : '1.0.8')); ?>" />
    <style>
        body {
            background: radial-gradient(circle at 10% 20%, rgba(59, 130, 246, 0.15) 0%, transparent 40%),
                        radial-gradient(circle at 90% 80%, rgba(16, 185, 129, 0.12) 0%, transparent 40%),
                        #070a0f;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px 16px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: #f8fafc;
        }

        .auth-card {
            width: 100%;
            max-width: 440px;
            background: #0d1219;
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.7);
            padding: 36px 30px;
            position: relative;
            z-index: 10;
        }

        .auth-brand {
            text-align: center;
            margin-bottom: 26px;
        }

        .form-group {
            margin-bottom: 18px;
            width: 100%;
        }

        .form-label {
            display: block;
            font-size: 0.88rem;
            font-weight: 700;
            color: #e2e8f0;
            margin-bottom: 8px;
        }

        .input-with-icon {
            position: relative;
            display: flex;
            align-items: center;
            width: 100%;
        }

        .input-with-icon > i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            font-size: 1rem;
            transition: color 0.2s;
            pointer-events: none;
            z-index: 2;
        }

        .input-with-icon .form-control {
            width: 100% !important;
            background: #111720 !important;
            border: 1px solid rgba(255, 255, 255, 0.15) !important;
            border-radius: 10px !important;
            padding: 13px 44px 13px 44px !important;
            color: #ffffff !important;
            font-size: 16px !important;
            outline: none !important;
            box-sizing: border-box !important;
            height: 48px !important;
            transition: all 0.2s ease;
        }

        .input-with-icon .form-control:focus {
            border-color: #c7ff2e !important;
            box-shadow: 0 0 16px rgba(199, 255, 46, 0.3) !important;
            background: #151c27 !important;
            color: #ffffff !important;
        }

        .input-with-icon .form-control:focus ~ i {
            color: #c7ff2e !important;
        }

        /* Chrome/Safari Autofill Dark Theme Fix */
        input:-webkit-autofill,
        input:-webkit-autofill:hover, 
        input:-webkit-autofill:focus, 
        input:-webkit-autofill:active {
            -webkit-box-shadow: 0 0 0 1000px #111720 inset !important;
            -webkit-text-fill-color: #ffffff !important;
            transition: background-color 5000s ease-in-out 0s;
            caret-color: #ffffff !important;
        }

        .password-toggle-btn {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            color: #64748b;
            cursor: pointer;
            padding: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.05rem;
            transition: color 0.2s;
            z-index: 10;
        }

        .password-toggle-btn:hover {
            color: #c7ff2e;
        }

        .password-toggle-btn i {
            position: static !important;
            transform: none !important;
            color: inherit !important;
        }

        .btn-submit-login {
            width: 100%;
            padding: 13px 20px;
            font-size: 1rem;
            font-weight: 800;
            background: #c7ff2e !important;
            color: #05080d !important;
            border: none !important;
            border-radius: 10px !important;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 4px 18px rgba(199, 255, 46, 0.38) !important;
            transition: all 0.2s ease;
        }

        .btn-submit-login:hover {
            background: #b8f522 !important;
            box-shadow: 0 0 28px rgba(199, 255, 46, 0.55) !important;
            transform: translateY(-1px);
        }

        .btn-submit-login:active {
            transform: scale(0.98);
        }

        .role-hint {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            font-size: 0.82rem;
            color: #64748b;
            text-align: center;
        }

        .nexora-footer {
            margin-top: 20px;
            text-align: center;
            font-size: 0.82rem;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding-top: 14px;
            border-top: 1px dashed rgba(255, 255, 255, 0.1);
        }

        .nexora-badge {
            color: #c7ff2e;
            font-weight: 800;
            letter-spacing: 0.5px;
            font-family: 'Outfit', sans-serif;
        }

        @media (max-width: 480px) {
            body {
                padding: 12px 10px !important;
                align-items: flex-start !important;
                padding-top: 28px !important;
            }
            .auth-card {
                padding: 24px 18px !important;
                border-radius: 16px !important;
                max-width: 100% !important;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.6) !important;
            }
        }
    </style>
</head>
<body>

<div class="auth-card">
    <div class="auth-brand" style="text-align: center; margin-bottom: 26px;">
        <a href="<?php echo base_url('/'); ?>" style="text-decoration: none; display: inline-flex; align-items: center; gap: 10px; margin-bottom: 8px;">
            <div style="width: 44px; height: 44px; background: #151b23; border: 1px solid rgba(204, 255, 0, 0.35); border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; box-shadow: 0 0 16px rgba(204, 255, 0, 0.2);">
                <i class="fa-solid fa-bolt" style="color: #ccff00; font-size: 1.4rem;"></i>
            </div>
            <span style="font-family: 'Outfit', 'Plus Jakarta Sans', sans-serif; font-size: 1.85rem; font-weight: 900; color: #ffffff; letter-spacing: -0.5px; display: inline-flex; align-items: center; gap: 6px;">
                FITISIFY <span style="background: #ccff00; color: #05080d; font-size: 0.8rem; font-weight: 900; padding: 2px 7px; border-radius: 6px; letter-spacing: 0.5px; line-height: 1.1;">OS</span>
            </span>
        </a>
        <p style="font-size: 0.88rem; color: var(--text-muted); margin: 2px 0 0 0;">Multi-Tenant Gym Management Portal</p>
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
            <button type="submit" name="login" value="1" class="btn btn-primary btn-submit-login">
                <i class="fas fa-sign-in-alt"></i> Sign In to Account
            </button>
        </div>
    </form>

    <div style="margin-top: 20px; display: flex; flex-direction: column; gap: 8px;">
        <a href="<?php echo base_url('/register-gym'); ?>" class="btn btn-secondary btn-sm" style="width: 100%; border-radius: 10px; padding: 10px 14px;">
            <i class="fas fa-plus-circle"></i> Register New Gym / Start Free Trial
        </a>
        <a href="<?php echo base_url('/'); ?>" style="text-align: center; font-size: 0.85rem; color: var(--primary); font-weight: 600; text-decoration: none; padding: 6px;">
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

<script src="<?php echo base_url('/assets/js/app.js?v=' . (file_exists(__DIR__ . '/assets/js/app.js') ? filemtime(__DIR__ . '/assets/js/app.js') : '1.0.8')); ?>"></script>
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