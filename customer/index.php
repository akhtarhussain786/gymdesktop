<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helpers.php';

if (Auth::check()) {
    redirect(base_url('/customer/pages/index'));
}

$login_error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();
    $res = Auth::attempt($_POST['user'] ?? '', $_POST['pass'] ?? '');
    if ($res['success']) {
        redirect(base_url('/customer/pages/index'), 'success', 'Welcome to your member portal!');
    } else {
        $login_error = $res['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Customer Login | Fitisify Gym</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Outfit:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/app.css" />
    <style>
        body {
            background: var(--bg-app);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .auth-card {
            width: 100%;
            max-width: 420px;
            background: var(--bg-surface);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-xl);
            padding: 36px 30px;
        }
    </style>
</head>
<body>
<div class="auth-card">
    <div style="text-align: center; margin-bottom: 24px;">
        <div style="width: 50px; height: 50px; border-radius: 14px; background: linear-gradient(135deg, #10b981, #059669); color: white; display: inline-flex; align-items: center; justify-content: center; font-size: 1.4rem; margin-bottom: 12px;">
            <i class="fas fa-user"></i>
        </div>
        <h2 style="font-family: var(--font-display); font-size: 1.5rem; font-weight: 800; color: var(--text-main);">Customer Login</h2>
        <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 4px;">Access your workout routines, attendance & invoices</p>
    </div>

    <?php if (!empty($login_error)): ?>
        <div style="background: rgba(239, 68, 68, 0.12); color: #ef4444; padding: 12px 14px; border-radius: var(--radius-md); font-size: 0.88rem; margin-bottom: 20px;">
            <i class="fas fa-exclamation-circle"></i> <?php echo e($login_error); ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <?php echo Auth::csrfField(); ?>
        <div class="form-group">
            <label class="form-label">Member Username</label>
            <input type="text" name="user" class="form-control" placeholder="Enter username" required autofocus />
        </div>
        <div class="form-group">
            <label class="form-label">Password</label>
            <input type="password" name="pass" class="form-control" placeholder="Enter password" required />
        </div>
        <div style="margin-top: 20px;">
            <button type="submit" class="btn btn-success" style="width: 100%; padding: 12px;">
                <i class="fas fa-sign-in-alt"></i> Sign In to Member Portal
            </button>
        </div>
    </form>

    <div style="margin-top: 20px; text-align: center;">
        <a href="<?php echo base_url('/index2'); ?>" style="font-size: 0.85rem; color: var(--text-muted);">
            <i class="fas fa-arrow-left"></i> Go to Universal Login
        </a>
    </div>
</div>
</body>
</html>
