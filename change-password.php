<?php
/**
 * Mandatory First-Login Password Change Page
 */
ob_start();

require_once __DIR__ . '/core/db.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/helpers.php';

if (!Auth::check()) {
    redirect(base_url('/index2.php'), 'error', 'Please log in with your credentials first.');
}

// Runs the full session re-validation (account status, tenant status); allows this page while must_change_password = 1
Auth::requireAuth();

if (!empty($_SESSION['impersonator'])) {
    redirect(base_url('/admin/index.php'), 'error', 'Password changes are disabled while impersonating a gym.');
}

$user = Auth::user();
$userId = $user['id'];
$error = "";
$success = "";

// Resolve the account record by the table the user actually authenticated against
$authSource = $user['auth_source'] ?? 'users';
$accountId  = (int)($user['account_id'] ?? 0);
$sourceMap  = [
    'users'   => ['table' => 'users',   'pk' => 'id'],
    'staffs'  => ['table' => 'staffs',  'pk' => 'user_id'],
    'members' => ['table' => 'members', 'pk' => 'user_id'],
    'admin'   => ['table' => 'admin',   'pk' => 'user_id'],
];
$source = $sourceMap[$authSource] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    Auth::verifyCsrf();

    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword     = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Verify current temporary password
    $dbUser = null;
    if ($source && $accountId > 0) {
        if ($source['table'] === 'staffs' || $source['table'] === 'members') {
            $dbUser = DB::fetchOne("SELECT * FROM `{$source['table']}` WHERE `{$source['pk']}` = ? AND tenant_id = ?", [$accountId, (int)($user['tenant_id'] ?? 0)]);
        } else {
            $dbUser = DB::fetchOne("SELECT * FROM `{$source['table']}` WHERE `{$source['pk']}` = ?", [$accountId]);
        }
    }
    if (!$dbUser) {
        $error = "User record not found.";
    } elseif (!Auth::verifyPassword($currentPassword, $dbUser['password'] ?? '')) {
        $error = "Current temporary password is incorrect.";
    } elseif (strlen($newPassword) < 8) {
        $error = "New password must be at least 8 characters long.";
    } elseif (!preg_match('/[A-Z]/', $newPassword) || !preg_match('/[a-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
        $error = "New password must contain at least one uppercase letter, one lowercase letter, and one number.";
    } elseif (!hash_equals((string)$newPassword, (string)$confirmPassword)) {
        $error = "New password and confirmation do not match.";
    } elseif (hash_equals((string)$currentPassword, (string)$newPassword)) {
        $error = "Your new password must be different from your temporary password.";
    } else {
        // Hash new password securely
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);

        $updateData = ['password' => $newHash];
        if ($source['table'] === 'users') {
            $updateData['must_change_password'] = 0;
        }
        DB::update($source['table'], $updateData, "`{$source['pk']}` = ?", [$accountId]);

        // Invalidate any outstanding password reset links for this account
        if ($source['table'] === 'users') {
            DB::query("UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL AND token_hash IS NOT NULL", [$accountId]);
        }

        $_SESSION['must_change_password'] = 0;
        @session_regenerate_id(true);
        Auth::auditLog('PASSWORD_CHANGED', "User {$user['username']} updated their initial temporary password.");

        // Redirect to dashboard
        $role = $user['role'];
        if ($role === 'super_admin') {
            redirect(base_url('/superadmin/index.php'), 'success', 'Password successfully updated! Welcome to Super Admin.');
        } elseif ($role === 'member') {
            redirect(base_url('/customer/pages/index.php'), 'success', 'Password updated successfully!');
        } elseif ($role === 'trainer') {
            redirect(base_url('/trainer/index.php'), 'success', 'Password updated successfully!');
        } else {
            redirect(base_url('/admin/index.php'), 'success', 'Password successfully updated! Welcome to your Gym Management Dashboard.');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Permanent Password | Fitisify SaaS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Outfit:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/app.css">
    <style>
        body {
            background: radial-gradient(circle at 50% 0%, rgba(99, 102, 241, 0.15) 0%, transparent 60%), var(--bg-app);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .change-pass-card {
            max-width: 480px;
            width: 100%;
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-xl);
            padding: 36px 32px;
        }

        .card-header-icon {
            width: 60px;
            height: 60px;
            border-radius: 18px;
            background: linear-gradient(135deg, #6366f1, #a855f7);
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            margin-bottom: 16px;
            box-shadow: 0 8px 20px rgba(99, 102, 241, 0.35);
        }
    </style>
</head>
<body>

<div class="change-pass-card">
    <div style="text-align: center; margin-bottom: 24px;">
        <div class="card-header-icon"><i class="fas fa-key"></i></div>
        <h2 style="font-family: 'Outfit', sans-serif; font-size: 1.6rem; font-weight: 800; color: var(--text-main); margin: 0 0 6px;">
            Set Your Permanent Password
        </h2>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0;">
            For your security, please replace the temporary password that was emailed to you.
        </p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger" style="margin-bottom: 20px;">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo e($error); ?></span>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <?php echo Auth::csrfField(); ?>

        <div class="form-group">
            <label class="form-label">Logged In User</label>
            <input type="text" class="form-control" value="<?php echo e($user['username']); ?> (<?php echo e($user['fullname']); ?>)" readonly style="background: var(--bg-app); opacity: 0.8;" />
        </div>

        <div class="form-group">
            <label class="form-label">Current Temporary Password *</label>
            <div class="input-with-icon" style="position: relative; display: flex; align-items: center;">
                <input type="password" id="currPass" name="current_password" class="form-control" placeholder="Enter temporary password from email" required style="width: 100%; padding-right: 44px;" />
                <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('currPass', 'toggleIconCurr')" title="Show / Hide Password" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: transparent; border: none; color: var(--text-muted); cursor: pointer; padding: 8px; z-index: 10;">
                    <i class="far fa-eye" id="toggleIconCurr"></i>
                </button>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">New Permanent Password *</label>
            <div class="input-with-icon" style="position: relative; display: flex; align-items: center;">
                <input type="password" id="newPass" name="new_password" class="form-control" placeholder="Min. 8 chars (Uppercase, Lowercase, Number)" required style="width: 100%; padding-right: 44px;" />
                <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('newPass', 'toggleIconNew')" title="Show / Hide Password" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: transparent; border: none; color: var(--text-muted); cursor: pointer; padding: 8px; z-index: 10;">
                    <i class="far fa-eye" id="toggleIconNew"></i>
                </button>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Confirm New Password *</label>
            <div class="input-with-icon" style="position: relative; display: flex; align-items: center;">
                <input type="password" id="confPass" name="confirm_password" class="form-control" placeholder="Re-type new password" required style="width: 100%; padding-right: 44px;" />
                <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('confPass', 'toggleIconConf')" title="Show / Hide Password" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: transparent; border: none; color: var(--text-muted); cursor: pointer; padding: 8px; z-index: 10;">
                    <i class="far fa-eye" id="toggleIconConf"></i>
                </button>
            </div>
        </div>

        <div style="margin-top: 28px;">
            <button type="submit" name="change_password" value="1" class="btn btn-primary btn-lg" style="width: 100%;">
                <i class="fas fa-check-shield"></i> Save & Launch Dashboard
            </button>
        </div>
    </form>

    <div style="margin-top: 20px; text-align: center;">
        <a href="logout.php" style="color: #ef4444; font-size: 0.85rem; text-decoration: none;">
            <i class="fas fa-sign-out-alt"></i> Logout / Cancel
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
