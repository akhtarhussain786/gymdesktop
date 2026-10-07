<?php
require_once __DIR__ . '/core/helpers.php';
require_once __DIR__ . '/core/seo.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <?php SEO::renderHead('/terms-of-service'); ?>
    
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
                    <li><a href="privacy-policy.php">Privacy Policy</a></li>
                    <li><a href="terms-of-service.php" class="active">Terms of Service</a></li>
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
                <i class="fa-solid fa-file-contract"></i> Master Service Agreement
            </div>
            <h1 class="legal-title">Terms of Service</h1>
            <p class="legal-updated">Last Updated: August 24, 2026 • FITISIFY OS Terms & Conditions</p>
        </section>

        <!-- LEGAL CONTENT -->
        <main class="legal-content">
            
            <div class="legal-section">
                <h2><i class="fa-solid fa-handshake"></i> 1. Acceptance of Terms</h2>
                <p>
                    By registering for, subscribing to, or using the <strong>FITISIFY OS</strong> SaaS platform, web management portals, or mobile application (collectively, the "Service"), provided by <strong>Nexora Lab Technologies</strong> ("Company", "We", "Our"), you ("User", "Gym Owner", "Facility Member") agree to be bound by these Terms of Service.
                </p>
                <p>
                    If you do not agree to these terms, you must immediately discontinue using our services and applications.
                </p>
            </div>

            <div class="legal-section">
                <h2><i class="fa-solid fa-building-user"></i> 2. SaaS Subscriptions & Gym Facility Management</h2>
                <p>FITISIFY OS operates as a multi-tenant cloud Software-as-a-Service platform:</p>
                <ul>
                    <li><strong>Account Creation:</strong> Gym facility owners must provide accurate and verifiable organization details upon signup.</li>
                    <li><strong>Tenant Isolation:</strong> Each gym operates under an isolated tenant identifier (`tenant_id` / `gym_code`). Facility administrators are responsible for managing staff access permissions.</li>
                    <li><strong>Subscription Billing:</strong> SaaS subscription fees are billed on a recurring monthly or annual basis. Failure to pay subscription renewals may result in temporary suspension of admin portal access after the grace period expires.</li>
                </ul>
            </div>

            <div class="legal-section">
                <h2><i class="fa-solid fa-mobile-screen-button"></i> 3. Member App & Digital Pass Usage</h2>
                <p>Rules governing the FITISIFY OS Athlete Mobile Application:</p>
                <ul>
                    <li><strong>Digital Pass Ownership:</strong> Dynamic QR Digital Passes generated within the mobile app are non-transferable and assigned exclusively to the registered member.</li>
                    <li><strong>Prohibited Misuse:</strong> Screenshot sharing, QR pass forgery, automated scraping, or attempting to bypass facility attendance hardware is strictly prohibited and constitutes grounds for immediate account termination.</li>
                    <li><strong>Gym Relationship:</strong> Gym membership plans, physical facility access, and refund policies are directly governed by your individual gym facility management. FITISIFY OS provides the technology operating infrastructure.</li>
                </ul>
            </div>

            <div class="legal-section">
                <h2><i class="fa-solid fa-shield-cat"></i> 4. Acceptable Use & Security</h2>
                <p>You agree not to engage in any of the following prohibited activities:</p>
                <ul>
                    <li>Reverse engineering, decompiling, or attempting to extract source code from FITISIFY OS web or mobile applications.</li>
                    <li>Introducing malware, viruses, SQL injection payloads, or automated bots to our servers.</li>
                    <li>Attempting unauthorized access to another gym tenant's database or member records.</li>
                </ul>
            </div>

            <div class="legal-section">
                <h2><i class="fa-solid fa-server"></i> 5. Service Level & Uptime</h2>
                <p>
                    Nexora Lab Technologies strives to maintain <strong>99.9% uptime</strong> for FITISIFY OS cloud services. Scheduled maintenance windows will be communicated to administrators in advance whenever feasible.
                </p>
            </div>

            <div class="legal-section">
                <h2><i class="fa-solid fa-scale-balanced"></i> 6. Limitation of Liability</h2>
                <p>
                    To the maximum extent permitted by applicable law, Nexora Lab Technologies shall not be liable for any indirect, incidental, special, or consequential damages resulting from the use or inability to use the Service, including loss of gym business revenue or data interruption.
                </p>
                <div class="contact-box">
                    <strong style="color:#fff; font-size:1.1rem;"><i class="fa-solid fa-headset text-lime"></i> Legal & Support Team</strong><br />
                    Company: <span style="color:#fff;">Nexora Lab Technologies</span><br />
                    Email: <a href="mailto:nexoralabtechnologies@gmail.com" style="color:#a3e635; font-weight:700;">nexoralabtechnologies@gmail.com</a>
                </div>
            </div>

        </main>

        <!-- FOOTER -->
        <footer class="footer-master">
            <div class="container" style="max-width:900px; margin:0 auto; padding:30px 24px; text-align:center; color:var(--text-muted, #94a3b8); border-top:1px solid rgba(255,255,255,0.08);">
                <p>&copy; <?php echo date('Y'); ?> <strong>Nexora Lab Technologies</strong>. All rights reserved. FITISIFY OS Gym Cloud Platform.</p>
                <div style="display:flex; justify-content:center; gap:20px; margin-top:12px;">
                    <a href="<?php echo base_url('/privacy-policy'); ?>" style="color:#94a3b8;">Privacy Policy</a>
                    <a href="<?php echo base_url('/terms-of-service'); ?>" style="color:#a3e635;">Terms of Service</a>
                    <a href="<?php echo base_url('/security-whitepaper'); ?>" style="color:#94a3b8;">Security Whitepaper</a>
                </div>
            </div>
        </footer>

    </div>

</body>
</html>
