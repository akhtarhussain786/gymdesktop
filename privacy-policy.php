<?php
require_once __DIR__ . '/core/helpers.php';
require_once __DIR__ . '/core/seo.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <?php SEO::renderHead('/privacy-policy'); ?>
    
    <!-- Google Fonts: Outfit & Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- FontAwesome 6 Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    
    <!-- App Styling -->
    <link rel="stylesheet" href="<?php echo base_url('/assets/css/app.css'); ?>" />
    <style>
        .legal-page-header {
            padding: 140px 0 60px;
            text-align: center;
            background: radial-gradient(circle at 50% 20%, rgba(163, 230, 53, 0.08) 0%, transparent 60%);
            border-bottom: 1px solid var(--border-color, rgba(255, 255, 255, 0.08));
        }
        .legal-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 16px;
            background: rgba(163, 230, 53, 0.12);
            border: 1px solid rgba(163, 230, 53, 0.3);
            border-radius: 30px;
            color: #a3e635;
            font-size: 0.85rem;
            font-weight: 700;
            margin-bottom: 20px;
        }
        .legal-title {
            font-family: 'Outfit', sans-serif;
            font-size: 2.8rem;
            font-weight: 900;
            color: #fff;
            margin-bottom: 12px;
            letter-spacing: -0.5px;
        }
        .legal-updated {
            color: var(--text-muted, #94a3b8);
            font-size: 0.95rem;
        }
        .legal-content {
            max-width: 900px;
            margin: 60px auto;
            padding: 0 24px;
            color: #cbd5e1;
            font-size: 1.02rem;
            line-height: 1.8;
        }
        .legal-section {
            background: rgba(19, 26, 36, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 20px;
            padding: 36px;
            margin-bottom: 30px;
            backdrop-filter: blur(10px);
        }
        .legal-section h2 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.45rem;
            font-weight: 800;
            color: #fff;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .legal-section h2 i {
            color: #a3e635;
        }
        .legal-section p {
            margin-bottom: 16px;
        }
        .legal-section ul {
            margin-left: 20px;
            margin-bottom: 16px;
        }
        .legal-section li {
            margin-bottom: 10px;
        }
        .contact-box {
            background: rgba(163, 230, 53, 0.05);
            border: 1px solid rgba(163, 230, 53, 0.2);
            border-radius: 16px;
            padding: 24px;
            margin-top: 24px;
        }
    </style>
</head>
<body>

    <!-- Atmospheric Mesh Grid Background -->
    <div class="bg-atmosphere"></div>

    <div class="site-wrapper">

        <!-- FLOATING NAVBAR -->
        <header class="navbar-wrapper">
            <nav class="navbar-container">
                <a href="index.php" class="navbar-brand">
                    <div class="brand-icon">
                        <i class="fa-solid fa-bolt-lightning"></i>
                    </div>
                    <div class="brand-name">
                        FITISIFY <span class="tag">OS</span>
                    </div>
                </a>

                <ul class="navbar-menu">
                    <li><a href="index.php#features">Features</a></li>
                    <li><a href="index.php#pricing">Pricing</a></li>
                    <li><a href="privacy-policy.php" class="active">Privacy Policy</a></li>
                    <li><a href="terms-of-service.php">Terms of Service</a></li>
                    <li><a href="security-whitepaper.php">Security</a></li>
                </ul>

                <div class="navbar-actions">
                    <a href="index2.php" class="btn-primary-sm" style="background:#a3e635; color:#090d14; font-weight:800; padding:10px 20px; border-radius:12px; text-decoration:none;">Sign In</a>
                </div>
            </nav>
        </header>

        <!-- PAGE HEADER -->
        <section class="legal-page-header">
            <div class="legal-badge">
                <i class="fa-solid fa-shield-halved"></i> Official Privacy Document
            </div>
            <h1 class="legal-title">Privacy Policy</h1>
            <p class="legal-updated">Last Updated: August 24, 2026 • FITISIFY OS Cloud Services</p>
        </section>

        <!-- LEGAL CONTENT -->
        <main class="legal-content">
            
            <div class="legal-section">
                <h2><i class="fa-solid fa-circle-info"></i> 1. Introduction</h2>
                <p>
                    Welcome to <strong>FITISIFY OS</strong>, a next-generation gym management SaaS platform and athlete mobile application engineered and operated by <strong>Nexora Lab Technologies</strong> ("Company", "We", "Us", or "Our").
                </p>
                <p>
                    We respect your privacy and are committed to protecting the personal data of gym facility owners, staff administrators, trainers, and gym members ("Users"). This Privacy Policy explains how we collect, use, disclose, and safeguard your information when you access our cloud web software or use our FITISIFY OS mobile application on Android and iOS devices.
                </p>
            </div>

            <div class="legal-section">
                <h2><i class="fa-solid fa-database"></i> 2. Information We Collect</h2>
                <p>We collect information necessary to provide seamless multi-tenant gym management and digital attendance services:</p>
                <ul>
                    <li><strong>Account & Profile Information:</strong> Full name, phone number, email address, emergency contact details, profile picture, gender, and gym facility membership ID.</li>
                    <li><strong>Gym Membership & Attendance Data:</strong> Membership tier, plan duration, start and expiry dates, payment transaction receipts, check-in timestamps, and dynamic QR digital pass logs.</li>
                    <li><strong>Device & Technical Information:</strong> Operating system, app version, unique device tokens for FCM push notifications, IP address, and security logs.</li>
                    <li><strong>Payment Information:</strong> Transaction IDs and payment verification statuses processed securely via PCI-DSS compliant payment partners (e.g. Cashfree, UPI, Razorpay). We do <em>not</em> store raw credit card numbers or UPI PINs on our servers.</li>
                </ul>
            </div>

            <div class="legal-section">
                <h2><i class="fa-solid fa-gears"></i> 3. How We Use Your Information</h2>
                <p>Your information is used strictly to deliver and enhance FITISIFY OS functionality:</p>
                <ul>
                    <li>To verify member identity and grant digital pass QR entry into assigned gym facilities.</li>
                    <li>To notify members of upcoming membership renewals, fee due dates, and gym announcements.</li>
                    <li>To enable gym owners and trainers to manage workout routines, diet plans, and attendance registers.</li>
                    <li>To maintain strict multi-tenant data isolation, ensuring Gym A's member data is completely segregated from Gym B.</li>
                    <li>To prevent fraudulent QR pass duplication, unauthorized account access, and security breaches.</li>
                </ul>
            </div>

            <div class="legal-section">
                <h2><i class="fa-solid fa-lock"></i> 4. Data Security & Encryption</h2>
                <p>
                    Nexora Lab Technologies implements industry-standard security measures to protect your personal data:
                </p>
                <ul>
                    <li>All data transmitted between the FITISIFY OS mobile application and cloud servers is encrypted using <strong>256-Bit SSL/TLS HTTPS encryption</strong>.</li>
                    <li>Member QR Digital Passes utilize time-bound cryptographic HMAC-SHA256 signatures to prevent unauthorized screenshot sharing.</li>
                    <li>User passwords are hashed using secure cryptographic algorithms (BCrypt) prior to storage.</li>
                </ul>
            </div>

            <div class="legal-section">
                <h2><i class="fa-solid fa-user-shield"></i> 5. Data Sharing & Third Parties</h2>
                <p>
                    <strong>We do not sell, rent, or trade your personal data to advertisers or third-party marketers.</strong>
                </p>
                <p>
                    Data is shared only with trusted service providers necessary for app operations:
                </p>
                <ul>
                    <li><strong>Payment Processors:</strong> To process secure subscription and fee payments.</li>
                    <li><strong>Push Notification Gateways:</strong> To deliver real-time attendance and fee reminders (Firebase Cloud Messaging).</li>
                    <li><strong>Legal & Compliance:</strong> If required by law, subpoena, or government order.</li>
                </ul>
            </div>

            <div class="legal-section">
                <h2><i class="fa-solid fa-trash-can"></i> 6. Account Deletion & Data Rights</h2>
                <p>
                    Users have the right to request access to, correction of, or deletion of their personal information:
                </p>
                <ul>
                    <li><strong>Gym Members:</strong> You may request account deletion directly through your gym facility administrator or by contacting our privacy team.</li>
                    <li><strong>Gym Owners / SaaS Subscribers:</strong> You may request complete tenant data export or deletion upon account termination.</li>
                </ul>
                <p>To submit a privacy or data removal request, please contact us at:</p>
                <div class="contact-box">
                    <strong style="color:#fff; font-size:1.1rem;"><i class="fa-solid fa-envelope text-lime"></i> Privacy Office — Nexora Lab Technologies</strong><br />
                    Email: <a href="mailto:nexoralabtechnologies@gmail.com" style="color:#a3e635; font-weight:700;">nexoralabtechnologies@gmail.com</a><br />
                    Platform: <span style="color:#fff;">https://gymsaas.nexoralabtechnologies.com</span>
                </div>
            </div>

        </main>

        <!-- FOOTER -->
        <footer class="footer-master">
            <div class="container" style="max-width:900px; margin:0 auto; padding:30px 24px; text-align:center; color:var(--text-muted, #94a3b8); border-top:1px solid rgba(255,255,255,0.08);">
                <p>&copy; <?php echo date('Y'); ?> <strong>Nexora Lab Technologies</strong>. All rights reserved. FITISIFY OS Gym Cloud Platform.</p>
                <div style="display:flex; justify-content:center; gap:20px; margin-top:12px;">
                    <a href="<?php echo base_url('/privacy-policy'); ?>" style="color:#a3e635;">Privacy Policy</a>
                    <a href="<?php echo base_url('/terms-of-service'); ?>" style="color:#94a3b8;">Terms of Service</a>
                    <a href="<?php echo base_url('/security-whitepaper'); ?>" style="color:#94a3b8;">Security Whitepaper</a>
                </div>
            </div>
        </footer>

    </div>

</body>
</html>
