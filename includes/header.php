<?php
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/tenant.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helpers.php';

// Fetch current gym tenant branding
$currentTenant = Tenant::getCurrent();
$currentUser = Auth::user();
$pageTitle = $pageTitle ?? 'Dashboard';
$primaryColor = $currentTenant['primary_color'] ?? '#3b82f6';
$secondaryColor = $currentTenant['secondary_color'] ?? '#10b981';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?php echo e($pageTitle); ?> | <?php echo e($currentTenant['gym_name'] ?? 'Fitisify SaaS'); ?></title>
    
    <!-- Google Fonts: Outfit & Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Modern Font Awesome 6 Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    
    <!-- Chart.js for beautiful modern analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Custom Modern UI Design System -->
    <link rel="stylesheet" href="<?php echo base_url('/assets/css/app.css'); ?>" />

    <!-- Tenant Dynamic Branding Styles -->
    <style>
        :root {
            <?php if (!empty($currentTenant['primary_color'])): ?>
            --primary: <?php echo preg_match('/^#[0-9A-Fa-f]{3,8}$/', (string)$currentTenant['primary_color']) ? $currentTenant['primary_color'] : '#3b82f6'; ?>;
            <?php endif; ?>
            <?php if (!empty($currentTenant['secondary_color'])): ?>
            --secondary: <?php echo preg_match('/^#[0-9A-Fa-f]{3,8}$/', (string)$currentTenant['secondary_color']) ? $currentTenant['secondary_color'] : '#10b981'; ?>;
            <?php endif; ?>
        }
    </style>
</head>
<body>
<div class="app-wrapper">
