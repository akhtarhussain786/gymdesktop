<?php
/**
 * Password Reset (token consumption) — single-use, 1 hour expiry, emailed by forgot-password.php
 */
require_once __DIR__ . '/core/db.php';
require_once __DIR__ . '/core/helpers.php';
require_once __DIR__ . '/core/auth.php';

header('Referrer-Policy: no-referrer');

$msg = "";
$type = "info";
$done = false;

$token = (string)($_POST['token'] ?? $_GET['token'] ?? '');

/**
 * Look up a valid (unused, unexpired) reset record for a raw token
 */
function find_reset_record($token) {
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }
    $tokenHash = hash('sha256', $token);
    $row = DB::fetchOne("SELECT * FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() LIMIT 1", [$tokenHash]);
    if (!$row || !hash_equals((string)$row['token_hash'], $tokenHash) || empty($row['user_id'])) {
        return null;
    }
    return $row;
}

$record = find_reset_record($token);
if (!$record) {
    $msg = "This password reset link is invalid or has expired. Please request a new one.";
    $type = "error";
}

if ($record && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();
    $newPass     = (string)($_POST['new_password'] ?? '');
    $confirmPass = (string)($_POST['confirm_password'] ?? '');

    $user = DB::fetchOne("SELECT id, tenant_id, username, status FROM users WHERE id = ?", [(int)$record['user_id']]);

    if (!$user || ($user['status'] ?? '') !== 'active') {
        $msg = "This password reset link is invalid or has expired. Please request a new one.";
        $type = "error";
        $record = null;
    } elseif (strlen($newPass) < 8) {
        $msg = "Password must be at least 8 characters long.";
        $type = "error";
    } elseif (!preg_match('/[A-Z]/', $newPass) || !preg_match('/[a-z]/', $newPass) || !preg_match('/[0-9]/', $newPass)) {
        $msg = "Password must contain at least one uppercase letter, one lowercase letter, and one number.";
        $type = "error";
    } elseif (!hash_equals($newPass, $confirmPass)) {
        $msg = "New password and confirmation password do not match.";
        $type = "error";
    } else {
        // Claim the token atomically so it can only ever be used once
        $claim = DB::query("UPDATE password_resets SET used_at = NOW() WHERE id = ? AND used_at IS NULL AND expires_at > NOW()", [(int)$record['id']]);
        if (!is_array($claim) || (int)$claim['affected'] !== 1) {
            $msg = "This password reset link is invalid or has expired. Please request a new one.";
            $type = "error";
            $record = null;
        } else {
            DB::update('users', [
                'password' => password_hash($newPass, PASSWORD_DEFAULT),
                'must_change_password' => 0
            ], 'id = ?', [(int)$user['id']]);

            // Invalidate any other outstanding links for this account
            DB::query("UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL AND token_hash IS NOT NULL", [(int)$user['id']]);

            Auth::auditLog('PASSWORD_RESET_SUCCESS', "Password reset via emailed link for user {$user['username']}", !empty($user['tenant_id']) ? (int)$user['tenant_id'] : null);

            $msg = "Your password has been updated. You can now sign in with your new password.";
            $type = "success";
            $done = true;
            $record = null;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="referrer" content="no-referrer" />
    <meta name="robots" content="noindex, nofollow" />
    <title>Choose New Password | Fitisify</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Outfit:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo base_url('/assets/css/app.css'); ?>" />
    <style>
        body {
            background: radial-gradient(circle at 50% 0%, rgba(59, 130, 246, 0.15) 0%, transparent 60%), var(--bg-app);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .auth-card {
            width: 100%;
            max-width: 450px;
            background: var(--bg-surface);
            border-radius: var(--radius-xl);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-xl);
            padding: 36px 32px;
        }

        .input-with-icon {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-with-icon > i.field-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            transition: color 0.2s;
            pointer-events: none;
            z-index: 2;
        }

        .input-with-icon .form-control {
            width: 100%;
            padding-left: 42px;
            padding-right: 44px;
        }

        .input-with-icon .form-control:focus ~ i.field-icon {
            color: var(--primary);
        }

        .password-toggle-btn {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            transition: color 0.2s;
            z-index: 10;
            border-radius: 6px;
        }

        .password-toggle-btn:hover {
            color: var(--primary);
            background: rgba(255, 255, 255, 0.05);
        }
    </style>
</head>
<body>
<div class="auth-card">
    <div style="text-align: center; margin-bottom: 24px;">
        <div style="width: 56px; height: 56px; border-radius: 16px; background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: #ffffff; display: inline-flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 14px; box-shadow: 0 8px 20px rgba(59, 130, 246, 0.35);">
            <i class="fas fa-key"></i>
        </div>
        <h2 style="font-family: var(--font-display); font-size: 1.6rem; font-weight: 800; color: var(--text-main); margin: 0 0 6px;">
            Choose a New Password
        </h2>
    </div>

    <?php if (!empty($msg)): ?>
        <div style="background: <?php echo $type === 'success' ? 'rgba(16, 185, 129, 0.12)' : ($type === 'error' ? 'rgba(239, 68, 68, 0.12)' : 'rgba(59, 130, 246, 0.12)'); ?>; border: 1px solid <?php echo $type === 'success' ? 'rgba(16, 185, 129, 0.25)' : ($type === 'error' ? 'rgba(239, 68, 68, 0.25)' : 'rgba(59, 130, 246, 0.25)'); ?>; color: <?php echo $type === 'success' ? '#10b981' : ($type === 'error' ? '#ef4444' : '#3b82f6'); ?>; padding: 12px 14px; border-radius: var(--radius-md); font-size: 0.88rem; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <i class="fas <?php echo $type === 'success' ? 'fa-check-circle' : ($type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'); ?>" style="font-size: 1.1rem; flex-shrink: 0;"></i>
            <div><?php echo e($msg); ?></div>
        </div>
    <?php endif; ?>

    <?php if ($record): ?>
    <form method="POST" action="<?php echo base_url('/reset-password.php'); ?>">
        <?php echo Auth::csrfField(); ?>
        <input type="hidden" name="token" value="<?php echo e($token); ?>" />

        <div class="form-group" style="margin-bottom: 18px;">
            <label class="form-label" style="font-weight: 600; font-size: 0.88rem; margin-bottom: 6px; display: block; color: var(--text-main);">New Password *</label>
            <div class="input-with-icon">
                <input type="password" id="newPassword" name="new_password" class="form-control" placeholder="Min. 8 chars (Uppercase, Lowercase, Number)" required autocomplete="new-password" />
                <i class="fas fa-lock field-icon"></i>
                <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('newPassword', 'toggleIcon1')" title="Show / Hide Password" aria-label="Toggle password visibility">
                    <i class="far fa-eye" id="toggleIcon1"></i>
                </button>
            </div>
        </div>

        <div class="form-group" style="margin-bottom: 22px;">
            <label class="form-label" style="font-weight: 600; font-size: 0.88rem; margin-bottom: 6px; display: block; color: var(--text-main);">Confirm New Password *</label>
            <div class="input-with-icon">
                <input type="password" id="confirmPassword" name="confirm_password" class="form-control" placeholder="Re-type new password" required autocomplete="new-password" />
                <i class="fas fa-lock field-icon"></i>
                <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('confirmPassword', 'toggleIcon2')" title="Show / Hide Password" aria-label="Toggle password visibility">
                    <i class="far fa-eye" id="toggleIcon2"></i>
                </button>
            </div>
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 1rem; font-weight: 700;">
            <i class="fas fa-shield-alt"></i> Save New Password
        </button>
    </form>
    <?php elseif (!$done): ?>
        <a href="<?php echo base_url('/forgot-password.php'); ?>" class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 1rem; font-weight: 700; display: block; text-align: center;">
            <i class="fas fa-redo"></i> Request a New Reset Link
        </a>
    <?php endif; ?>

    <div style="margin-top: 24px; text-align: center; border-top: 1px solid var(--border-color); padding-top: 18px;">
        <a href="<?php echo base_url('/index2.php'); ?>" style="font-size: 0.88rem; color: var(--primary); font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
            <i class="fas fa-arrow-left"></i> Return to Login Page
        </a>
    </div>
</div>

<script>
function togglePasswordVisibility(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    if (!input || !icon) return;

    const isPassword = input.getAttribute('type') === 'password';
    input.setAttribute('type', isPassword ? 'text' : 'password');

    if (isPassword) {
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}
</script>
</body>
</html>
