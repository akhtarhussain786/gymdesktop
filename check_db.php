<?php
/**
 * Database Diagnostic Tool
 * Access in browser: https://gymsaas.nexoralabtechnologies.com/check_db.php
 */

header('Content-Type: text/html; charset=utf-8');

$envPath = __DIR__ . '/.env';
$envExists = file_exists($envPath);
$envReadable = $envExists && is_readable($envPath);

$envVars = [];
if ($envReadable) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || str_starts_with($line, '#') || str_starts_with($line, ';')) continue;
        $pos = strpos($line, '=');
        if ($pos !== false) {
            $k = trim(substr($line, 0, $pos));
            $v = trim(substr($line, $pos + 1));
            if ((str_starts_with($v, '"') && str_ends_with($v, '"')) || (str_starts_with($v, "'") && str_ends_with($v, "'"))) {
                $v = substr($v, 1, -1);
            }
            $envVars[$k] = $v;
        }
    }
}

$dbHost = $envVars['DB_HOST'] ?? 'localhost';
$dbUser = $envVars['DB_USER'] ?? '';
$dbPass = $envVars['DB_PASS'] ?? '';
$dbName = $envVars['DB_NAME'] ?? '';

// Try connecting to combinations
$hostsToTry = array_unique([$dbHost, 'localhost', '127.0.0.1']);
$namesToTry = array_unique([$dbName]);
if (preg_match('/^([a-z0-9]+)_\1_(.+)$/i', $dbName, $m)) {
    $namesToTry[] = $m[1] . '_' . $m[2];
}

$results = [];
$connectedSuccessfully = false;
$successHost = '';
$successDb = '';

foreach ($hostsToTry as $h) {
    foreach ($namesToTry as $n) {
        if (empty($dbUser) || empty($n)) continue;
        $tCon = @mysqli_connect($h, $dbUser, $dbPass, $n);
        if ($tCon) {
            $connectedSuccessfully = true;
            $successHost = $h;
            $successDb = $n;
            $results[] = [
                'host' => $h,
                'db' => $n,
                'status' => 'SUCCESS',
                'error' => null
            ];
            mysqli_close($tCon);
            break 2;
        } else {
            $results[] = [
                'host' => $h,
                'db' => $n,
                'status' => 'FAILED',
                'error' => '(' . mysqli_connect_errno() . ') ' . mysqli_connect_error()
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Database Diagnostic | Gym SaaS</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #0f172a; color: #f8fafc; padding: 30px; line-height: 1.6; }
        .container { max-width: 750px; margin: 0 auto; background: #1e293b; border-radius: 12px; padding: 25px; border: 1px solid #334155; }
        h1 { font-size: 1.4rem; margin-top: 0; color: #38bdf8; }
        .box { background: #0f172a; border: 1px solid #334155; border-radius: 8px; padding: 15px; margin-bottom: 20px; font-family: monospace; font-size: 0.9rem; }
        .badge-ok { background: #059669; color: white; padding: 3px 8px; border-radius: 4px; font-weight: bold; }
        .badge-err { background: #dc2626; color: white; padding: 3px 8px; border-radius: 4px; font-weight: bold; }
        .guide { background: #1e3a8a; border-left: 4px solid #3b82f6; padding: 15px; border-radius: 4px; margin-top: 20px; }
    </style>
</head>
<body>
<div class="container">
    <h1>Database Connection Diagnostic</h1>

    <div class="box">
        <strong>1. .env File Status:</strong><br>
        File Exists: <?php echo $envExists ? '<span class="badge-ok">YES</span>' : '<span class="badge-err">NO</span>'; ?><br>
        File Readable: <?php echo $envReadable ? '<span class="badge-ok">YES</span>' : '<span class="badge-err">NO</span>'; ?><br><br>
        <strong>Loaded Credentials from .env:</strong><br>
        DB_HOST = <code><?php echo htmlspecialchars($dbHost); ?></code><br>
        DB_USER = <code><?php echo htmlspecialchars($dbUser); ?></code><br>
        DB_PASS = <code><?php echo str_repeat('*', strlen($dbPass)); ?></code> (length: <?php echo strlen($dbPass); ?> chars)<br>
        DB_NAME = <code><?php echo htmlspecialchars($dbName); ?></code>
    </div>

    <div class="box">
        <strong>2. Connection Test Results:</strong><br><br>
        <?php foreach ($results as $r): ?>
            <div>
                Host: <code><?php echo htmlspecialchars($r['host']); ?></code> | DB: <code><?php echo htmlspecialchars($r['db']); ?></code><br>
                Status: <?php echo $r['status'] === 'SUCCESS' ? '<span class="badge-ok">CONNECTED OK</span>' : '<span class="badge-err">FAILED</span>'; ?><br>
                <?php if ($r['error']): ?>
                    <span style="color: #f87171;">Error: <?php echo htmlspecialchars($r['error']); ?></span><br>
                <?php endif; ?>
                <hr style="border-color: #334155; margin: 10px 0;">
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($connectedSuccessfully): ?>
        <div style="background: #064e3b; border-left: 4px solid #10b981; padding: 15px; border-radius: 4px;">
            <strong style="color: #34d399;">Connection Successful!</strong><br>
            Connected via Host: <code><?php echo htmlspecialchars($successHost); ?></code> and Database: <code><?php echo htmlspecialchars($successDb); ?></code>.
        </div>
    <?php else: ?>
        <div class="guide">
            <strong>How to fix this in cPanel / Hostinger:</strong><br>
            <ol style="margin-top: 8px; padding-left: 20px;">
                <?php
                $firstErr = $results[0]['error'] ?? '';
                if (str_contains($firstErr, '1045')): ?>
                    <li><strong>Error 1045 (Access Denied / Wrong Password):</strong>
                        <br>Go to <strong>cPanel &rarr; MySQL Databases</strong>.
                        <br>Scroll to <strong>Current Users</strong> &rarr; Click <strong>Change Password</strong> for <code><?php echo htmlspecialchars($dbUser); ?></code> &rarr; set password to match <code>DB_PASS</code> in your <code>.env</code> file.
                        <br>Scroll to <strong>Add User to Database</strong> &rarr; Select User <code><?php echo htmlspecialchars($dbUser); ?></code> and Database <code><?php echo htmlspecialchars($dbName); ?></code> &rarr; click <strong>Add</strong> &rarr; Check <strong>ALL PRIVILEGES</strong> &rarr; Click <strong>Make Changes</strong>.
                    </li>
                <?php elseif (str_contains($firstErr, '1049')): ?>
                    <li><strong>Error 1049 (Unknown Database):</strong>
                        <br>Go to <strong>cPanel &rarr; MySQL Databases</strong>.
                        <br>Check the exact full database name under <strong>Current Databases</strong>.
                        <br>Update <code>DB_NAME</code> in your <code>.env</code> file.
                    </li>
                <?php else: ?>
                    <li>Go to <strong>cPanel &rarr; MySQL Databases</strong>.</li>
                    <li>Verify the exact Database Name and Database User name.</li>
                    <li>Ensure the user is added to the database with <strong>ALL PRIVILEGES</strong>.</li>
                <?php endif; ?>
            </ol>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
