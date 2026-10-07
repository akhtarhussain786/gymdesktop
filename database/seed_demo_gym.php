<?php
/**
 * Standalone Demo Gym Seeder & Database Sanitation Script
 * 
 * Usage from CLI: php database/seed_demo_gym.php [--reset] [--purge-only]
 */

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/demo_seeder.php';

$isCli = (PHP_SAPI === 'cli');

if (!$isCli) {
    require_once __DIR__ . '/../core/auth.php';
    Auth::requireAuth('super_admin');
}

$purgeOnly = isset($_GET['purge_only']) || (isset($argv) && in_array('--purge-only', $argv));
$forceReset = isset($_GET['reset']) || (isset($argv) && in_array('--reset', $argv));

echo $isCli ? "Starting Database Sanitation...\n" : "<h3>Database Sanitation & Demo Seeder</h3><pre>";

// 1. Purge Orphans
$purgeResult = DemoSeeder::purgeOrphans();
if ($purgeResult['success']) {
    echo "Purged {$purgeResult['total_deleted']} orphaned records across database tables.\n";
    foreach ($purgeResult['breakdown'] as $tbl => $cnt) {
        echo "  - {$tbl}: {$cnt} deleted\n";
    }
} else {
    echo "Error during orphan purge: " . ($purgeResult['error'] ?? 'Unknown error') . "\n";
}

// 2. Seed Demo Gym if not purge-only
if (!$purgeOnly) {
    echo "\nSeeding / Resetting Demo Gym...\n";
    $seedResult = DemoSeeder::seedDemoGym($forceReset);
    if ($seedResult['success']) {
        echo "SUCCESS: Demo Gym ready!\n";
        echo "Gym Name: " . ($seedResult['gym_name'] ?? 'DEMO FITNESS & GYM') . "\n";
        echo "Gym Code: " . ($seedResult['credentials']['gym_code'] ?? 'DEMO-GYM') . "\n";
        echo "Admin Username: " . ($seedResult['credentials']['admin_username'] ?? 'demogym') . "\n";
        echo "Admin Password: " . ($seedResult['credentials']['admin_password'] ?? 'Password@123') . "\n";
    } else {
        echo "Error during demo seeding: " . ($seedResult['error'] ?? 'Unknown error') . "\n";
    }
}

echo $isCli ? "\nDone.\n" : "</pre><p><a href='" . base_url('/superadmin/index.php') . "'>&larr; Return to Super Admin Dashboard</a></p>";
