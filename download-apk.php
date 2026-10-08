<?php
/**
 * Direct APK Streaming & Download Handler for Fitisify Android App
 */
require_once __DIR__ . '/core/helpers.php';
require_once __DIR__ . '/core/db.php';

// Check if external URL configured by SuperAdmin
$externalUrl = get_platform_setting('app_apk_external_url', '');
if (!empty($externalUrl)) {
    header("Location: " . $externalUrl, true, 302);
    exit();
}

$apkPath = __DIR__ . '/uploads/apk/fitisify_member_app.apk';
if (!file_exists($apkPath)) {
    // Try checking Flutter build folder as fallback
    $altPath = __DIR__ . '/gym_member_app/build/app/outputs/flutter-apk/app-release.apk';
    if (file_exists($altPath)) {
        $apkPath = $altPath;
    }
}

if (!file_exists($apkPath)) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><title>APK Being Updated - Fitisify</title><meta name="viewport" content="width=device-width, initial-scale=1.0"><style>body{background:#05080d;color:#fff;font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;padding:20px;box-sizing:border-box;text-align:center;} .box{background:#111720;border:1px solid rgba(199,255,46,0.2);padding:40px;border-radius:18px;max-width:480px;} .btn{display:inline-block;background:#c7ff2e;color:#05080d;padding:12px 24px;border-radius:999px;text-decoration:none;font-weight:bold;margin-top:20px;}</style></head><body><div class="box"><h2 style="color:#c7ff2e;">APK Package Syncing</h2><p style="color:#94a3b8;">The latest Fitisify Android APK package is currently synchronizing. Please try again in a few moments.</p><a href="' . base_url('/') . '" class="btn">Return to Homepage</a></div></body></html>';
    exit();
}

$version = get_platform_setting('app_version', 'v1.0.4');
$cleanVersion = preg_replace('/[^a-zA-Z0-9._-]/', '', $version);
$filename = 'Fitisify_Member_App_' . $cleanVersion . '.apk';
$filesize = filesize($apkPath);

// Clear output buffers
while (ob_get_level()) {
    ob_end_clean();
}

// Send binary headers
header('Content-Description: File Transfer');
header('Content-Type: application/vnd.android.package-archive');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Content-Length: ' . $filesize);

// Stream file
$handle = fopen($apkPath, 'rb');
if ($handle !== false) {
    while (!feof($handle)) {
        echo fread($handle, 65536);
        flush();
    }
    fclose($handle);
} else {
    readfile($apkPath);
}
exit();
