<?php
require_once __DIR__ . '/core/db.php';
require_once __DIR__ . '/core/currencies.php';
require_once __DIR__ . '/core/helpers.php';
require_once __DIR__ . '/core/seo.php';
require_once __DIR__ . '/core/visitor_tracker.php';

// Log real website visit
VisitorTracker::logVisit('/');

// Fetch active subscription plans from DB with resilient fallback
try {
    $plans = DB::fetchAll("SELECT * FROM subscription_plans WHERE is_active = 1 ORDER BY price_monthly ASC");
} catch (Exception $e) {
    $plans = [];
}

if (empty($plans)) {
    $plans = [
        [
            'id' => 1,
            'name' => 'Starter Base',
            'price_monthly' => 999,
            'price_yearly' => 9590,
            'max_members' => 150,
            'max_staff' => 3,
            'max_branches' => 1,
            'grace_period_days' => 3
        ],
        [
            'id' => 2,
            'name' => 'Pro Powerhouse',
            'price_monthly' => 2499,
            'price_yearly' => 23990,
            'max_members' => 600,
            'max_staff' => 10,
            'max_branches' => 2,
            'grace_period_days' => 7
        ],
        [
            'id' => 3,
            'name' => 'Enterprise Chain',
            'price_monthly' => 5999,
            'price_yearly' => 57590,
            'max_members' => 2500,
            'max_staff' => 35,
            'max_branches' => 5,
            'grace_period_days' => 15
        ]
    ];
}

// Fetch active testimonials from DB with resilient fallback
try {
    $testimonials = DB::fetchAll("SELECT * FROM testimonials WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
} catch (Exception $e) {
    $testimonials = [];
}

if (empty($testimonials)) {
    $testimonials = [
        [
            'id' => 1,
            'author_name' => 'Vikram Singhania',
            'designation' => 'Founder, Titan Athletics (3 Branches)',
            'quote' => 'Managing attendance, renewals and Cashfree UPI payments from one dark dashboard has completely transformed how our front desk operates. Our renewal rate jumped by 24% in the first 60 days.',
            'avatar' => 'customer/img/demo/av1.jpg',
            'rating' => 5
        ],
        [
            'id' => 2,
            'author_name' => 'Ananya Roy',
            'designation' => 'Managing Director, Crossfit Matrix',
            'quote' => 'The QR check-in and athlete mobile app make our gym look like an Apple product. Members constantly compliment how seamless it is to check their workout splits and fee receipts.',
            'avatar' => 'customer/img/demo/av2.jpg',
            'rating' => 5
        ],
        [
            'id' => 3,
            'author_name' => 'Rohan Malhotra',
            'designation' => 'Head Coach, Iron Vault Studios',
            'quote' => 'The trainer command center and automated WhatsApp expiry notifications alone have eliminated all awkward payment follow-up conversations. It pays for itself ten times over.',
            'avatar' => 'customer/img/demo/av3.jpg',
            'rating' => 5
        ]
    ];
}

$currencies = get_supported_currencies();

// Dynamic Mobile App Release Settings
$appPlayStoreUrl = get_platform_setting('app_playstore_url', 'https://play.google.com/store/apps/details?id=com.fitisify.gym_member_app');
$appApkExternalUrl = get_platform_setting('app_apk_external_url', '');
$appVersion = get_platform_setting('app_version', 'v1.0.4');
$appMinAndroid = get_platform_setting('app_min_android', 'Android 8.0+');
$appApkLocalPath = __DIR__ . '/uploads/apk/fitisify_member_app.apk';
$appApkExists = file_exists($appApkLocalPath);
$appApkSizeFormatted = $appApkExists ? round(filesize($appApkLocalPath) / (1024 * 1024), 1) . ' MB' : '63.6 MB';
$appApkDownloadUrl = !empty($appApkExternalUrl) ? $appApkExternalUrl : base_url('/uploads/apk/fitisify_member_app.apk');
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <?php SEO::renderHead('/'); ?>
    
    <!-- Google Fonts: Outfit & Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- FontAwesome 6 Pro Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    
    <!-- Master Dark-Tech SaaS Design & Motion System -->
    <link rel="stylesheet" href="<?php echo base_url('/assets/css/app.css?v=' . (file_exists(__DIR__ . '/assets/css/app.css') ? filemtime(__DIR__ . '/assets/css/app.css') : '1.0.5')); ?>" />
</head>
<body>

    <!-- Atmospheric Mesh Grid Background -->
    <div class="bg-atmosphere"></div>

    <div class="site-wrapper">

        <!-- =================================================================
             1. FLOATING CAPSULE NAVBAR (LOAD SEQ 1)
             ================================================================= -->
        <header class="navbar-wrapper load-seq-1">
            <nav class="navbar-container">
                <a href="<?php echo base_url('/'); ?>" class="navbar-brand">
                    <div class="brand-icon">
                        <i class="fa-solid fa-bolt-lightning"></i>
                    </div>
                    <div class="brand-name">
                        FITISIFY <span class="tag">OS</span>
                    </div>
                </a>

                <ul class="navbar-menu">
                    <li><a href="#features" class="active">Features</a></li>
                    <li><a href="#solutions">Solutions</a></li>
                    <li><a href="#dashboard">Dashboard</a></li>
                    <li><a href="#athlete">Apps</a></li>
                    <li><a href="#attendance">Attendance</a></li>
                    <li><a href="#pricing">Pricing</a></li>
                    <li><a href="#faq">FAQ</a></li>
                </ul>

                <div class="navbar-actions">
                    <!-- Dynamic Geo-Currency Selector -->
                    <div class="currency-picker">
                        <i class="fa-solid fa-globe text-lime"></i>
                        <select id="global-currency-select" aria-label="Select Billing Currency">
                            <?php foreach ($currencies as $c): ?>
                                <option value="<?php echo e($c['code']); ?>" <?php echo $c['code'] === 'INR' ? 'selected' : ''; ?>>
                                    <?php echo e($c['symbol']); ?> <?php echo e($c['code']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <a href="<?php echo base_url('/index2'); ?>" class="btn btn-ghost-dark btn-sm">
                        <i class="fa-solid fa-arrow-right-to-bracket"></i> Login
                    </a>
                    
                    <a href="<?php echo base_url('/register-gym'); ?>" class="btn btn-lime btn-sm">
                        Get Started <i class="fa-solid fa-arrow-right"></i>
                    </a>

                    <button type="button" class="mobile-menu-toggle" onclick="toggleMobileNav()" aria-label="Open Navigation">
                        <i class="fa-solid fa-bars"></i>
                    </button>
                </div>

                <!-- Sleek Glowing Scroll Progress Bar -->
                <div class="navbar-progress-track">
                    <div class="navbar-progress-fill" id="navbar-progress-fill"></div>
                </div>
            </nav>
        </header>

        <!-- Mobile Navigation Backdrop Overlay -->
        <div class="mobile-menu-backdrop" id="mobile-menu-backdrop" onclick="toggleMobileNav()"></div>

        <!-- Mobile Navigation Off-Canvas Drawer -->
        <div class="mobile-nav-drawer" id="mobile-nav-drawer">
            <div class="mobile-drawer-header">
                <a href="<?php echo base_url('/'); ?>" class="navbar-brand">
                    <div class="brand-icon"><i class="fa-solid fa-bolt-lightning"></i></div>
                    <div class="brand-name">FITISIFY <span class="tag">OS</span></div>
                </a>
                <button type="button" class="mobile-drawer-close" onclick="toggleMobileNav()" aria-label="Close Menu">
                    &times;
                </button>
            </div>

            <div class="mobile-drawer-body">
                <a href="#features" onclick="toggleMobileNav()"><i class="fa-solid fa-layer-group"></i> Features</a>
                <a href="#solutions" onclick="toggleMobileNav()"><i class="fa-solid fa-sliders"></i> Solutions</a>
                <a href="#dashboard" onclick="toggleMobileNav()"><i class="fa-solid fa-gauge-high"></i> Product Showcase</a>
                <a href="#athlete" onclick="toggleMobileNav()"><i class="fa-solid fa-mobile-screen-button"></i> Athlete Experience</a>
                <a href="#attendance" onclick="toggleMobileNav()"><i class="fa-solid fa-qrcode"></i> Smart Check-in</a>
                <a href="#pricing" onclick="toggleMobileNav()"><i class="fa-solid fa-tags"></i> Pricing Plans</a>
                <a href="#faq" onclick="toggleMobileNav()"><i class="fa-solid fa-circle-question"></i> FAQ</a>

                <!-- Currency Picker Inside Mobile Drawer -->
                <div class="mobile-currency-wrapper">
                    <label for="mobile-currency-select"><i class="fa-solid fa-globe text-lime"></i> Currency:</label>
                    <select id="mobile-currency-select" aria-label="Select Billing Currency" onchange="document.getElementById('global-currency-select').value=this.value; document.getElementById('global-currency-select').dispatchEvent(new Event('change'));">
                        <?php foreach ($currencies as $c): ?>
                            <option value="<?php echo e($c['code']); ?>" <?php echo $c['code'] === 'INR' ? 'selected' : ''; ?>>
                                <?php echo e($c['symbol']); ?> <?php echo e($c['code']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mobile-drawer-actions">
                    <a href="index2.php" class="btn btn-ghost-dark btn-sm" style="flex:1;">
                        <i class="fa-solid fa-arrow-right-to-bracket"></i> Login
                    </a>
                    <a href="register-gym.php" class="btn btn-lime btn-sm" style="flex:1;">
                        Get Started <i class="fa-solid fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- =================================================================
             2. HERO SECTION (STAGGERED MASKED REVEALS & 3D PHYSICS)
             ================================================================= -->
        <section class="hero-section" id="hero">
            <!-- Atmospheric Dark-Tech Hero Background Video -->
            <div class="hero-video-bg-container">
                <!-- The mixkit sources returned 403 and the poster image was never committed; self-host this video for reliability -->
                <video class="hero-video-bg" autoplay loop muted playsinline preload="metadata">
                    <source src="https://videos.pexels.com/video-files/28436821/12382107_1920_1080_25fps.mp4" type="video/mp4">
                </video>
                <div class="hero-video-overlay"></div>
                <div class="hero-video-grid-mesh"></div>
            </div>

            <div class="container" style="position:relative; z-index:2;">
                <div class="hero-grid">
                    
                    <!-- Left: Masked Typography & CTA Sequence -->
                    <div class="hero-content">
                        <div class="badge-chip hero-eyebrow load-seq-2">
                            <span class="pulse-dot"></span> NEXT-GEN GYM OPERATING SYSTEM
                        </div>

                        <h1 class="hero-title">
                            <span class="line-reveal-wrap">
                                <span class="line-reveal line-reveal-delay-1">COMMAND YOUR</span>
                            </span>
                            <span class="line-reveal-wrap">
                                <span class="line-reveal line-reveal-delay-2 text-gradient-lime-animated">FITNESS EMPIRE</span>
                            </span>
                            <span class="line-reveal-wrap">
                                <span class="line-reveal line-reveal-delay-3">WITH PRECISION.</span>
                            </span>
                        </h1>

                        <p class="hero-desc load-seq-3">
                            The all-in-one dark-tech cloud platform built for modern gyms, studios, and fitness franchises. Unify member 360 profiles, sub-second QR attendance, dynamic memberships, trainer commissions, Cashfree payments, and real-time revenue analytics.
                        </p>

                        <div class="hero-cta-group load-seq-4">
                            <a href="<?php echo base_url('/register-gym'); ?>" class="btn btn-lime btn-lg">
                                Start 14-Day Free Trial <i class="fa-solid fa-arrow-right"></i>
                            </a>
                            <button type="button" onclick="openLeadModal('Hero Quick Demo Button')" class="btn btn-ghost-dark btn-lg">
                                <i class="fa-solid fa-bolt text-lime"></i> Book Live Demo / Callback
                            </button>
                        </div>

                        <div class="hero-guarantee load-seq-5">
                            <span><i class="fa-solid fa-shield-check text-lime"></i> Instant Cloud Provisioning</span>
                            <span><i class="fa-solid fa-credit-card text-lime"></i> No Credit Card Required</span>
                            <span><i class="fa-solid fa-bolt text-lime"></i> 2-Minute Onboarding</span>
                        </div>

                        <!-- Trust & Social Proof Strip -->
                        <div class="hero-trust-strip load-seq-5">
                            <div class="avatar-stack">
                                <img src="customer/img/demo/av1.jpg" alt="Gym Owner" />
                                <img src="customer/img/demo/av2.jpg" alt="Fitness Director" />
                                <img src="customer/img/demo/av3.jpg" alt="Trainer" />
                                <img src="customer/img/demo/av4.jpg" alt="Franchise Head" />
                            </div>
                            <div class="trust-stats">
                                <div class="stars">
                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star"></i>
                                    <span class="score">4.9/5</span>
                                </div>
                                <div class="label">
                                    Trusted by <strong>1,200+ Gyms</strong> & <strong>180K+ Athletes</strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Futuristic Dashboard Device Mockup & Mouse Parallax -->
                    <div class="hero-visual-wrapper">
                        
                        <!-- Floating Glass Metric 1 (Stagger Pop-in) -->
                        <div class="floating-widget floating-widget-1 load-seq-badge1">
                            <div class="widget-icon-box widget-icon-lime">
                                <i class="fa-solid fa-qrcode"></i>
                            </div>
                            <div>
                                <div class="widget-content-title">
                                    <span data-counter="620">620</span> <span style="font-size:0.75rem; color:var(--lime);">↑ 14.2%</span>
                                </div>
                                <div class="widget-content-sub">Active Check-ins Today</div>
                            </div>
                        </div>

                        <!-- Main Dashboard Frame -->
                        <div class="dashboard-device-frame load-seq-dash">
                            <div class="dashboard-header-bar">
                                <div class="window-dots">
                                    <span class="dot-red"></span>
                                    <span class="dot-yellow"></span>
                                    <span class="dot-green"></span>
                                </div>
                                <div class="dash-top-title">
                                    <i class="fa-solid fa-server text-lime"></i> FITISIFY COMMAND CENTER • MUMBAI HQ
                                </div>
                                <div style="font-size:0.75rem; color:var(--lime); font-weight:700;">
                                    <span class="pulse-dot" style="display:inline-block; margin-right:4px;"></span> LIVE
                                </div>
                            </div>

                            <div class="dashboard-inner-ui">
                                <!-- KPI Cards Row -->
                                <div class="dash-kpi-row">
                                    <div class="dash-kpi-card">
                                        <div class="dash-kpi-label">Active Members</div>
                                        <div class="dash-kpi-val">
                                            <span data-counter="1842">1,842</span> <span class="dash-kpi-badge">● LIVE</span>
                                        </div>
                                    </div>
                                    <div class="dash-kpi-card">
                                        <div class="dash-kpi-label">Monthly Revenue</div>
                                        <div class="dash-kpi-val">
                                            <span data-counter="4.82" data-counter-prefix="₹" data-counter-suffix="L" data-counter-decimal="true">₹4.82L</span> <span class="dash-kpi-badge">↑ 18%</span>
                                        </div>
                                    </div>
                                    <div class="dash-kpi-card">
                                        <div class="dash-kpi-label">Expiring (7d)</div>
                                        <div class="dash-kpi-val" style="color:#ff5a36;">
                                            <span data-counter="12">12</span> <span style="font-size:0.7rem; color:#ff5a36;">RISK</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Visual Chart & Live Feed -->
                                <div class="dash-charts-row">
                                    <div class="dash-chart-card">
                                        <div class="dash-chart-header">
                                            <span>HOURLY OCCUPANCY TREND</span>
                                            <span class="text-lime">PEAK: 19:00</span>
                                        </div>
                                        <div class="dash-mini-bars">
                                            <div class="mini-bar-col" style="--target-h: 30%;"></div>
                                            <div class="mini-bar-col" style="--target-h: 45%;"></div>
                                            <div class="mini-bar-col" style="--target-h: 60%;"></div>
                                            <div class="mini-bar-col active" style="--target-h: 95%;"></div>
                                            <div class="mini-bar-col" style="--target-h: 75%;"></div>
                                            <div class="mini-bar-col" style="--target-h: 40%;"></div>
                                            <div class="mini-bar-col" style="--target-h: 85%;"></div>
                                            <div class="mini-bar-col" style="--target-h: 50%;"></div>
                                        </div>
                                    </div>

                                    <div class="dash-chart-card">
                                        <div class="dash-chart-header">
                                            <span>RECENT CHECK-INS</span>
                                            <span class="text-cyan">AUTO-SYNC</span>
                                        </div>
                                        <div class="dash-activity-list">
                                            <div class="dash-activity-item">
                                                <div class="dash-activity-user">
                                                    <span class="user-dot"></span> Rahul S. (VIP)
                                                </div>
                                                <span style="color:var(--text-muted);">08:42 AM</span>
                                            </div>
                                            <div class="dash-activity-item">
                                                <div class="dash-activity-user">
                                                    <span class="user-dot"></span> Priya K. (Gold)
                                                </div>
                                                <span style="color:var(--text-muted);">08:39 AM</span>
                                            </div>
                                            <div class="dash-activity-item">
                                                <div class="dash-activity-user">
                                                    <span class="user-dot"></span> Vikram M. (PT)
                                                </div>
                                                <span style="color:var(--text-muted);">08:35 AM</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Floating Glass Metric 2 (Stagger Pop-in) -->
                        <div class="floating-widget floating-widget-2 load-seq-badge2">
                            <div class="widget-icon-box widget-icon-cyan">
                                <i class="fa-solid fa-wallet"></i>
                            </div>
                            <div>
                                <div class="widget-content-title">
                                    <span data-counter="1.84" data-counter-prefix="₹" data-counter-suffix="L" data-counter-decimal="true">₹1.84L</span> <span style="font-size:0.75rem; color:var(--cyan);">↑ 12.8%</span>
                                </div>
                                <div class="widget-content-sub">Collected via Cashfree UPI</div>
                            </div>
                        </div>

                        <!-- Floating Glass Metric 3 (Stagger Pop-in) -->
                        <div class="floating-widget floating-widget-3 load-seq-badge3">
                            <div class="widget-icon-box widget-icon-emerald">
                                <i class="fa-solid fa-users"></i>
                            </div>
                            <div>
                                <div class="widget-content-title" data-counter="1842">1,842</div>
                                <div class="widget-content-sub">Active Athletes Managed</div>
                            </div>
                        </div>

                    </div>

                </div>
            </div>
        </section>

        <!-- =================================================================
             3. INFINITE LOGO / TRUST MARQUEE (SMOOTH HORIZONTAL MOTION)
             ================================================================= -->
        <section class="trust-bar-section reveal-on-scroll">
            <div class="container">
                <div class="trust-bar-inner">
                    <div class="trust-bar-label">
                        POWERING ELITE FITNESS CHAINS ACROSS INDIA & GLOBALLY
                    </div>
                    
                    <div class="marquee-container" style="flex:1;">
                        <div class="marquee-track">
                            <div class="trust-logo-item"><i class="fa-solid fa-dumbbell text-lime"></i> TITAN FITNESS</div>
                            <div class="trust-logo-item"><i class="fa-solid fa-fire text-cyan"></i> CROSSFIT MATRIX</div>
                            <div class="trust-logo-item"><i class="fa-solid fa-shield-halved text-gold"></i> IRON VAULT GYM</div>
                            <div class="trust-logo-item"><i class="fa-solid fa-bolt text-lime"></i> VELOCITY ATHLETICS</div>
                            <div class="trust-logo-item"><i class="fa-solid fa-trophy text-blue"></i> APEX POWERHOUSE</div>
                            
                            <!-- Duplicate for seamless infinite loop -->
                            <div class="trust-logo-item"><i class="fa-solid fa-dumbbell text-lime"></i> TITAN FITNESS</div>
                            <div class="trust-logo-item"><i class="fa-solid fa-fire text-cyan"></i> CROSSFIT MATRIX</div>
                            <div class="trust-logo-item"><i class="fa-solid fa-shield-halved text-gold"></i> IRON VAULT GYM</div>
                            <div class="trust-logo-item"><i class="fa-solid fa-bolt text-lime"></i> VELOCITY ATHLETICS</div>
                            <div class="trust-logo-item"><i class="fa-solid fa-trophy text-blue"></i> APEX POWERHOUSE</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- =================================================================
             4. FEATURE / ECOSYSTEM SECTION (STAGGERED SCROLL REVEAL)
             ================================================================= -->
        <section class="section-spacing" id="features">
            <div class="container">
                <div class="section-header reveal-on-scroll">
                    <div class="badge-chip">CORE ECOSYSTEM</div>
                    <h2 class="section-title">EVERYTHING YOUR GYM NEEDS TO GROW.</h2>
                    <p class="section-subtitle">
                        One unified, intelligent platform engineered for members, trainers, club managers, and financial stakeholders.
                    </p>
                </div>

                <div class="features-grid">
                    
                    <!-- 01 MEMBER MANAGEMENT -->
                    <div class="feature-card reveal-on-scroll reveal-delay-100">
                        <div>
                            <div class="card-top-meta">
                                <span class="card-number">01</span>
                                <span class="card-category-badge">MEMBER 360°</span>
                            </div>
                            <div class="card-icon-box">
                                <i class="fa-solid fa-id-card-clip"></i>
                            </div>
                            <h3 class="card-title">Smart Member Management</h3>
                            <p class="card-desc">
                                Centralize registration profiles, biometric digital waivers, emergency contacts, medical records, and membership freeze/transfer workflows with zero friction.
                            </p>
                        </div>
                        <a href="register-gym.php" class="card-footer-action">
                            <span>Explore Members</span>
                            <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    </div>

                    <!-- 02 SMART ATTENDANCE -->
                    <div class="feature-card reveal-on-scroll reveal-delay-200">
                        <div>
                            <div class="card-top-meta">
                                <span class="card-number">02</span>
                                <span class="card-category-badge">BIOMETRIC SCAN</span>
                            </div>
                            <div class="card-icon-box">
                                <i class="fa-solid fa-qrcode"></i>
                            </div>
                            <h3 class="card-title">Instant Check-In & Access</h3>
                            <p class="card-desc">
                                Sub-second QR code, barcode, and facial biometric scanning. Includes real-time floor occupancy counting and duplicate scan fraud prevention.
                            </p>
                        </div>
                        <a href="#attendance" class="card-footer-action">
                            <span>Explore Attendance</span>
                            <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    </div>

                    <!-- 03 MEMBERSHIP ENGINE -->
                    <div class="feature-card reveal-on-scroll reveal-delay-300">
                        <div>
                            <div class="card-top-meta">
                                <span class="card-number">03</span>
                                <span class="card-category-badge">BILLING ENGINE</span>
                            </div>
                            <div class="card-icon-box">
                                <i class="fa-solid fa-calendar-check"></i>
                            </div>
                            <h3 class="card-title">Flexible Membership Engine</h3>
                            <p class="card-desc">
                                Create tiered plans, auto-renewals, upfront packages, discounts, and automated expiry alerts via WhatsApp and SMS before access lapses.
                            </p>
                        </div>
                        <a href="#pricing" class="card-footer-action">
                            <span>Explore Memberships</span>
                            <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    </div>

                    <!-- 04 TRAINER MANAGEMENT -->
                    <div class="feature-card reveal-on-scroll reveal-delay-100">
                        <div>
                            <div class="card-top-meta">
                                <span class="card-number">04</span>
                                <span class="card-category-badge">COACH COMMAND</span>
                            </div>
                            <div class="card-icon-box">
                                <i class="fa-solid fa-user-ninja"></i>
                            </div>
                            <h3 class="card-title">Trainer Command Center</h3>
                            <p class="card-desc">
                                Allocate clients, coordinate PT slots, track trainer attendance, measure client retention, and calculate commission splits automatically.
                            </p>
                        </div>
                        <a href="index2.php" class="card-footer-action">
                            <span>Explore Trainers</span>
                            <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    </div>

                    <!-- 05 WORKOUT & DIET -->
                    <div class="feature-card reveal-on-scroll reveal-delay-200">
                        <div>
                            <div class="card-top-meta">
                                <span class="card-number">05</span>
                                <span class="card-category-badge">FITNESS LAB</span>
                            </div>
                            <div class="card-icon-box">
                                <i class="fa-solid fa-apple-whole"></i>
                            </div>
                            <h3 class="card-title">Workout & Nutrition Builder</h3>
                            <p class="card-desc">
                                Design multi-week hypertrophy/fat-loss workout splits and macro-caloric diet regimens attached directly to member mobile app dashboards.
                            </p>
                        </div>
                        <a href="#athlete" class="card-footer-action">
                            <span>Explore Fitness</span>
                            <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    </div>

                    <!-- 06 FINANCIAL REVENUE CONTROL -->
                    <div class="feature-card reveal-on-scroll reveal-delay-300">
                        <div>
                            <div class="card-top-meta">
                                <span class="card-number">06</span>
                                <span class="card-category-badge">FINANCE & P&L</span>
                            </div>
                            <div class="card-icon-box">
                                <i class="fa-solid fa-chart-line-up"></i>
                            </div>
                            <h3 class="card-title">Gym Revenue Control</h3>
                            <p class="card-desc">
                                Native Cashfree payment gateway, instant GST tax receipts, recurring dues tracking, expense logging, and real-time Profit & Loss balance sheets.
                            </p>
                        </div>
                        <a href="register-gym.php" class="card-footer-action">
                            <span>Explore Finance</span>
                            <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    </div>

                </div>
            </div>
        </section>

        <!-- =================================================================
             5. PROGRAM / SOLUTION SHOWCASE (INTERACTIVE TABS)
             ================================================================= -->
        <section class="section-spacing" id="solutions" style="background: rgba(13, 18, 25, 0.4);">
            <div class="container">
                <div class="section-header reveal-on-scroll">
                    <div class="badge-chip badge-chip-cyan">TAILORED ARCHITECTURE</div>
                    <h2 class="section-title">BUILT FOR EVERY GYM OPERATION.</h2>
                    <p class="section-subtitle">
                        Switch seamlessly between specialized operational layers built to eliminate spreadsheet chaos and elevate member retention.
                    </p>
                </div>

                <!-- Category Filter Pills -->
                <div class="tabs-pill-nav reveal-on-scroll">
                    <button type="button" class="tab-pill-btn active" onclick="filterSolutions('all', this)">All Solutions</button>
                    <button type="button" class="tab-pill-btn" onclick="filterSolutions('operations', this)">Operations</button>
                    <button type="button" class="tab-pill-btn" onclick="filterSolutions('members', this)">Members</button>
                    <button type="button" class="tab-pill-btn" onclick="filterSolutions('trainers', this)">Trainers</button>
                    <button type="button" class="tab-pill-btn" onclick="filterSolutions('finance', this)">Finance</button>
                    <button type="button" class="tab-pill-btn" onclick="filterSolutions('fitness', this)">Fitness & Diet</button>
                </div>

                <!-- Solution Card 1: Membership Intelligence -->
                <div class="solution-showcase-card solution-card-item reveal-on-scroll" data-category="members" style="margin-bottom: 24px;">
                    <div>
                        <div class="badge-chip">MEMBERSHIP INTELLIGENCE</div>
                        <h3 style="font-size: 2rem; margin: 12px 0 16px;">Membership Control Center</h3>
                        <p style="color: var(--text-body); font-size: 1.05rem; line-height: 1.6;">
                            Track active memberships, renewal cadences, freezes, upgrades, and churn risk with automated triggers before revenue is lost.
                        </p>
                        <ul class="solution-checklist">
                            <li><i class="fa-solid fa-circle-check"></i> Automated renewal WhatsApp/SMS push notifications</li>
                            <li><i class="fa-solid fa-circle-check"></i> Real-time expiring membership risk radar (3d, 7d, 15d)</li>
                            <li><i class="fa-solid fa-circle-check"></i> Instant 1-click plan upgrades with pro-rated billing</li>
                            <li><i class="fa-solid fa-circle-check"></i> Comprehensive member lifecycle & LTV analytics</li>
                        </ul>
                        <a href="register-gym.php" class="btn btn-lime">
                            Deploy Membership Engine <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    </div>
                    <div style="background:#070a0f; border:1px solid rgba(255,255,255,0.08); border-radius:var(--radius-lg); padding:24px;">
                        <div style="display:flex; justify-content:space-between; margin-bottom:16px; font-weight:700; font-size:0.85rem;">
                            <span class="text-lime">MEMBERSHIP HEALTH AUDIT</span>
                            <span style="color:var(--text-muted);">LIVE SYNC</span>
                        </div>
                        <div style="display:flex; flex-direction:column; gap:12px;">
                            <div style="background:#111720; padding:12px 16px; border-radius:10px; display:flex; justify-content:space-between;">
                                <span>Active Paid Memberships</span>
                                <strong class="text-lime">1,680 (91.2%)</strong>
                            </div>
                            <div style="background:#111720; padding:12px 16px; border-radius:10px; display:flex; justify-content:space-between;">
                                <span>Renewed This Month</span>
                                <strong class="text-cyan">342 Members</strong>
                            </div>
                            <div style="background:#111720; padding:12px 16px; border-radius:10px; display:flex; justify-content:space-between;">
                                <span>At-Risk Expiries (Next 48h)</span>
                                <strong style="color:#ff5a36;">14 Members</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Solution Card 2: Operations Command -->
                <div class="solution-showcase-card solution-card-item reveal-on-scroll" data-category="operations" style="margin-bottom: 24px;">
                    <div>
                        <div class="badge-chip badge-chip-cyan">OPERATIONAL PRECISION</div>
                        <h3 style="font-size: 2rem; margin: 12px 0 16px;">Multi-Branch Facility Engine</h3>
                        <p style="color: var(--text-body); font-size: 1.05rem; line-height: 1.6;">
                            Consolidate equipment maintenance schedules, staff shift rosters, locker allocations, and multi-tenant permissions under one master pane.
                        </p>
                        <ul class="solution-checklist">
                            <li><i class="fa-solid fa-circle-check"></i> Equipment lifecycle logs & maintenance warranty alerts</li>
                            <li><i class="fa-solid fa-circle-check"></i> Role-based access control (Admin, Trainer, Front Desk, Superadmin)</li>
                            <li><i class="fa-solid fa-circle-check"></i> Centralized announcement broadcast system</li>
                            <li><i class="fa-solid fa-circle-check"></i> Row-level multi-tenant database isolation</li>
                        </ul>
                        <a href="register-gym.php" class="btn btn-lime">
                            Launch Multi-Branch OS <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    </div>
                    <div style="background:#070a0f; border:1px solid rgba(255,255,255,0.08); border-radius:var(--radius-lg); padding:24px;">
                        <div style="display:flex; justify-content:space-between; margin-bottom:16px; font-weight:700; font-size:0.85rem;">
                            <span class="text-cyan">STAFF & ASSET MATRIX</span>
                            <span class="text-lime">4 BRANCHES</span>
                        </div>
                        <div style="display:flex; flex-direction:column; gap:12px;">
                            <div style="background:#111720; padding:12px 16px; border-radius:10px; display:flex; justify-content:space-between;">
                                <span>Equipment Operational</span>
                                <strong class="text-lime">98.4% Uptime</strong>
                            </div>
                            <div style="background:#111720; padding:12px 16px; border-radius:10px; display:flex; justify-content:space-between;">
                                <span>Active Staff Logins</span>
                                <strong class="text-cyan">42 Staff Online</strong>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </section>

        <!-- =================================================================
             6. PRODUCT DASHBOARD SHOWCASE (FULL-WIDTH PERSPECTIVE)
             ================================================================= -->
        <section class="section-spacing" id="dashboard">
            <div class="container">
                <div class="product-showcase-box reveal-scale">
                    <div class="product-showcase-grid">
                        
                        <!-- Left: Live Dashboard Showcase UI -->
                        <div style="background:#070a0f; border:1px solid rgba(255,255,255,0.1); border-radius:var(--radius-lg); padding:24px; box-shadow:var(--shadow-xl);">
                            <div style="display:flex; align-items:center; justify-content:space-between; border-bottom:1px solid rgba(255,255,255,0.06); padding-bottom:14px; margin-bottom:18px;">
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <div style="width:12px; height:12px; border-radius:50%; background:var(--lime); box-shadow:0 0 10px var(--lime);"></div>
                                    <strong style="font-size:0.9rem; letter-spacing:0.05em;">MASTER GYM ANALYTICS ENGINE</strong>
                                </div>
                                <span class="badge-chip" style="font-size:0.65rem;">ENTERPRISE V3.4</span>
                            </div>

                            <div style="display:grid; grid-template-columns:repeat(2, 1fr); gap:14px; margin-bottom:16px;">
                                <div style="background:#0d1219; padding:16px; border-radius:12px; border:1px solid rgba(255,255,255,0.05);">
                                    <div style="font-size:0.75rem; color:var(--text-muted); font-weight:700;">TOTAL MONTHLY REVENUE</div>
                                    <div style="font-size:1.6rem; font-weight:900; font-family:'Outfit'; color:#fff; margin:4px 0;" data-counter="482500" data-counter-prefix="₹">₹4,82,500</div>
                                    <span style="font-size:0.75rem; color:var(--lime); font-weight:700;">↑ 22.4% vs last month</span>
                                </div>
                                <div style="background:#0d1219; padding:16px; border-radius:12px; border:1px solid rgba(255,255,255,0.05);">
                                    <div style="font-size:0.75rem; color:var(--text-muted); font-weight:700;">ACTIVE ATHLETES</div>
                                    <div style="font-size:1.6rem; font-weight:900; font-family:'Outfit'; color:var(--lime); margin:4px 0;" data-counter="1842">1,842</div>
                                    <span style="font-size:0.75rem; color:var(--cyan); font-weight:700;">94% Attendance Rate</span>
                                </div>
                            </div>

                            <div style="background:#0d1219; padding:18px; border-radius:12px; border:1px solid rgba(255,255,255,0.05);">
                                <div style="display:flex; justify-content:space-between; margin-bottom:10px; font-size:0.8rem; font-weight:700;">
                                    <span>REAL-TIME CASHFLOW INFLOW</span>
                                    <span class="text-lime">100% CASHFREE VERIFIED</span>
                                </div>
                                <div style="height:8px; width:100%; background:rgba(255,255,255,0.08); border-radius:99px; overflow:hidden; display:flex;">
                                    <div style="width:87%; background:var(--lime);"></div>
                                    <div style="width:13%; background:#ff5a36;"></div>
                                </div>
                                <div style="display:flex; justify-content:space-between; margin-top:8px; font-size:0.75rem; color:var(--text-muted);">
                                    <span>Collected: ₹4,21,300</span>
                                    <span>Pending Dues: ₹61,200</span>
                                </div>
                            </div>
                        </div>

                        <!-- Right: Feature List & Pitch -->
                        <div>
                            <div class="badge-chip">UNIFIED CONTROL</div>
                            <h2 style="font-size: 2.4rem; margin: 16px 0 20px;">
                                YOUR ENTIRE GYM.<br />
                                <span class="text-lime">ONE INTELLIGENT PLATFORM.</span>
                            </h2>
                            <p style="color: var(--text-body); font-size: 1.05rem; line-height: 1.65; margin-bottom: 28px;">
                                Replace 6 disconnected tools with a unified fitness operating system designed to automate manual tasks and maximize client lifetime value.
                            </p>
                            <ul class="solution-checklist" style="margin-bottom: 36px;">
                                <li><i class="fa-solid fa-check text-lime"></i> Real-time multi-branch member analytics & attendance logs</li>
                                <li><i class="fa-solid fa-check text-lime"></i> Automated Cashfree UPI payment links & invoice generation</li>
                                <li><i class="fa-solid fa-check text-lime"></i> Trainer client assignment & custom workout split builder</li>
                                <li><i class="fa-solid fa-check text-lime"></i> Member self-service mobile portal with QR pass</li>
                                <li><i class="fa-solid fa-check text-lime"></i> 256-bit encrypted multi-tenant data architecture</li>
                            </ul>
                            <a href="register-gym.php" class="btn btn-lime btn-lg">
                                Explore Platform Demo <i class="fa-solid fa-arrow-right"></i>
                            </a>
                        </div>

                    </div>
                </div>
            </div>
        </section>

        <!-- =================================================================
             7. MEMBER & ATHLETE EXPERIENCE SECTION (MOBILE APP & PORTAL)
             ================================================================= -->
        <section class="section-spacing" id="athlete">
            <div class="container">
                <div class="member-exp-grid">
                    
                    <!-- Left: Description of Athlete App & Downloads -->
                    <div class="reveal-fade-left">
                        <div class="badge-chip badge-chip-cyan">
                            <i class="fa-solid fa-mobile-screen-button"></i> OFFICIAL MEMBER MOBILE APP
                        </div>
                        <h2 class="section-title" style="text-align: left;">
                            FITISIFY ATHLETE APP. <br />
                            <span class="text-lime">POWER IN YOUR POCKET.</span>
                        </h2>
                        <p style="color: var(--text-body); font-size: 1.08rem; line-height: 1.7; margin-bottom: 24px;">
                            Delight your gym athletes with the official <strong>Fitisify Mobile Application</strong>. Members can check in instantly using biometric QR passes, track workout streaks and body fat metrics, view customized daily diet plans, and download instant GST payment receipts.
                        </p>

                        <div style="display:flex; flex-direction:column; gap:16px; margin-bottom:28px;">
                            <div style="display:flex; gap:14px; align-items:flex-start;">
                                <div style="width:36px; height:36px; border-radius:10px; background:rgba(199,255,46,0.1); display:flex; align-items:center; justify-content:center; color:var(--lime); font-size:1.1rem; flex-shrink:0;">
                                    <i class="fa-solid fa-qrcode"></i>
                                </div>
                                <div>
                                    <h4 style="font-size:1.05rem; margin-bottom:2px;">Dynamic Biometric QR Pass</h4>
                                    <p style="font-size:0.9rem; color:var(--text-muted); line-height:1.4;">Instant contactless turnstile check-in with duplicate scan prevention and dynamic color codes.</p>
                                </div>
                            </div>

                            <div style="display:flex; gap:14px; align-items:flex-start;">
                                <div style="width:36px; height:36px; border-radius:10px; background:rgba(0,217,255,0.1); display:flex; align-items:center; justify-content:center; color:var(--cyan); font-size:1.1rem; flex-shrink:0;">
                                    <i class="fa-solid fa-bell"></i>
                                </div>
                                <div>
                                    <h4 style="font-size:1.05rem; margin-bottom:2px;">Real-Time Push Notifications</h4>
                                    <p style="font-size:0.9rem; color:var(--text-muted); line-height:1.4;">Automated expiry alerts, gym broadcast bulletins, trainer routine updates, and fee receipts.</p>
                                </div>
                            </div>

                            <div style="display:flex; gap:14px; align-items:flex-start;">
                                <div style="width:36px; height:36px; border-radius:10px; background:rgba(16,185,129,0.1); display:flex; align-items:center; justify-content:center; color:#10b981; font-size:1.1rem; flex-shrink:0;">
                                    <i class="fa-solid fa-fire"></i>
                                </div>
                                <div>
                                    <h4 style="font-size:1.05rem; margin-bottom:2px;">Workout Streaks & Diet Macros</h4>
                                    <p style="font-size:0.9rem; color:var(--text-muted); line-height:1.4;">Caloric tracking, hydration targets, personal record logs, and trainer-assigned fitness splits.</p>
                                </div>
                            </div>
                        </div>

                        <!-- App Download Showcase Card (Ultra-Premium Dark-Tech Hub) -->
                        <div class="fitisify-app-showcase-card" style="background: linear-gradient(145deg, rgba(17, 23, 32, 0.95) 0%, rgba(10, 14, 20, 0.98) 100%); border: 1px solid rgba(199, 255, 46, 0.25); border-radius: 20px; padding: 22px 20px; box-shadow: 0 16px 40px -10px rgba(0, 0, 0, 0.7), 0 0 25px rgba(199, 255, 46, 0.08); margin-top: 24px; display: flex; flex-direction: column; gap: 16px;">
                            
                            <!-- Download Buttons (2-Column Responsive Grid) -->
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px;">
                                
                                <!-- Official Google Play Store Button -->
                                <a href="<?php echo e($appPlayStoreUrl); ?>" target="_blank" rel="noopener noreferrer" class="fitisify-store-btn" style="display: flex; align-items: center; gap: 14px; background: #0d1219; border: 1px solid rgba(255, 255, 255, 0.12); border-radius: 14px; padding: 12px 18px; text-decoration: none; transition: all 0.25s cubic-bezier(0.2, 0.8, 0.2, 1); box-shadow: 0 4px 14px rgba(0,0,0,0.5);" onmouseover="this.style.borderColor='#00d9ff'; this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 24px rgba(0,217,255,0.25)';" onmouseout="this.style.borderColor='rgba(255,255,255,0.12)'; this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 14px rgba(0,0,0,0.5)';">
                                    <div style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                        <svg width="28" height="30" viewBox="0 0 256 281" preserveAspectRatio="xMidYMid">
                                            <path fill="#00C1FF" d="M.4 12.8C.1 14.7 0 16.7 0 18.9v243.2c0 2.2.1 4.2.4 6.1l132.8-129.7L.4 12.8z"/>
                                            <path fill="#00E676" d="M174.5 96.6L133.2 138.5l41.3 41.9 48.7-28.1c8.1-4.7 13.4-13.3 13.4-23.3s-5.3-18.6-13.4-23.3l-48.7-29.1z"/>
                                            <path fill="#FF3D00" d="M133.2 138.5L.4 268.2c3.5 1.5 7.6 2.3 12 2.3 4.9 0 9.8-1.3 14.1-3.8l148-86.3-41.3-41.9z"/>
                                            <path fill="#FFD400" d="M174.5 96.6L26.5 4.3C22.2 1.8 17.3.5 12.4.5c-4.4 0-8.5.8-12 2.3l132.8 135.7 41.3-41.9z"/>
                                        </svg>
                                    </div>
                                    <div style="display: flex; flex-direction: column; text-align: left; line-height: 1.2;">
                                        <span style="font-size: 0.65rem; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; color: #94a3b8; margin-bottom: 2px;">GET IT ON</span>
                                        <span style="font-family: 'Outfit', -apple-system, sans-serif; font-size: 1.1rem; font-weight: 800; color: #ffffff;">Google Play</span>
                                    </div>
                                </a>

                                <!-- Direct Android APK Download Button -->
                                <a href="<?php echo e($appApkDownloadUrl); ?>" download class="fitisify-store-btn" style="display: flex; align-items: center; gap: 14px; background: linear-gradient(135deg, rgba(199, 255, 46, 0.08) 0%, #0d1219 100%); border: 1px solid rgba(199, 255, 46, 0.38); border-radius: 14px; padding: 12px 18px; text-decoration: none; transition: all 0.25s cubic-bezier(0.2, 0.8, 0.2, 1); box-shadow: 0 4px 14px rgba(0,0,0,0.5);" onmouseover="this.style.borderColor='var(--lime)'; this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 24px rgba(199,255,46,0.25)';" onmouseout="this.style.borderColor='rgba(199,255,46,0.38)'; this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 14px rgba(0,0,0,0.5)';">
                                    <div style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                        <svg width="26" height="26" viewBox="0 0 24 24" fill="#c7ff2e">
                                            <path d="M17.523 15.3414c-.5511 0-.9993-.4486-.9993-1.0003 0-.5517.4482-1.0003.9993-1.0003.5511 0 .9993.4486.9993 1.0003 0 .5517-.4482 1.0003-.9993 1.0003m-11.046 0c-.5511 0-.9993-.4486-.9993-1.0003 0-.5517.4482-1.0003.9993-1.0003.5511 0 .9993.4486.9993 1.0003 0 .5517-.4482 1.0003-.9993 1.0003m11.4045-6.02l1.9973-3.4592a.416.416 0 00-.1521-.5676.416.416 0 00-.5676.1521l-2.0223 3.503C15.5902 8.4111 13.8533 8.0817 12 8.0817c-1.8533 0-3.5902.3294-5.1368.868L4.8409 5.4467a.4161.4161 0 00-.5677-.1521.4157.4157 0 00-.1521.5676l1.9973 3.4592C2.6889 11.1867.3432 14.6589 0 18.761h24c-.3432-4.1021-2.6889-7.5743-6.1185-9.4396"/>
                                        </svg>
                                    </div>
                                    <div style="display: flex; flex-direction: column; text-align: left; line-height: 1.2;">
                                        <span style="font-size: 0.65rem; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; color: var(--lime); margin-bottom: 2px;">
                                            <i class="fa-solid fa-download" style="margin-right: 3px;"></i> DIRECT APK
                                        </span>
                                        <span style="font-family: 'Outfit', -apple-system, sans-serif; font-size: 1.1rem; font-weight: 800; color: #ffffff; display: flex; align-items: center; gap: 6px;">
                                            Android App
                                            <span style="font-size: 0.7rem; font-weight: 800; background: rgba(199,255,46,0.15); color: #c7ff2e; border: 1px solid rgba(199,255,46,0.4); padding: 1px 6px; border-radius: 999px;"><?php echo e($appVersion); ?></span>
                                        </span>
                                    </div>
                                </a>

                            </div>

                            <!-- Mobile QR Code & Verification Strip -->
                            <div style="display: flex; align-items: center; gap: 14px; background: rgba(10, 14, 20, 0.85); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 14px; padding: 12px 14px;">
                                <div style="width: 72px; height: 72px; background: #ffffff; border-radius: 10px; padding: 4px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(0,0,0,0.6);">
                                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=160x160&margin=4&data=<?php echo urlencode($appApkDownloadUrl); ?>" alt="Scan to Download APK" style="width: 100%; height: 100%; display: block; border-radius: 6px;" />
                                </div>
                                <div style="display: flex; flex-direction: column; gap: 3px;">
                                    <div style="font-size: 0.88rem; font-weight: 800; color: #ffffff; display: flex; align-items: center; gap: 6px;">
                                        <i class="fa-solid fa-qrcode" style="color: var(--lime);"></i> Scan to Install on Phone
                                    </div>
                                    <div style="font-size: 0.76rem; color: #94a3b8; line-height: 1.4;">
                                        Package: <strong style="color: #fff;"><?php echo e($appApkSizeFormatted); ?></strong> &bull; Requires: <strong style="color: #fff;"><?php echo e($appMinAndroid); ?></strong>
                                    </div>
                                    <div style="display: inline-flex; align-items: center; gap: 5px; font-size: 0.72rem; font-weight: 700; color: #10b981; margin-top: 1px;">
                                        <i class="fa-solid fa-shield-halved"></i> 100% Virus-Free &bull; SHA-256 Validated
                                    </div>
                                </div>
                            </div>

                            <!-- Alternate Web Portal Link -->
                            <div style="display: flex; align-items: center; justify-content: space-between; padding-top: 2px;">
                                <a href="index2.php" style="font-size: 0.82rem; color: #94a3b8; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; transition: color 0.2s ease;" onmouseover="this.style.color='#c7ff2e';" onmouseout="this.style.color='#94a3b8';">
                                    <i class="fa-solid fa-desktop" style="color: var(--cyan);"></i> Access Member Portal in Web Browser &rarr;
                                </a>
                            </div>

                        </div>
                    </div>

                    <!-- Right: Realistic Athlete Phone Mockup -->
                    <div class="reveal-fade-right">
                        <div class="phone-mockup-frame">
                            <div class="phone-notch"></div>
                            
                            <!-- Athlete Profile Header -->
                            <div class="athlete-profile-card">
                                <img src="customer/img/demo/av1.jpg" class="athlete-avatar" alt="Rahul Sharma" />
                                <div>
                                    <div style="font-weight:800; font-size:1.05rem; color:#fff;">Rahul Sharma</div>
                                    <div style="font-size:0.75rem; color:var(--lime); font-weight:700;">
                                        <i class="fa-solid fa-crown"></i> PLATINUM VIP • ACTIVE
                                    </div>
                                </div>
                            </div>

                            <!-- Athlete Stats Grid with Progress Fill Animations -->
                            <div class="athlete-stats-grid">
                                <div class="athlete-stat-tile">
                                    <div class="athlete-stat-val text-lime">78.2 kg</div>
                                    <div class="athlete-stat-lbl">Body Weight (↓8.2%)</div>
                                    <div class="progress-track"><div class="progress-fill-lime" data-width="84%"></div></div>
                                </div>
                                <div class="athlete-stat-tile">
                                    <div class="athlete-stat-val text-cyan">14.8%</div>
                                    <div class="athlete-stat-lbl">Body Fat Index</div>
                                    <div class="progress-track"><div class="progress-fill-cyan" data-width="68%"></div></div>
                                </div>
                                <div class="athlete-stat-tile">
                                    <div class="athlete-stat-val" style="color:var(--gold);">18 Days 🔥</div>
                                    <div class="athlete-stat-lbl">Workout Streak</div>
                                    <div class="progress-track"><div class="progress-fill-lime" data-width="90%"></div></div>
                                </div>
                                <div class="athlete-stat-tile">
                                    <div class="athlete-stat-val" style="color:#10b981;">94%</div>
                                    <div class="athlete-stat-lbl">Monthly Attendance</div>
                                    <div class="progress-track"><div class="progress-fill-lime" data-width="94%"></div></div>
                                </div>
                            </div>

                            <!-- Digital QR Pass Card -->
                            <div style="background:#111720; border:1px solid rgba(199,255,46,0.25); border-radius:var(--radius-md); padding:16px; text-align:center; margin-bottom:12px;">
                                <div style="font-size:0.72rem; font-weight:800; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.08em; margin-bottom:10px;">
                                    DIGITAL MEMBER CHECK-IN PASS
                                </div>
                                <div style="background:#fff; padding:10px; border-radius:10px; display:inline-block;">
                                    <i class="fa-solid fa-qrcode" style="font-size:4rem; color:#05080d;"></i>
                                </div>
                                <div style="font-size:0.72rem; color:var(--lime); font-weight:700; margin-top:8px;">
                                    <span class="pulse-dot" style="display:inline-block; margin-right:4px;"></span> READY TO SCAN AT FRONT DESK
                                </div>
                            </div>

                            <!-- Upcoming Session Card -->
                            <div style="background:#0d1219; border:1px solid rgba(255,255,255,0.06); border-radius:10px; padding:12px; font-size:0.8rem; display:flex; justify-content:space-between; align-items:center;">
                                <div>
                                    <div style="font-weight:700; color:#fff;">HIIT Strength Session</div>
                                    <div style="color:var(--text-muted); font-size:0.72rem;">Trainer Vikram • 06:00 PM Today</div>
                                </div>
                                <span class="badge-chip" style="font-size:0.65rem; padding:3px 8px;">BOOKED</span>
                            </div>

                        </div>
                    </div>

                </div>
            </div>
        </section>

        <!-- =================================================================
             8. ATTENDANCE & LIVE SCANNER SIMULATOR
             ================================================================= -->
        <section class="section-spacing" id="attendance" style="background: rgba(13, 18, 25, 0.4);">
            <div class="container container-narrow">
                <div class="section-header reveal-on-scroll">
                    <div class="badge-chip">BIOMETRIC SCAN ENGINE</div>
                    <h2 class="section-title">SUB-SECOND CHECK-IN VISIBILITY.</h2>
                    <p class="section-subtitle">
                        Experience hardware-accelerated member check-in with duplicate scan safeguards and live real-time facility occupancy.
                    </p>
                </div>

                <div class="scanner-demo-box reveal-scale">
                    <div style="display:flex; align-items:center; gap:8px; font-size:0.85rem; font-weight:800; color:var(--lime); letter-spacing:0.08em; text-transform:uppercase;">
                        <span class="pulse-dot"></span> CURRENTLY INSIDE GYM: <span id="live-inside-count" style="font-size:1.2rem; color:#fff; margin-left:4px;" data-counter="126">126</span> ATHLETES
                    </div>

                    <!-- Scanner Viewfinder Simulation -->
                    <div class="scanner-viewfinder">
                        <div class="scanner-laser"></div>
                        <i class="fa-solid fa-qrcode" style="font-size: 6rem; color: rgba(255,255,255,0.15);"></i>
                    </div>

                    <button type="button" id="btn-scan-trigger" class="btn btn-lime" onclick="triggerScanDemo()">
                        <i class="fa-solid fa-barcode"></i> Simulate Member QR Scan
                    </button>

                    <!-- Scan Result Card -->
                    <div id="scanner-result-card" style="display:none; margin-top:24px; width:100%; max-width:440px; background:#111720; border:1px solid var(--lime-border); border-radius:var(--radius-md); padding:16px; text-align:left;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                            <span class="badge-chip" style="font-size:0.7rem;">MEMBER DETECTED</span>
                            <span style="font-size:0.75rem; color:var(--lime); font-weight:700;">CHECK-IN RECORDED</span>
                        </div>
                        <div style="display:flex; align-items:center; gap:12px; margin-top:10px;">
                            <img src="customer/img/demo/av1.jpg" style="width:42px; height:42px; border-radius:50%; border:2px solid var(--lime);" alt="Rahul" />
                            <div>
                                <div style="font-weight:800; font-size:1rem; color:#fff;">Rahul Sharma</div>
                                <div style="font-size:0.78rem; color:var(--text-muted);">Platinum Annual Membership • Active</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- =================================================================
             9. MULTI-BRANCH CONTROL CENTER
             ================================================================= -->
        <section class="section-spacing" id="branches">
            <div class="container">
                <div class="section-header reveal-on-scroll">
                    <div class="badge-chip badge-chip-cyan">ENTERPRISE EXPANSION</div>
                    <h2 class="section-title">YOUR GYM. EVERY LOCATION. ONE OS.</h2>
                    <p class="section-subtitle">
                        Seamlessly navigate multi-city operations with consolidated metrics, localized currency, and isolated branch permissions.
                    </p>
                </div>

                <!-- Branch Selector Pills -->
                <div class="branch-switcher-nav reveal-on-scroll">
                    <button type="button" class="branch-btn active" onclick="switchBranch('mumbai', this)">Mumbai Flagship</button>
                    <button type="button" class="branch-btn" onclick="switchBranch('delhi', this)">Delhi South</button>
                    <button type="button" class="branch-btn" onclick="switchBranch('bangalore', this)">Bangalore Tech Park</button>
                    <button type="button" class="branch-btn" onclick="switchBranch('patna', this)">Patna Central</button>
                </div>

                <!-- Dynamic Branch Stats Board -->
                <div class="reveal-scale" style="background:var(--bg-surface-card); border:1px solid var(--border-card); border-radius:var(--radius-xl); padding:40px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:20px; border-bottom:1px solid var(--border-subtle); padding-bottom:24px; margin-bottom:32px;">
                        <div>
                            <div style="font-size:0.8rem; font-weight:800; color:var(--text-muted); text-transform:uppercase;">CURRENT BRANCH SELECTED</div>
                            <h3 id="branch-display-name" style="font-size:1.8rem; color:#fff; margin-top:4px;">Mumbai Flagship (Bandra)</h3>
                        </div>
                        <div id="branch-display-status" class="badge-chip">
                            OPTIMAL CAPACITY (84%)
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:24px;">
                        <div style="background:#0d1219; padding:20px; border-radius:14px; border:1px solid rgba(255,255,255,0.05);">
                            <div style="font-size:0.78rem; font-weight:700; color:var(--text-muted);">ACTIVE MEMBERS</div>
                            <div id="branch-display-members" style="font-size:2rem; font-weight:900; color:#fff; font-family:'Outfit'; margin-top:6px;">1,842</div>
                        </div>
                        <div style="background:#0d1219; padding:20px; border-radius:14px; border:1px solid rgba(255,255,255,0.05);">
                            <div style="font-size:0.78rem; font-weight:700; color:var(--text-muted);">MONTHLY REVENUE</div>
                            <div id="branch-display-revenue" style="font-size:2rem; font-weight:900; color:var(--lime); font-family:'Outfit'; margin-top:6px;">₹4,82,500</div>
                        </div>
                        <div style="background:#0d1219; padding:20px; border-radius:14px; border:1px solid rgba(255,255,255,0.05);">
                            <div style="font-size:0.78rem; font-weight:700; color:var(--text-muted);">TODAY'S CHECK-INS</div>
                            <div id="branch-display-checkins" style="font-size:2rem; font-weight:900; color:var(--cyan); font-family:'Outfit'; margin-top:6px;">620</div>
                        </div>
                        <div style="background:#0d1219; padding:20px; border-radius:14px; border:1px solid rgba(255,255,255,0.05);">
                            <div style="font-size:0.78rem; font-weight:700; color:var(--text-muted);">ACTIVE TRAINERS</div>
                            <div id="branch-display-trainers" style="font-size:2rem; font-weight:900; color:var(--gold); font-family:'Outfit'; margin-top:6px;">18</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- =================================================================
             10. AUTOMATED WORKFLOWS PIPELINE
             ================================================================= -->
        <section class="section-spacing" style="background: rgba(13, 18, 25, 0.4);">
            <div class="container">
                <div class="section-header reveal-on-scroll">
                    <div class="badge-chip">AUTONOMOUS OPERATIONS</div>
                    <h2 class="section-title">LET THE SYSTEM HANDLE FOLLOW-UPS.</h2>
                    <p class="section-subtitle">
                        Save 15+ administrative hours every week with automated WhatsApp triggers, renewal recovery flows, and churn prevention.
                    </p>
                </div>

                <div class="automation-flow-grid">
                    
                    <!-- Workflow 1 -->
                    <div class="automation-pipe-card reveal-on-scroll reveal-delay-100">
                        <div class="badge-chip" style="font-size:0.7rem; align-self:flex-start;">PIPELINE 01</div>
                        <h4 style="font-size:1.2rem; color:#fff;">Membership Expiry Recovery</h4>
                        <div class="flow-step">
                            <i class="fa-solid fa-clock-rotate-left"></i> 3 Days to Membership Expiry
                        </div>
                        <div class="flow-arrow"><i class="fa-solid fa-arrow-down"></i></div>
                        <div class="flow-step">
                            <i class="fa-brands fa-whatsapp text-lime"></i> WhatsApp 1-Click Payment Link
                        </div>
                        <div class="flow-arrow"><i class="fa-solid fa-arrow-down"></i></div>
                        <div class="flow-step" style="border-color:var(--lime-border); background:rgba(199,255,46,0.05);">
                            <i class="fa-solid fa-badge-check text-lime"></i> Instant UPI Payment & Renewal
                        </div>
                    </div>

                    <!-- Workflow 2 -->
                    <div class="automation-pipe-card reveal-on-scroll reveal-delay-200">
                        <div class="badge-chip badge-chip-cyan" style="font-size:0.7rem; align-self:flex-start;">PIPELINE 02</div>
                        <h4 style="font-size:1.2rem; color:#fff;">Inactive Member Reactivation</h4>
                        <div class="flow-step">
                            <i class="fa-solid fa-user-slash"></i> Inactive for 7 Consecutive Days
                        </div>
                        <div class="flow-arrow"><i class="fa-solid fa-arrow-down text-cyan"></i></div>
                        <div class="flow-step">
                            <i class="fa-solid fa-comment-sms text-cyan"></i> Motivational Check-in & Free PT Invite
                        </div>
                        <div class="flow-arrow"><i class="fa-solid fa-arrow-down text-cyan"></i></div>
                        <div class="flow-step" style="border-color:rgba(0,217,255,0.3); background:rgba(0,217,255,0.05);">
                            <i class="fa-solid fa-circle-check text-cyan"></i> Athlete Reactivated & Session Booked
                        </div>
                    </div>

                    <!-- Workflow 3 -->
                    <div class="automation-pipe-card reveal-on-scroll reveal-delay-300">
                        <div class="badge-chip" style="font-size:0.7rem; align-self:flex-start; color:var(--gold); border-color:rgba(244,196,48,0.3); background:rgba(244,196,48,0.08);">PIPELINE 03</div>
                        <h4 style="font-size:1.2rem; color:#fff;">Birthday & Milestone Delight</h4>
                        <div class="flow-step">
                            <i class="fa-solid fa-cake-candles"></i> Member Birthday Detected in DB
                        </div>
                        <div class="flow-arrow"><i class="fa-solid fa-arrow-down" style="color:var(--gold);"></i></div>
                        <div class="flow-step">
                            <i class="fa-solid fa-gift" style="color:var(--gold);"></i> Automated Shake Discount Code Sent
                        </div>
                        <div class="flow-arrow"><i class="fa-solid fa-arrow-down" style="color:var(--gold);"></i></div>
                        <div class="flow-step" style="border-color:rgba(244,196,48,0.3); background:rgba(244,196,48,0.05);">
                            <i class="fa-solid fa-heart" style="color:var(--gold);"></i> Delight & Referral Retention
                        </div>
                    </div>

                </div>
            </div>
        </section>

        <!-- =================================================================
             11. DYNAMIC SAAS PRICING (DATABASE DRIVEN + GEO-CURRENCY)
             ================================================================= -->
        <section class="section-spacing" id="pricing">
            <div class="container">
                <div class="section-header reveal-on-scroll">
                    <div class="badge-chip">TRANSPARENT VALUE</div>
                    <h2 class="section-title">PREDICTABLE PLANS FOR GROWING GYMS.</h2>
                    <p class="section-subtitle">
                        Every plan includes multi-tenant isolation, SSL certificates, Cashfree online gateway integration, and 24/7 priority support.
                    </p>
                </div>

                <!-- Monthly vs Annual Toggle (20% OFF) -->
                <div class="pricing-toggle-wrap reveal-on-scroll">
                    <div class="pricing-toggle-container">
                        <button type="button" class="pricing-cycle-btn active" id="btn-monthly" onclick="setBillingCycle('monthly')">
                            Monthly Billing
                        </button>
                        <button type="button" class="pricing-cycle-btn" id="btn-yearly" onclick="setBillingCycle('yearly')">
                            Annual Billing <span style="background:#10b981; color:#fff; font-size:0.7rem; font-weight:800; padding:2px 7px; border-radius:4px; margin-left:4px;">SAVE 20%</span>
                        </button>
                    </div>
                </div>

                <!-- Dynamic Pricing Cards Grid -->
                <div class="pricing-cards-grid">
                    <?php foreach ($plans as $index => $p): ?>
                        <?php 
                            $isFeatured = (strpos(strtolower($p['name']), 'pro') !== false || strpos(strtolower($p['name']), 'growth') !== false || (int)$p['id'] === 2);
                            $monthlyPrice = (float)($p['price_monthly'] ?? 0);
                            $yearlyPrice = (float)($p['price_yearly'] ?? ($monthlyPrice * 10));
                        ?>
                        <div class="pricing-tier-card <?php echo $isFeatured ? 'featured' : ''; ?> reveal-on-scroll reveal-delay-<?php echo ($index + 1) * 100; ?>">
                            <?php if ($isFeatured): ?>
                                <div class="pricing-tier-badge">MOST POPULAR</div>
                            <?php endif; ?>

                            <div>
                                <h3 style="font-size: 1.5rem; color: #fff; margin-bottom: 6px;">
                                    <?php echo e($p['name']); ?>
                                </h3>
                                <p style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 20px;">
                                    Ideal for <?php echo (int)($p['max_members'] ?? 0) >= 500 ? 'rapidly scaling fitness franchises & chains' : 'independent gyms & fitness studios'; ?>
                                </p>

                                <div class="tier-price-row">
                                    <span class="tier-currency-symbol">₹</span>
                                    <span class="tier-price-val" 
                                          data-price-monthly="<?php echo $monthlyPrice; ?>"
                                          data-price-yearly="<?php echo $yearlyPrice; ?>">
                                        <?php echo number_format($monthlyPrice, 0); ?>
                                    </span>
                                    <span class="tier-cycle-label">/month</span>
                                </div>

                                <ul class="tier-feature-list">
                                    <li>
                                        <i class="fa-solid fa-circle-check"></i>
                                        <span><strong><?php echo number_format((int)($p['max_members'] ?? 0)); ?></strong> Member Capacity</span>
                                    </li>
                                    <li>
                                        <i class="fa-solid fa-circle-check"></i>
                                        <span><strong><?php echo number_format((int)($p['max_staff'] ?? 0)); ?></strong> Staff & Trainer Logins</span>
                                    </li>
                                    <li>
                                        <i class="fa-solid fa-circle-check"></i>
                                        <span><strong><?php echo number_format((int)($p['max_branches'] ?? 1)); ?></strong> Facility Location(s)</span>
                                    </li>
                                    <li>
                                        <i class="fa-solid fa-circle-check"></i>
                                        <span><strong><?php echo (int)($p['grace_period_days'] ?? 7); ?> Days</strong> Renewal Grace Period</span>
                                    </li>
                                    <li>
                                        <i class="fa-solid fa-circle-check"></i>
                                        <span>Instant Cashfree UPI Gateway Integration</span>
                                    </li>
                                    <li>
                                        <i class="fa-solid fa-circle-check"></i>
                                        <span>Customer Mobile & Web Self-Service App</span>
                                    </li>
                                </ul>
                            </div>

                            <a href="register-gym.php?plan=<?php echo $p['id']; ?>" class="btn <?php echo $isFeatured ? 'btn-lime' : 'btn-ghost-dark'; ?>" style="width:100%;">
                                <?php echo $monthlyPrice == 0 ? 'Start Free Trial' : 'Get Started Now'; ?> <i class="fa-solid fa-arrow-right"></i>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>

            </div>
        </section>

        <!-- =================================================================
             12. VERIFIED CLIENT TESTIMONIALS
             ================================================================= -->
        <section class="section-spacing" style="background: rgba(13, 18, 25, 0.4);">
            <div class="container">
                <div class="section-header reveal-on-scroll">
                    <div class="badge-chip">TESTED & PROVEN</div>
                    <h2 class="section-title">LOVED BY GYM OPERATORS.</h2>
                    <p class="section-subtitle">
                        See why over 1,200+ fitness business owners rely on Fitisify OS every morning.
                    </p>
                </div>

                <div class="testimonials-grid">
                    <?php 
                    $delay = 100;
                    foreach ($testimonials as $t): 
                        $rating = isset($t['rating']) ? (int)$t['rating'] : 5;
                        $avatar = !empty($t['avatar']) ? (str_starts_with($t['avatar'], 'http') ? $t['avatar'] : $t['avatar']) : 'customer/img/demo/av1.jpg';
                    ?>
                        <div class="testimonial-card reveal-on-scroll reveal-delay-<?php echo $delay; ?>">
                            <div class="stars" style="color:var(--gold); margin-bottom:16px;">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fa<?php echo ($i <= $rating) ? 's' : 'r'; ?> fa-star"></i>
                                <?php endfor; ?>
                            </div>
                            <p class="testimonial-quote">
                                "<?php echo htmlspecialchars($t['quote']); ?>"
                            </p>
                            <div class="testimonial-author">
                                <img src="<?php echo htmlspecialchars($avatar); ?>" class="author-avatar" alt="<?php echo htmlspecialchars($t['author_name']); ?>" onerror="this.src='customer/img/demo/av1.jpg';" />
                                <div>
                                    <div style="font-weight:800; color:#fff;"><?php echo htmlspecialchars($t['author_name']); ?></div>
                                    <div style="font-size:0.8rem; color:var(--text-muted);"><?php echo htmlspecialchars($t['designation']); ?></div>
                                </div>
                            </div>
                        </div>
                    <?php 
                        $delay = ($delay >= 300) ? 100 : ($delay + 100);
                    endforeach; 
                    ?>
                </div>
            </div>
        </section>

        <!-- =================================================================
             13. INTERACTIVE FAQ ACCORDION
             ================================================================= -->
        <section class="section-spacing" id="faq">
            <div class="container container-narrow">
                <div class="section-header reveal-on-scroll">
                    <div class="badge-chip badge-chip-cyan">QUESTIONS & ANSWERS</div>
                    <h2 class="section-title">FREQUENTLY ASKED QUESTIONS.</h2>
                    <p class="section-subtitle">
                        Everything you need to know about onboarding, multi-branch scaling, and Cashfree payments.
                    </p>
                </div>

                <div class="faq-accordion reveal-on-scroll">
                    <div class="faq-item active">
                        <div class="faq-header">
                            <span>How fast can I launch Fitisify OS for my gym?</span>
                            <i class="fa-solid fa-chevron-down"></i>
                        </div>
                        <div class="faq-body">
                            You can register and have your complete multi-tenant gym portal live in under 2 minutes. The platform automatically provisions your gym database, admin portal, trainer accounts, and self-service member app instantly.
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-header">
                            <span>Can members check in using their own smartphones?</span>
                            <i class="fa-solid fa-chevron-down"></i>
                        </div>
                        <div class="faq-body">
                            Yes! Each member receives a dynamic digital QR Membership Pass inside their customer portal. Your front desk staff or iPad kiosk can scan this QR code in under 0.2 seconds to verify active membership status and record attendance.
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-header">
                            <span>How does Cashfree online payment integration work?</span>
                            <i class="fa-solid fa-chevron-down"></i>
                        </div>
                        <div class="faq-body">
                            Members can settle dues directly via UPI (Google Pay, PhonePe, Paytm), Credit/Debit Cards, and Net Banking. When a payment completes, the invoice is automatically marked paid in real time and an instant GST-compliant PDF receipt is generated.
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-header">
                            <span>Can I manage multiple gym locations under one master account?</span>
                            <i class="fa-solid fa-chevron-down"></i>
                        </div>
                        <div class="faq-body">
                            Absolutely. Our multi-tenant and multi-branch architecture lets franchise owners toggle between different branches while keeping attendance, revenue, and staff permissions neatly compartmentalized.
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-header">
                            <span>Is our gym and member data secure?</span>
                            <i class="fa-solid fa-chevron-down"></i>
                        </div>
                        <div class="faq-body">
                            All data is secured with 256-bit SSL encryption, automated hourly cloud backups, and strict row-level tenant data isolation so no gym's records can ever cross into another.
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- =================================================================
             14. CINEMATIC FINAL CONVERSION CTA
             ================================================================= -->
        <section class="final-cta-section">
            <div class="container">
                <div class="final-cta-box reveal-scale">
                    <div class="badge-chip" style="margin-bottom: 20px;">
                        <span class="pulse-dot"></span> RISK-FREE 14-DAY TRIAL
                    </div>
                    <h2 class="final-cta-title">
                        READY TO RUN A <br />
                        <span class="text-lime">SMARTER, RICHER GYM?</span>
                    </h2>
                    <p class="final-cta-subtitle">
                        Join 1,200+ elite gym operators who eliminated spreadsheet chaos and scaled their revenue with Fitisify OS.
                    </p>
                    <div style="display:flex; justify-content:center; gap:16px; flex-wrap:wrap;">
                        <a href="register-gym.php" class="btn btn-lime btn-lg">
                            Start Free Trial Today <i class="fa-solid fa-arrow-right"></i>
                        </a>
                        <a href="index2.php" class="btn btn-ghost-dark btn-lg">
                            <i class="fa-solid fa-user-shield text-lime"></i> Staff & Member Login
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- =================================================================
             15. ENTERPRISE FOOTER
             ================================================================= -->
        <footer class="footer-master">
            <div class="container">
                <div class="footer-grid">
                    
                    <!-- Col 1: Brand & Provider -->
                    <div>
                        <a href="<?php echo base_url('/'); ?>" class="navbar-brand" style="margin-bottom: 16px;">
                            <div class="brand-icon">
                                <i class="fa-solid fa-bolt-lightning"></i>
                            </div>
                            <div class="brand-name">
                                FITISIFY <span class="tag">OS</span>
                            </div>
                        </a>
                        <p style="font-size: 0.9rem; line-height: 1.65; color: var(--text-muted); margin-bottom: 18px;">
                            The next-generation dark-tech operating system for modern gyms and athletic chains. Proudly engineered and supported by <strong>Nexora Lab Technologies</strong>.
                        </p>
                        <div style="font-size: 0.82rem; color: var(--lime); font-weight: 700;">
                            <i class="fa-solid fa-shield-check"></i> 256-Bit SSL Encrypted & Multi-Tenant Verified
                        </div>
                    </div>

                    <!-- Col 2: Product -->
                    <div>
                        <h4 class="footer-col-title">Platform</h4>
                        <ul class="footer-links">
                            <li><a href="#features">Ecosystem Features</a></li>
                            <li><a href="#solutions">Operations Solutions</a></li>
                            <li><a href="#athlete">Member Mobile Portal</a></li>
                            <li><a href="#attendance">Smart QR Scanner</a></li>
                            <li><a href="#pricing">Subscription Pricing</a></li>
                        </ul>
                    </div>

                    <!-- Col 3: Portals -->
                    <div>
                        <h4 class="footer-col-title">Access Portals</h4>
                        <ul class="footer-links">
                            <li><a href="<?php echo base_url('/index2'); ?>">Gym Admin Login</a></li>
                            <li><a href="<?php echo base_url('/index2'); ?>">Trainer Command Hub</a></li>
                            <li><a href="<?php echo base_url('/index2'); ?>">Athlete Member Portal</a></li>
                            <li><a href="<?php echo base_url('/superadmin/index'); ?>">Superadmin Master</a></li>
                            <li><a href="<?php echo base_url('/register-gym'); ?>">Register New Gym</a></li>
                        </ul>
                    </div>

                    <!-- Col 4: Solutions -->
                    <div>
                        <h4 class="footer-col-title">Solutions</h4>
                        <ul class="footer-links">
                            <li><a href="<?php echo base_url('/register-gym'); ?>">Crossfit Studios</a></li>
                            <li><a href="<?php echo base_url('/register-gym'); ?>">Multi-Location Chains</a></li>
                            <li><a href="<?php echo base_url('/register-gym'); ?>">Martial Arts Academies</a></li>
                            <li><a href="<?php echo base_url('/register-gym'); ?>">Personal Training Clubs</a></li>
                            <li><a href="<?php echo base_url('/register-gym'); ?>">Commercial Gyms</a></li>
                        </ul>
                    </div>

                    <!-- Col 5: Company & Support -->
                    <div>
                        <h4 class="footer-col-title">Company & Dev</h4>
                        <div style="font-size: 0.88rem; color: var(--text-muted); line-height: 1.6;">
                            <strong style="color: #fff;">Nexora Lab Technologies</strong><br />
                            <i class="fa-solid fa-envelope text-lime"></i> <a href="mailto:nexoralabtechnologies@gmail.com" style="color:#a5b4fc;">nexoralabtechnologies@gmail.com</a><br />
                            <i class="fa-brands fa-whatsapp text-lime"></i> <a href="https://wa.me/918676875336?text=Hi%20Fitisify%20Team%2C%20I%20am%20interested%20in%20your%20Gym%20Management%20System." target="_blank" style="color:#a3e635; font-weight:700;">+91 8676875336</a>
                        </div>
                        <div style="display: flex; gap: 12px; font-size: 1.4rem; color: var(--text-muted); margin-top: 16px;">
                            <i class="fa-brands fa-cc-visa"></i>
                            <i class="fa-brands fa-cc-mastercard"></i>
                            <i class="fa-solid fa-building-columns"></i>
                            <i class="fa-solid fa-qrcode text-lime"></i>
                        </div>
                    </div>

                </div>

                <div class="footer-bottom-bar">
                    <div>
                        &copy; <?php echo date('Y'); ?> <strong>Nexora Lab Technologies</strong>. All rights reserved. Fitisify OS Gym Management Cloud.
                    </div>
                    <div style="display: flex; gap: 20px;">
                        <a href="<?php echo base_url('/privacy-policy'); ?>" style="color:var(--text-muted);">Privacy Policy</a>
                        <a href="<?php echo base_url('/terms-of-service'); ?>" style="color:var(--text-muted);">Terms of Service</a>
                        <a href="<?php echo base_url('/security-whitepaper'); ?>" style="color:var(--text-muted);">Security Whitepaper</a>
                    </div>
                </div>
            </div>
        </footer>

    </div>

    <!-- Floating Real Visitor Quick Lead & Demo Widget -->
    <div class="floating-visitor-widget" id="floatingVisitorWidget">
        <button type="button" class="btn-floating-lead" onclick="openLeadModal('Floating VIP Trigger')" aria-label="Book Demo">
            <span class="pulse-ring"></span>
            <i class="fa-solid fa-bolt-lightning text-lime"></i>
            <span class="btn-floating-text">Book Live Demo & Free Trial</span>
        </button>
        <a href="https://wa.me/918676875336?text=Hi%20Fitisify%20Team%2C%20I%20am%20visiting%20your%20website%20and%20want%20to%20learn%20more%20about%20your%20Gym%20Management%20System." target="_blank" class="btn-floating-wa" title="Chat on WhatsApp: +91 8676875336" aria-label="WhatsApp Support">
            <i class="fa-brands fa-whatsapp"></i>
        </a>
    </div>

    <!-- Interactive Real Visitor Lead Capture Modal -->
    <div class="visitor-lead-modal-overlay" id="visitorLeadModal">
        <div class="visitor-lead-modal-box">
            <button type="button" class="modal-close-btn" onclick="closeLeadModal()" aria-label="Close modal">&times;</button>
            
            <div class="lead-modal-header">
                <div class="badge-chip" style="margin-bottom: 8px;">
                    <span class="pulse-dot"></span> REAL-TIME CLOUD PROVISIONING
                </div>
                <h3 style="font-size: 1.5rem; font-weight: 900; color: #fff; margin-bottom: 6px;">
                    Get Your <span style="color: var(--lime);">Free 14-Day Pro Demo</span>
                </h3>
                <p style="color: var(--text-muted); font-size: 0.88rem; margin: 0;">
                    Enter your details below. Our SaaS technical consultant will activate your demo and contact you on WhatsApp / Phone immediately.
                </p>
            </div>

            <form id="visitorLeadForm" onsubmit="submitVisitorLead(event)" style="margin-top: 20px;">
                <div id="leadFormAlert" style="display: none; padding: 12px; border-radius: 8px; font-size: 0.88rem; margin-bottom: 16px;"></div>

                <div class="lead-form-grid">
                    <div class="lead-field">
                        <label for="lead_name">Your Full Name <span style="color:#ef4444;">*</span></label>
                        <input type="text" id="lead_name" name="full_name" required placeholder="e.g. Vikram Sharma" class="lead-input" />
                    </div>

                    <div class="lead-field">
                        <label for="lead_phone">Phone / WhatsApp Number <span style="color:#ef4444;">*</span></label>
                        <input type="tel" id="lead_phone" name="phone" required placeholder="e.g. 9876543210" class="lead-input" />
                    </div>

                    <div class="lead-field">
                        <label for="lead_email">Email Address</label>
                        <input type="email" id="lead_email" name="email" placeholder="e.g. vikram@fitgym.com" class="lead-input" />
                    </div>

                    <div class="lead-field">
                        <label for="lead_gym">Gym / Fitness Center Name</label>
                        <input type="text" id="lead_gym" name="gym_name" placeholder="e.g. Titan Fitness Club" class="lead-input" />
                    </div>

                    <div class="lead-field">
                        <label for="lead_city">City / Location</label>
                        <input type="text" id="lead_city" name="city" placeholder="e.g. Delhi, Mumbai, Bengaluru" class="lead-input" />
                    </div>

                    <div class="lead-field">
                        <label for="lead_plan">Interested Plan</label>
                        <select id="lead_plan" name="interested_plan" class="lead-input">
                            <option value="Pro Powerhouse (Most Popular)">Pro Powerhouse (Recommended)</option>
                            <option value="Starter Base">Starter Base</option>
                            <option value="Enterprise Chain">Enterprise Multi-Branch Chain</option>
                            <option value="Custom Enterprise Solution">Custom Enterprise Solution</option>
                        </select>
                    </div>
                </div>

                <div style="position: absolute; left: -10000px;" aria-hidden="true">
                    <input type="text" id="lead_website" name="website" tabindex="-1" autocomplete="off" />
                </div>

                <button type="submit" class="lead-submit-btn" id="btnSubmitLead">
                    <i class="fa-solid fa-paper-plane"></i>
                    <span>Activate Live Demo & Request Callback</span>
                </button>
            </form>
        </div>
    </div>

    <style>
    /* Floating Widget Styles */
    .floating-visitor-widget {
        position: fixed;
        bottom: 24px;
        right: 24px;
        display: flex;
        align-items: center;
        gap: 10px;
        z-index: 9998;
    }
    .btn-floating-lead {
        background: #0f172a;
        color: #fff;
        border: 1px solid rgba(163, 230, 53, 0.4);
        padding: 12px 20px;
        border-radius: 50px;
        font-weight: 700;
        font-size: 0.88rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 10px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.6);
        transition: all 0.25s ease;
        position: relative;
    }
    .btn-floating-lead:hover {
        border-color: #a3e635;
        transform: translateY(-2px);
        box-shadow: 0 15px 35px rgba(163, 230, 53, 0.25);
    }
    .pulse-ring {
        position: absolute;
        left: 14px;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #a3e635;
        box-shadow: 0 0 0 0 rgba(163, 230, 53, 0.7);
        animation: pulse-ring-anim 1.8s infinite cubic-bezier(0.66, 0, 0, 1);
    }
    @keyframes pulse-ring-anim {
        to {
            box-shadow: 0 0 0 12px rgba(163, 230, 53, 0);
        }
    }
    .btn-floating-wa {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: #22c55e;
        color: #fff !important;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        box-shadow: 0 10px 25px rgba(34, 197, 94, 0.4);
        transition: all 0.25s ease;
    }
    .btn-floating-wa:hover {
        transform: scale(1.1);
        background: #16a34a;
    }

    /* Modal Styles */
    .visitor-lead-modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background: rgba(0,0,0,0.8);
        backdrop-filter: blur(8px);
        z-index: 99999;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    .visitor-lead-modal-overlay.open {
        display: flex;
    }
    .visitor-lead-modal-box {
        background: #0f172a;
        border: 1px solid rgba(255,255,255,0.12);
        border-radius: 20px;
        width: 100%;
        max-width: 580px;
        padding: 30px;
        box-shadow: 0 25px 50px rgba(0,0,0,0.8);
        position: relative;
        color: #fff;
    }
    .modal-close-btn {
        position: absolute;
        top: 18px;
        right: 20px;
        background: none;
        border: none;
        color: #94a3b8;
        font-size: 1.8rem;
        cursor: pointer;
        line-height: 1;
    }
    .modal-close-btn:hover { color: #fff; }
    .lead-form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px;
    }
    @media (max-width: 600px) {
        .lead-form-grid { grid-template-columns: 1fr; }
        .btn-floating-text { display: none; }
    }
    .lead-field label {
        display: block;
        font-size: 0.78rem;
        font-weight: 700;
        color: #cbd5e1;
        margin-bottom: 4px;
        text-transform: uppercase;
    }
    .lead-input {
        width: 100%;
        background: #090d16;
        border: 1px solid rgba(255,255,255,0.12);
        border-radius: 8px;
        padding: 10px 14px;
        color: #fff;
        font-size: 0.9rem;
        outline: none;
        transition: border-color 0.2s;
    }
    .lead-input:focus {
        border-color: #a3e635;
    }
    .lead-submit-btn {
        width: 100%;
        margin-top: 20px;
        background: #a3e635;
        color: #05080d;
        border: none;
        padding: 14px;
        border-radius: 10px;
        font-weight: 800;
        font-size: 1rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        transition: all 0.2s;
        box-shadow: 0 4px 20px rgba(163, 230, 53, 0.35);
    }
    .lead-submit-btn:hover {
        background: #bef264;
        transform: translateY(-2px);
    }
    </style>

    <script>
    function openLeadModal(source) {
        document.getElementById('visitorLeadModal').classList.add('open');
    }
    function closeLeadModal() {
        document.getElementById('visitorLeadModal').classList.remove('open');
    }

    async function submitVisitorLead(e) {
        e.preventDefault();
        const btn = document.getElementById('btnSubmitLead');
        const alertBox = document.getElementById('leadFormAlert');
        const originalText = btn.innerHTML;

        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Submitting & Activating...';
        btn.disabled = true;

        const payload = {
            full_name: document.getElementById('lead_name').value,
            phone: document.getElementById('lead_phone').value,
            email: document.getElementById('lead_email').value,
            gym_name: document.getElementById('lead_gym').value,
            city: document.getElementById('lead_city').value,
            interested_plan: document.getElementById('lead_plan').value,
            website: document.getElementById('lead_website').value,
            source_page: window.location.href
        };

        try {
            const res = await fetch('<?php echo base_url('/api/capture-visitor-lead.php'); ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();

            if (data.success) {
                alertBox.style.display = 'block';
                alertBox.style.background = 'rgba(34, 197, 94, 0.15)';
                alertBox.style.border = '1px solid #22c55e';
                alertBox.style.color = '#4ade80';
                alertBox.innerHTML = '<i class="fa-solid fa-check-circle"></i> ' + data.message;
                
                document.getElementById('visitorLeadForm').reset();
                setTimeout(() => {
                    closeLeadModal();
                    alertBox.style.display = 'none';
                }, 3500);
            } else {
                alertBox.style.display = 'block';
                alertBox.style.background = 'rgba(239, 68, 68, 0.15)';
                alertBox.style.border = '1px solid #ef4444';
                alertBox.style.color = '#f87171';
                alertBox.innerHTML = '<i class="fa-solid fa-exclamation-triangle"></i> ' + (data.error || 'Failed to submit inquiry.');
            }
        } catch (err) {
            alertBox.style.display = 'block';
            alertBox.style.background = 'rgba(239, 68, 68, 0.15)';
            alertBox.style.border = '1px solid #ef4444';
            alertBox.style.color = '#f87171';
            alertBox.innerHTML = '<i class="fa-solid fa-exclamation-triangle"></i> Connection error. Please try again.';
        } finally {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    }
    </script>

    <!-- Scripts: GeoCurrency Auto-detection & Interactive Motion Controller -->
    <script src="assets/js/geo-currency.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>