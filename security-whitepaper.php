<?php
require_once __DIR__ . '/core/helpers.php';
require_once __DIR__ . '/core/seo.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <?php SEO::renderHead('/security-whitepaper'); ?>
    
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
        .tech-pill {
            display: inline-block;
            padding: 4px 10px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 6px;
            font-family: monospace;
            font-size: 0.9rem;
            color: #a3e635;
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
                    <li><a href="terms-of-service.php">Terms of Service</a></li>
                    <li><a href="security-whitepaper.php" class="active">Security</a></li>
                </ul>

                <div class="navbar-actions">
                    <a href="index2.php" class="btn-primary-sm" style="background:#a3e635; color:#090d14; font-weight:800; padding:10px 20px; border-radius:12px; text-decoration:none;">Sign In</a>
                </div>
            </nav>
        </header>

        <!-- PAGE HEADER -->
        <section class="legal-page-header">
            <div class="legal-badge">
                <i class="fa-solid fa-shield-check"></i> Enterprise Architecture Document
            </div>
            <h1 class="legal-title">Security & Infrastructure Whitepaper</h1>
            <p class="legal-updated">FITISIFY OS Cloud Security Architecture • Nexora Lab Technologies</p>
        </section>

        <!-- LEGAL CONTENT -->
        <main class="legal-content">
            
            <div class="legal-section">
                <h2><i class="fa-solid fa-layer-group"></i> 1. Multi-Tenant Isolation Model</h2>
                <p>
                    FITISIFY OS employs strict multi-tenant data architecture. Every API call, database query, and authentication check is cryptographically bounded by a tenant context (<span class="tech-pill">tenant_id</span> / <span class="tech-pill">gym_code</span>).
                </p>
                <p>
                    This guarantees that Gym Facility A can never access, query, or view financial, staff, or member records belonging to Gym Facility B under any scenario.
                </p>
            </div>

            <div class="legal-section">
                <h2><i class="fa-solid fa-qrcode"></i> 2. Dynamic QR Digital Pass Cryptography</h2>
                <p>
                    Traditional static QR codes or barcodes are vulnerable to screenshot duplication and fraud. FITISIFY OS implements a time-bound dynamic cryptographic signature system:
                </p>
                <ul>
                    <li>Digital Pass QR codes contain a time-sensitive payload signed using <span class="tech-pill">HMAC-SHA256</span> encryption algorithms.</li>
                    <li>QR signatures dynamically rotate every few seconds with member verification checks.</li>
                    <li>The scanner endpoint on admin and staff terminals verifies the signature integrity in real-time, preventing fake or shared passes.</li>
                </ul>
            </div>

            <div class="legal-section">
                <h2><i class="fa-solid fa-lock-hashtag"></i> 3. Data Encryption Standards</h2>
                <p>Security protocols active across all FITISIFY OS cloud layers:</p>
                <ul>
                    <li><strong>In Transit:</strong> 100% of HTTP network traffic enforces <span class="tech-pill">256-Bit SSL/TLS 1.3</span> encryption between mobile applications, web portals, and server APIs.</li>
                    <li><strong>At Rest:</strong> Sensitive user passwords and authentication tokens are hashed using industry-standard <span class="tech-pill">BCrypt</span> algorithms.</li>
                    <li><strong>Bearer Tokens:</strong> Mobile auth uses secure token-based authorization stored in encrypted device keychains (<span class="tech-pill">FlutterSecureStorage</span>).</li>
                </ul>
            </div>

            <div class="legal-section">
                <h2><i class="fa-solid fa-server"></i> 4. Infrastructure & DDoS Protection</h2>
                <p>
                    FITISIFY OS servers operate in hardened cloud environments monitored 24/7 by Nexora Lab Technologies:
                </p>
                <ul>
                    <li>Automated daily encrypted database snapshots and off-site backup redundancy.</li>
                    <li>Rate limiting and parameter sanitization against SQL Injection, XSS, and Cross-Site Request Forgery (CSRF).</li>
                    <li>Automated health probes ensuring 99.9% service availability.</li>
                </ul>
                <div class="contact-box">
                    <strong style="color:#fff; font-size:1.1rem;"><i class="fa-solid fa-bug text-lime"></i> Security Disclosure & Audit Inquiries</strong><br />
                    SecOps Team: <span style="color:#fff;">Nexora Lab Technologies</span><br />
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
                    <a href="<?php echo base_url('/terms-of-service'); ?>" style="color:#94a3b8;">Terms of Service</a>
                    <a href="<?php echo base_url('/security-whitepaper'); ?>" style="color:#a3e635;">Security Whitepaper</a>
                </div>
            </div>
        </footer>

    </div>

</body>
</html>
