<?php
require_once __DIR__ . '/core/db.php';
require_once __DIR__ . '/core/helpers.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/mailer.php';

$msg = "";
$type = "info";

// Rate limits (per rolling hour)
const RESET_MAX_PER_IDENTIFIER = 3;
const RESET_MAX_PER_IP = 10;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();
    $input = trim((string)($_POST['identity'] ?? ''));

    if ($input === '' || strlen($input) > 191) {
        $msg = "Please enter your registered username or email address.";
        $type = "error";
    } else {
        $genericMsg = "If an account exists with the provided details, a password reset link has been sent to its registered email address. The link expires in 1 hour.";
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $identifierHash = hash('sha256', strtolower($input));

        $recentForIdentifier = (int)DB::fetchValue("SELECT COUNT(*) FROM password_resets WHERE identifier_hash = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)", [$identifierHash]);
        $recentForIp = (int)DB::fetchValue("SELECT COUNT(*) FROM password_resets WHERE ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)", [$ip]);

        if ($recentForIdentifier >= RESET_MAX_PER_IDENTIFIER || $recentForIp >= RESET_MAX_PER_IP) {
            error_log("Password reset rate limit hit (ip={$ip})");
        } else {
            $accounts = DB::fetchAll("SELECT id, tenant_id, username, fullname, email, status FROM users WHERE (LOWER(username) = LOWER(?) OR LOWER(email) = LOWER(?)) AND status = 'active' LIMIT 5", [$input, $input]);
            $sentAny = false;
            foreach ($accounts as $acct) {
                $email = trim((string)($acct['email'] ?? ''));
                if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    continue;
                }
                // Invalidate older unused links for this account
                DB::query("UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL AND token_hash IS NOT NULL", [(int)$acct['id']]);

                $token = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $token);
                $res = DB::query(
                    "INSERT INTO password_resets (user_id, identifier_hash, token_hash, ip_address, expires_at, created_at) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR), NOW())",
                    [(int)$acct['id'], $identifierHash, $tokenHash, $ip]
                );
                if (!is_array($res)) {
                    continue;
                }
                $sentAny = true;
                $resetUrl = base_url('/reset-password.php') . '?token=' . urlencode($token);
                $mailRes = Mailer::sendPasswordResetEmail($email, $acct['fullname'] ?? $acct['username'], $resetUrl, $acct['tenant_id'] ?? null);
                if (empty($mailRes['success'])) {
                    error_log('Password reset email failed for user #' . (int)$acct['id'] . ': ' . ($mailRes['error'] ?? 'unknown'));
                }
                Auth::auditLog('PASSWORD_RESET_REQUESTED', "Password reset link requested for user {$acct['username']}", !empty($acct['tenant_id']) ? (int)$acct['tenant_id'] : null);
            }

            if (!$sentAny) {
                // Record the attempt so the per-identifier / per-IP limits also apply to unknown accounts
                DB::query(
                    "INSERT INTO password_resets (user_id, identifier_hash, token_hash, ip_address, expires_at, created_at) VALUES (NULL, ?, NULL, ?, NULL, NOW())",
                    [$identifierHash, $ip]
                );
            }
        }

        // Same response whether or not the account exists / was rate limited
        $msg = $genericMsg;
        $type = "info";
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Reset Password | Fitisify</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Outfit:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/app.css" />
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
            Reset Password
        </h2>
        <p style="font-size: 0.88rem; color: var(--text-muted); margin: 0;">
            Enter your username or registered email address and we will email you a secure reset link.
        </p>
    </div>

    <?php if (!empty($msg)): ?>
        <div style="background: <?php echo $type === 'success' ? 'rgba(16, 185, 129, 0.12)' : ($type === 'error' ? 'rgba(239, 68, 68, 0.12)' : 'rgba(59, 130, 246, 0.12)'); ?>; border: 1px solid <?php echo $type === 'success' ? 'rgba(16, 185, 129, 0.25)' : ($type === 'error' ? 'rgba(239, 68, 68, 0.25)' : 'rgba(59, 130, 246, 0.25)'); ?>; color: <?php echo $type === 'success' ? '#10b981' : ($type === 'error' ? '#ef4444' : '#3b82f6'); ?>; padding: 12px 14px; border-radius: var(--radius-md); font-size: 0.88rem; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <i class="fas <?php echo $type === 'success' ? 'fa-check-circle' : ($type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'); ?>" style="font-size: 1.1rem; flex-shrink: 0;"></i>
            <div><?php echo e($msg); ?></div>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <?php echo Auth::csrfField(); ?>
        
        <div class="form-group" style="margin-bottom: 18px;">
            <label class="form-label" style="font-weight: 600; font-size: 0.88rem; margin-bottom: 6px; display: block; color: var(--text-main);">Username or Registered Email *</label>
            <div class="input-with-icon">
                <input type="text" name="identity" class="form-control" placeholder="Enter username or email" value="" required autofocus autocomplete="username" />
                <i class="fas fa-user field-icon"></i>
            </div>
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 1rem; font-weight: 700;">
            <i class="fas fa-paper-plane"></i> Email Me a Reset Link
        </button>
    </form>

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
