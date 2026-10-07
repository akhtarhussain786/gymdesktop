<?php
/**
 * Superadmin SEO & AI Meta Management Console
 * Manage page-by-page SEO, automated AI generation, sitemap, and search verification tags.
 */

require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/seo.php';
require_once __DIR__ . '/../core/helpers.php';

Auth::requireAuth('super_admin');

$page = 'super_seo';
$pageTitle = 'AI SEO & Search Engine Optimization Engine';
$pageSubtitle = 'Manage automated AI metadata generation, OpenGraph tags, JSON-LD schemas, sitemap, and search console indexing.';

$message = '';
$messageType = '';

// Handle AJAX AI Generation Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_ai_seo') {
    $ajaxToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($ajaxToken) || !hash_equals($_SESSION['csrf_token'] ?? '', (string)$ajaxToken)) {
        json_response(['success' => false, 'error' => 'Invalid or expired CSRF token. Please refresh and try again.'], 403);
    }
    header('Content-Type: application/json; charset=utf-8');
    
    $route = trim($_POST['route'] ?? '/');
    $pageName = trim($_POST['page_name'] ?? '');
    $keywordsHint = trim($_POST['keywords_hint'] ?? '');
    $customPrompt = trim($_POST['custom_prompt'] ?? '');

    $result = SEO::generateAISEO($route, $pageName, $keywordsHint, $customPrompt);
    
    if ($result) {
        echo json_encode(['success' => true, 'data' => $result]);
    } else {
        echo json_encode(['success' => false, 'error' => 'AI generation failed. Please try again.']);
    }
    exit();
}

// Handle Batch Auto-Optimize All Pages with AI
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['batch_optimize_all'])) {
    Auth::verifyCsrf();
    
    $allPages = SEO::getAllPages();
    $count = 0;
    
    foreach ($allPages as $p) {
        $aiData = SEO::generateAISEO($p['route'], $p['page_name'] ?? '');
        if ($aiData) {
            $aiData['route'] = $p['route'];
            $aiData['page_name'] = $p['page_name'] ?? 'Page';
            $aiData['ai_generated'] = 1;
            SEO::savePageSEO($aiData);
            $count++;
        }
    }

    Auth::auditLog('SEO_BATCH_OPTIMIZE', "Super Admin auto-optimized {$count} pages with AI SEO");
    redirect(base_url('/superadmin/seo-settings'), 'success', "Successfully optimized {$count} pages using AI SEO Engine!");
}

// Handle Save Individual Page SEO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_page_seo'])) {
    Auth::verifyCsrf();

    $pageData = [
        'route' => $_POST['route'] ?? '/',
        'page_name' => $_POST['page_name'] ?? 'Page',
        'meta_title' => $_POST['meta_title'] ?? '',
        'meta_description' => $_POST['meta_description'] ?? '',
        'meta_keywords' => $_POST['meta_keywords'] ?? '',
        'canonical_url' => $_POST['canonical_url'] ?? '',
        'og_title' => $_POST['og_title'] ?? '',
        'og_description' => $_POST['og_description'] ?? '',
        'og_image' => $_POST['og_image'] ?? '',
        'schema_type' => $_POST['schema_type'] ?? 'SoftwareApplication',
        'custom_schema_json' => $_POST['custom_schema_json'] ?? '',
        'robots_index' => isset($_POST['robots_index']) ? 1 : 0,
        'robots_follow' => isset($_POST['robots_follow']) ? 1 : 0,
        'ai_generated' => isset($_POST['is_ai_generated']) ? 1 : 0
    ];

    SEO::savePageSEO($pageData);
    Auth::auditLog('SEO_PAGE_UPDATE', "Updated SEO metadata for route: {$pageData['route']}");
    redirect(base_url('/superadmin/seo-settings'), 'success', "SEO metadata for '{$pageData['route']}' saved successfully!");
}

// Handle Save Global SEO Settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_global_seo'])) {
    Auth::verifyCsrf();

    $globalSettings = [
        'site_name' => trim($_POST['site_name'] ?? 'Fitisify OS'),
        'site_tagline' => trim($_POST['site_tagline'] ?? ''),
        'title_separator' => trim($_POST['title_separator'] ?? '|'),
        'default_og_image' => trim($_POST['default_og_image'] ?? 'assets/img/seo-banner.jpg'),
        'google_site_verification' => trim($_POST['google_site_verification'] ?? ''),
        'bing_site_verification' => trim($_POST['bing_site_verification'] ?? ''),
        'ga4_measurement_id' => trim($_POST['ga4_measurement_id'] ?? ''),
        'gtm_container_id' => trim($_POST['gtm_container_id'] ?? ''),
        'twitter_handle' => trim($_POST['twitter_handle'] ?? ''),
        'ai_provider' => trim($_POST['ai_provider'] ?? 'builtin'),
        'ai_api_key' => trim($_POST['ai_api_key'] ?? ''),
        'robots_txt_custom' => trim($_POST['robots_txt_custom'] ?? '')
    ];

    SEO::saveSettings($globalSettings);
    Auth::auditLog('SEO_GLOBAL_UPDATE', "Super Admin updated global SEO and verification settings");
    redirect(base_url('/superadmin/seo-settings'), 'success', 'Global SEO and Verification settings updated successfully.');
}

$seoSettings = SEO::getSettings();
$pagesList = SEO::getAllPages();
$csrfToken = Auth::csrfToken();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/topbar.php';
?>

<style>
/* Modern Dark Glassmorphism SEO Console Styling */
.seo-header-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 16px;
}
.seo-tabs {
    display: flex;
    gap: 8px;
    background: var(--bg-card, #1e293b);
    padding: 6px;
    border-radius: 12px;
    border: 1px solid var(--border-color, rgba(255,255,255,0.08));
    margin-bottom: 24px;
    overflow-x: auto;
}
.seo-tab-btn {
    padding: 10px 20px;
    border-radius: 8px;
    background: transparent;
    border: none;
    color: var(--text-muted, #94a3b8);
    font-weight: 600;
    font-size: 0.9rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease;
    white-space: nowrap;
}
.seo-tab-btn:hover {
    color: #fff;
    background: rgba(255,255,255,0.05);
}
.seo-tab-btn.active {
    background: var(--primary, #a3e635);
    color: #000;
    font-weight: 700;
    box-shadow: 0 4px 12px rgba(163, 230, 53, 0.25);
}
.seo-tab-content {
    display: none;
}
.seo-tab-content.active {
    display: block;
}

/* Live SERP Google Preview Card */
.serp-preview {
    background: #202124;
    border-radius: 12px;
    padding: 20px;
    border: 1px solid #3c4043;
    margin-bottom: 24px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.3);
}
.serp-preview-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 12px;
    font-size: 0.8rem;
    color: #9aa0a6;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.serp-preview-url {
    font-size: 0.85rem;
    color: #bdc1c6;
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 6px;
}
.serp-preview-title {
    font-size: 1.25rem;
    font-weight: 500;
    color: #8ab4f8;
    text-decoration: none;
    line-height: 1.3;
    margin-bottom: 6px;
    cursor: pointer;
}
.serp-preview-title:hover {
    text-decoration: underline;
}
.serp-preview-desc {
    font-size: 0.9rem;
    color: #bdc1c6;
    line-height: 1.5;
}

/* Modal Styling */
.seo-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(0, 0, 0, 0.75);
    backdrop-filter: blur(6px);
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.seo-modal-overlay.open {
    display: flex;
}
.seo-modal-box {
    background: #0f172a;
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 16px;
    width: 100%;
    max-width: 850px;
    max-height: 90vh;
    overflow-y: auto;
    padding: 30px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7);
    color: #f8fafc;
}
.seo-counter {
    font-size: 0.78rem;
    font-weight: 600;
    float: right;
    margin-top: 4px;
}
.counter-good { color: #10b981; }
.counter-warn { color: #f59e0b; }
.counter-bad { color: #ef4444; }

.ai-pulse-btn {
    background: linear-gradient(135deg, #a855f7 0%, #6366f1 100%);
    color: #fff;
    border: none;
    border-radius: 8px;
    padding: 10px 18px;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    box-shadow: 0 4px 15px rgba(168, 85, 247, 0.35);
    transition: all 0.25s ease;
}
.ai-pulse-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(168, 85, 247, 0.5);
}
</style>

<div class="seo-header-actions">
    <div>
        <h2 style="font-size: 1.6rem; font-weight: 800; color: #fff; display: flex; align-items: center; gap: 12px;">
            <i class="fas fa-robot" style="color: var(--primary);"></i>
            <span>AI Automated SEO & Meta Optimization Console</span>
        </h2>
        <p style="color: var(--text-muted); font-size: 0.95rem; margin-top: 4px;">
            Clean Extensionless URLs Active &middot; Dynamic XML Sitemap &middot; Automated AI Schema & Social Cards
        </p>
    </div>
    
    <div style="display: flex; gap: 12px;">
        <form method="POST" style="margin: 0;" onsubmit="return confirm('Do you want to run AI Auto-Optimization across all website pages? This will analyze each page and update titles, descriptions, and schemas.');">
            <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
            <button type="submit" name="batch_optimize_all" class="ai-pulse-btn">
                <i class="fas fa-magic"></i>
                <span>Auto-Optimize All Pages with AI</span>
            </button>
        </form>
        <a href="<?php echo base_url('/sitemap.xml'); ?>" target="_blank" class="btn btn-outline" style="border-color: rgba(255,255,255,0.2); color: #fff;">
            <i class="fas fa-sitemap"></i> View XML Sitemap
        </a>
    </div>
</div>

<!-- Tabs Navigation -->
<div class="seo-tabs">
    <button class="seo-tab-btn active" onclick="switchTab('tab-pages')">
        <i class="fas fa-file-code"></i> Page SEO & AI Optimizer (<?php echo count($pagesList); ?>)
    </button>
    <button class="seo-tab-btn" onclick="switchTab('tab-global')">
        <i class="fas fa-globe"></i> Global Meta & Verification
    </button>
    <button class="seo-tab-btn" onclick="switchTab('tab-ai-engine')">
        <i class="fas fa-brain"></i> AI Engine Settings
    </button>
    <button class="seo-tab-btn" onclick="switchTab('tab-sitemap')">
        <i class="fas fa-network-wired"></i> Sitemap & Robots.txt
    </button>
</div>

<!-- TAB 1: Page-by-Page SEO & AI Optimizer -->
<div id="tab-pages" class="seo-tab-content active">
    <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <div class="card-title">
                <i class="fas fa-search"></i>
                <span>Target Website Routes & Meta Configurations</span>
            </div>
            <button class="btn btn-primary btn-sm" onclick="openNewPageModal()">
                <i class="fas fa-plus"></i> Add Custom Route SEO
            </button>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="table" style="margin-bottom: 0;">
                    <thead>
                        <tr>
                            <th style="width: 220px;">Page & Clean Route</th>
                            <th>Meta Title & Snippet Preview</th>
                            <th style="width: 140px;">Schema Type</th>
                            <th style="width: 120px;">AI Status</th>
                            <th style="width: 110px;">Indexing</th>
                            <th style="width: 140px; text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pagesList as $p): ?>
                            <?php
                            $routeClean = $p['route'] ?? '/';
                            $titleLen = mb_strlen($p['meta_title'] ?? '');
                            $descLen = mb_strlen($p['meta_description'] ?? '');
                            ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 700; color: #fff; font-size: 0.95rem;">
                                        <?php echo e($p['page_name'] ?? 'Page'); ?>
                                    </div>
                                    <div style="margin-top: 4px;">
                                        <a href="<?php echo base_url($routeClean); ?>" target="_blank" style="color: var(--primary); font-size: 0.82rem; font-family: monospace; text-decoration: none;">
                                            <i class="fas fa-external-link-alt" style="font-size: 0.75rem;"></i> <?php echo e($routeClean); ?>
                                        </a>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 600; color: #e2e8f0; font-size: 0.92rem; line-height: 1.4;">
                                        <?php echo e($p['meta_title'] ?? '—'); ?>
                                    </div>
                                    <div style="font-size: 0.82rem; color: var(--text-muted); margin-top: 4px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                        <?php echo e($p['meta_description'] ?? '—'); ?>
                                    </div>
                                    <div style="display: flex; gap: 12px; margin-top: 6px; font-size: 0.76rem;">
                                        <span class="<?php echo ($titleLen >= 45 && $titleLen <= 65) ? 'counter-good' : 'counter-warn'; ?>">
                                            Title: <?php echo $titleLen; ?> chars
                                        </span>
                                        <span class="<?php echo ($descLen >= 120 && $descLen <= 165) ? 'counter-good' : 'counter-warn'; ?>">
                                            Desc: <?php echo $descLen; ?> chars
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge" style="background: rgba(99, 102, 241, 0.15); color: #818cf8; border: 1px solid rgba(99, 102, 241, 0.3); font-size: 0.78rem;">
                                        <i class="fas fa-code"></i> <?php echo e($p['schema_type'] ?? 'SoftwareApplication'); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($p['ai_generated'])): ?>
                                        <span class="badge" style="background: rgba(168, 85, 247, 0.15); color: #c084fc; border: 1px solid rgba(168, 85, 247, 0.3); font-size: 0.78rem;">
                                            <i class="fas fa-sparkles"></i> AI Generated
                                        </span>
                                    <?php else: ?>
                                        <span class="badge" style="background: rgba(255,255,255,0.06); color: var(--text-muted); font-size: 0.78rem;">
                                            Manual
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!isset($p['robots_index']) || $p['robots_index'] == 1): ?>
                                        <span class="status-badge badge-success"><i class="fas fa-check"></i> Index</span>
                                    <?php else: ?>
                                        <span class="status-badge badge-danger"><i class="fas fa-ban"></i> Noindex</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right;">
                                    <button type="button" class="btn btn-sm btn-outline" style="border-color: rgba(255,255,255,0.2); color: #fff;" onclick="editPageSEO(<?php echo htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8'); ?>)">
                                        <i class="fas fa-magic" style="color: #a855f7;"></i> Edit & AI
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- TAB 2: Global SEO & Verification -->
<div id="tab-global" class="seo-tab-content">
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <i class="fas fa-sliders-h"></i>
                <span>Global Site Identity, Search Console & Analytics Tracking</span>
            </div>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; margin-bottom: 24px;">
                    <div class="form-group">
                        <label class="form-label">Global Site Name</label>
                        <input type="text" name="site_name" class="form-control" value="<?php echo e($seoSettings['site_name'] ?? 'Fitisify OS'); ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Platform Tagline / Slogan</label>
                        <input type="text" name="site_tagline" class="form-control" value="<?php echo e($seoSettings['site_tagline'] ?? 'Next-Gen Gym Management SaaS Platform'); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Title Separator</label>
                        <input type="text" name="title_separator" class="form-control" value="<?php echo e($seoSettings['title_separator'] ?? '|'); ?>" style="max-width: 120px;">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Twitter / X Handle (e.g. @fitisify)</label>
                        <input type="text" name="twitter_handle" class="form-control" value="<?php echo e($seoSettings['twitter_handle'] ?? '@fitisify'); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Default OpenGraph Share Banner (1200x630px)</label>
                        <input type="text" name="default_og_image" class="form-control" value="<?php echo e($seoSettings['default_og_image'] ?? 'assets/img/seo-banner.jpg'); ?>">
                    </div>
                </div>

                <hr style="border-color: rgba(255,255,255,0.08); margin: 30px 0;">

                <h4 style="font-size: 1.1rem; color: #fff; font-weight: 700; margin-bottom: 16px;">
                    <i class="fas fa-shield-check" style="color: var(--primary);"></i> Search Engine Webmaster Verifications
                </h4>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; margin-bottom: 24px;">
                    <div class="form-group">
                        <label class="form-label">Google Search Console Verification Code</label>
                        <input type="text" name="google_site_verification" class="form-control" placeholder="e.g. d_yJqK2xP8Wq..." value="<?php echo e($seoSettings['google_site_verification'] ?? ''); ?>">
                        <small style="color: var(--text-muted); font-size: 0.78rem;">Adds <code>&lt;meta name="google-site-verification" content="..." /&gt;</code> to every page header.</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Bing Webmaster Tools Verification Code</label>
                        <input type="text" name="bing_site_verification" class="form-control" placeholder="e.g. 7A1F2C..." value="<?php echo e($seoSettings['bing_site_verification'] ?? ''); ?>">
                        <small style="color: var(--text-muted); font-size: 0.78rem;">Adds <code>&lt;meta name="msvalidate.01" content="..." /&gt;</code> for Bing / Yahoo indexing.</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Google Analytics 4 (GA4) Measurement ID</label>
                        <input type="text" name="ga4_measurement_id" class="form-control" placeholder="G-XXXXXXXXXX" value="<?php echo e($seoSettings['ga4_measurement_id'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Google Tag Manager (GTM) Container ID</label>
                        <input type="text" name="gtm_container_id" class="form-control" placeholder="GTM-XXXXXXX" value="<?php echo e($seoSettings['gtm_container_id'] ?? ''); ?>">
                    </div>
                </div>

                <div style="text-align: right; margin-top: 24px;">
                    <button type="submit" name="save_global_seo" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Global Settings
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- TAB 3: AI Engine Settings -->
<div id="tab-ai-engine" class="seo-tab-content">
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <i class="fas fa-brain" style="color: #a855f7;"></i>
                <span>Generative AI Provider & API Settings</span>
            </div>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
                
                <div style="background: rgba(168, 85, 247, 0.08); border: 1px solid rgba(168, 85, 247, 0.25); border-radius: 12px; padding: 20px; margin-bottom: 24px;">
                    <h4 style="color: #c084fc; font-size: 1rem; font-weight: 700; margin-bottom: 8px;">
                        <i class="fas fa-info-circle"></i> High-Intent AI Search Optimization
                    </h4>
                    <p style="color: #cbd5e1; font-size: 0.88rem; line-height: 1.6; margin: 0;">
                        The built-in neural SEO generator is fully functional without requiring any external API keys. If you provide a Google Gemini or OpenAI API key, the engine will leverage real-time LLM reasoning to craft bespoke CTR-optimized headlines, meta tags, and structured schemas.
                    </p>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; margin-bottom: 24px;">
                    <div class="form-group">
                        <label class="form-label">AI Optimization Provider</label>
                        <select name="ai_provider" class="form-control">
                            <option value="builtin" <?php echo ($seoSettings['ai_provider'] ?? '') === 'builtin' ? 'selected' : ''; ?>>Built-in Neural Optimizer (Zero API Cost, Fast & Local)</option>
                            <option value="gemini" <?php echo ($seoSettings['ai_provider'] ?? '') === 'gemini' ? 'selected' : ''; ?>>Google Gemini 1.5 Flash (Recommended for Advanced AI)</option>
                            <option value="openai" <?php echo ($seoSettings['ai_provider'] ?? '') === 'openai' ? 'selected' : ''; ?>>OpenAI GPT-4o Mini</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">API Key (Gemini API Key or OpenAI API Key)</label>
                        <input type="password" name="ai_api_key" class="form-control" placeholder="AIzaSy... or sk-..." value="<?php echo e($seoSettings['ai_api_key'] ?? ''); ?>">
                        <small style="color: var(--text-muted); font-size: 0.78rem;">Get your free Google Gemini API key from <a href="https://aistudio.google.com/" target="_blank" style="color: var(--primary);">Google AI Studio</a>.</small>
                    </div>
                </div>

                <div style="text-align: right; margin-top: 24px;">
                    <button type="submit" name="save_global_seo" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save AI Engine Settings
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- TAB 4: Sitemap & Robots.txt -->
<div id="tab-sitemap" class="seo-tab-content">
    <div class="card" style="margin-bottom: 24px;">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <div class="card-title">
                <i class="fas fa-sitemap" style="color: var(--primary);"></i>
                <span>Automated XML Sitemap Status</span>
            </div>
            <a href="<?php echo base_url('/sitemap.xml'); ?>" target="_blank" class="btn btn-sm btn-primary">
                <i class="fas fa-external-link-alt"></i> Open Live /sitemap.xml
            </a>
        </div>
        <div class="card-body">
            <p style="color: #cbd5e1; font-size: 0.9rem; line-height: 1.6;">
                Your XML Sitemap is dynamically generated at <code><?php echo base_url('/sitemap.xml'); ?></code>. It dynamically indexes all active clean URLs, priorities, and update timestamps.
            </p>
            <div style="background: #090d16; padding: 16px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.08); font-family: monospace; font-size: 0.85rem; color: #a3e635;">
                Sitemap URL: <?php echo base_url('/sitemap.xml'); ?><br>
                Total Indexed Pages: <?php echo count($pagesList); ?> URLs<br>
                Search Engine Protocol: sitemaps.org/schemas/sitemap/0.9
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <i class="fas fa-robot"></i>
                <span>Robots.txt Directive Editor</span>
            </div>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
                
                <div class="form-group">
                    <label class="form-label">Robots.txt File Contents (served at <code>/robots.txt</code>)</label>
                    <textarea name="robots_txt_custom" class="form-control" rows="8" style="font-family: monospace; font-size: 0.9rem; background: #090d16; color: #38bdf8;"><?php echo e($seoSettings['robots_txt_custom'] ?? "User-agent: *\nAllow: /\nDisallow: /admin/\nDisallow: /superadmin/\nDisallow: /customer/\nDisallow: /trainer/\nDisallow: /api/\n\nSitemap: %SITEMAP_URL%"); ?></textarea>
                    <small style="color: var(--text-muted); font-size: 0.78rem;"><code>%SITEMAP_URL%</code> will be dynamically replaced with <code><?php echo base_url('/sitemap.xml'); ?></code>.</small>
                </div>

                <div style="text-align: right; margin-top: 20px;">
                    <button type="submit" name="save_global_seo" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Robots.txt
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit / AI Generate Individual Page SEO -->
<div id="seoEditModal" class="seo-modal-overlay">
    <div class="seo-modal-box">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="font-size: 1.3rem; font-weight: 800; color: #fff; display: flex; align-items: center; gap: 10px; margin: 0;">
                <i class="fas fa-magic" style="color: #a855f7;"></i>
                <span id="modalTitle">Edit Page SEO</span>
            </h3>
            <button type="button" onclick="closeModal()" style="background: none; border: none; color: #94a3b8; font-size: 1.3rem; cursor: pointer;">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Live Google SERP Preview Card -->
        <div class="serp-preview">
            <div class="serp-preview-header">
                <i class="fab fa-google" style="color: #4285f4;"></i> Live Google Search Result Preview
            </div>
            <div class="serp-preview-url">
                <span style="color: #9aa0a6;">https://</span><span id="previewDomain"><?php echo parse_url(base_url(), PHP_URL_HOST) ?: 'fitisify.com'; ?></span><span id="previewRoute" style="color: #8ab4f8;">/</span>
            </div>
            <div class="serp-preview-title" id="previewTitle">Fitisify OS | Futuristic Gym Management SaaS Platform</div>
            <div class="serp-preview-desc" id="previewDesc">Supercharge your fitness club with automated QR attendance, Cashfree payments, automated WhatsApp expiry alerts, and athlete mobile apps.</div>
        </div>

        <!-- AI Assistant Action Bar -->
        <div style="background: rgba(168, 85, 247, 0.12); border: 1px solid rgba(168, 85, 247, 0.3); border-radius: 12px; padding: 16px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
            <div>
                <div style="font-weight: 700; color: #e9d5ff; font-size: 0.95rem;">
                    <i class="fas fa-wand-magic-sparkles"></i> One-Click AI Metadata Assistant
                </div>
                <div style="font-size: 0.8rem; color: #cbd5e1; margin-top: 2px;">
                    Auto-synthesizes optimal search titles, click-optimized descriptions, and keywords.
                </div>
            </div>
            <button type="button" class="ai-pulse-btn" id="btnRunAI" onclick="runSinglePageAI()">
                <i class="fas fa-sparkles"></i> <span>Generate with AI</span>
            </button>
        </div>

        <form method="POST" id="pageSeoForm">
            <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
            <input type="hidden" name="is_ai_generated" id="inputIsAi" value="0">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                <div class="form-group">
                    <label class="form-label">Clean URL Route</label>
                    <input type="text" name="route" id="inputRoute" class="form-control" required placeholder="/pricing" style="font-family: monospace;">
                </div>
                <div class="form-group">
                    <label class="form-label">Page Display Name</label>
                    <input type="text" name="page_name" id="inputPageName" class="form-control" required placeholder="Pricing Plans">
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 16px;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <label class="form-label">Meta Title</label>
                    <span id="titleCounter" class="seo-counter counter-good">0 / 60 chars</span>
                </div>
                <input type="text" name="meta_title" id="inputMetaTitle" class="form-control" required oninput="updateSERPPreview()">
            </div>

            <div class="form-group" style="margin-bottom: 16px;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <label class="form-label">Meta Description</label>
                    <span id="descCounter" class="seo-counter counter-good">0 / 160 chars</span>
                </div>
                <textarea name="meta_description" id="inputMetaDescription" class="form-control" rows="3" required oninput="updateSERPPreview()"></textarea>
            </div>

            <div class="form-group" style="margin-bottom: 16px;">
                <label class="form-label">Focus & Meta Keywords (comma separated)</label>
                <input type="text" name="meta_keywords" id="inputMetaKeywords" class="form-control" placeholder="gym software, automated billing, QR attendance">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                <div class="form-group">
                    <label class="form-label">Structured Schema Type</label>
                    <select name="schema_type" id="inputSchemaType" class="form-control">
                        <option value="SoftwareApplication">SoftwareApplication (SaaS Platform)</option>
                        <option value="OfferCatalog">OfferCatalog (Pricing & Plans)</option>
                        <option value="Service">Service (Gym Onboarding / Signups)</option>
                        <option value="Organization">Organization (Company Info)</option>
                        <option value="WebPage">WebPage (General Content)</option>
                        <option value="TechArticle">TechArticle (Whitepaper / Security)</option>
                        <option value="FAQPage">FAQPage (Frequently Asked Questions)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Custom OpenGraph Banner</label>
                    <input type="text" name="og_image" id="inputOgImage" class="form-control" placeholder="assets/img/seo-banner.jpg">
                </div>
            </div>

            <div style="display: flex; gap: 24px; margin-bottom: 24px;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; color: #fff; font-size: 0.9rem;">
                    <input type="checkbox" name="robots_index" id="inputRobotsIndex" value="1" checked>
                    <span>Allow Search Engines to Index this Page (index)</span>
                </label>
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; color: #fff; font-size: 0.9rem;">
                    <input type="checkbox" name="robots_follow" id="inputRobotsFollow" value="1" checked>
                    <span>Follow Links on this Page (follow)</span>
                </label>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 12px;">
                <button type="button" class="btn btn-outline" onclick="closeModal()">Cancel</button>
                <button type="submit" name="save_page_seo" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Page SEO
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function switchTab(tabId) {
    document.querySelectorAll('.seo-tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.seo-tab-content').forEach(content => content.classList.remove('active'));
    
    event.currentTarget.classList.add('active');
    document.getElementById(tabId).classList.add('active');
}

function openNewPageModal() {
    document.getElementById('modalTitle').innerText = 'Add Custom Route SEO';
    document.getElementById('inputRoute').value = '';
    document.getElementById('inputRoute').removeAttribute('readonly');
    document.getElementById('inputPageName').value = '';
    document.getElementById('inputMetaTitle').value = '';
    document.getElementById('inputMetaDescription').value = '';
    document.getElementById('inputMetaKeywords').value = '';
    document.getElementById('inputSchemaType').value = 'WebPage';
    document.getElementById('inputOgImage').value = 'assets/img/seo-banner.jpg';
    document.getElementById('inputRobotsIndex').checked = true;
    document.getElementById('inputRobotsFollow').checked = true;
    document.getElementById('inputIsAi').value = '0';
    updateSERPPreview();
    document.getElementById('seoEditModal').classList.add('open');
}

function editPageSEO(page) {
    document.getElementById('modalTitle').innerText = 'Edit SEO: ' + page.route;
    document.getElementById('inputRoute').value = page.route;
    document.getElementById('inputPageName').value = page.page_name || '';
    document.getElementById('inputMetaTitle').value = page.meta_title || '';
    document.getElementById('inputMetaDescription').value = page.meta_description || '';
    document.getElementById('inputMetaKeywords').value = page.meta_keywords || '';
    document.getElementById('inputSchemaType').value = page.schema_type || 'SoftwareApplication';
    document.getElementById('inputOgImage').value = page.og_image || 'assets/img/seo-banner.jpg';
    document.getElementById('inputRobotsIndex').checked = (!page.robots_index || page.robots_index == 1);
    document.getElementById('inputRobotsFollow').checked = (!page.robots_follow || page.robots_follow == 1);
    document.getElementById('inputIsAi').value = page.ai_generated || '0';
    updateSERPPreview();
    document.getElementById('seoEditModal').classList.add('open');
}

function closeModal() {
    document.getElementById('seoEditModal').classList.remove('open');
}

function updateSERPPreview() {
    const route = document.getElementById('inputRoute').value || '/';
    const title = document.getElementById('inputMetaTitle').value || 'Title preview';
    const desc = document.getElementById('inputMetaDescription').value || 'Description preview';
    
    document.getElementById('previewRoute').innerText = route;
    document.getElementById('previewTitle').innerText = title;
    document.getElementById('previewDesc').innerText = desc;

    // Title counter
    const tLen = title.length;
    const tCounter = document.getElementById('titleCounter');
    tCounter.innerText = tLen + ' / 60 chars';
    if (tLen >= 45 && tLen <= 65) {
        tCounter.className = 'seo-counter counter-good';
    } else if (tLen < 45) {
        tCounter.className = 'seo-counter counter-warn';
    } else {
        tCounter.className = 'seo-counter counter-bad';
    }

    // Desc counter
    const dLen = desc.length;
    const dCounter = document.getElementById('descCounter');
    dCounter.innerText = dLen + ' / 160 chars';
    if (dLen >= 120 && dLen <= 165) {
        dCounter.className = 'seo-counter counter-good';
    } else if (dLen < 120) {
        dCounter.className = 'seo-counter counter-warn';
    } else {
        dCounter.className = 'seo-counter counter-bad';
    }
}

async function runSinglePageAI() {
    const btn = document.getElementById('btnRunAI');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Synthesizing SEO...';
    btn.disabled = true;

    const route = document.getElementById('inputRoute').value;
    const pageName = document.getElementById('inputPageName').value;
    const keywordsHint = document.getElementById('inputMetaKeywords').value;

    try {
        const formData = new FormData();
        formData.append('action', 'generate_ai_seo');
        formData.append('csrf_token', <?php echo json_encode($csrfToken, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>);
        formData.append('route', route);
        formData.append('page_name', pageName);
        formData.append('keywords_hint', keywordsHint);

        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });

        const res = await response.json();
        if (res.success && res.data) {
            document.getElementById('inputMetaTitle').value = res.data.meta_title || '';
            document.getElementById('inputMetaDescription').value = res.data.meta_description || '';
            document.getElementById('inputMetaKeywords').value = res.data.meta_keywords || '';
            if (res.data.schema_type) {
                document.getElementById('inputSchemaType').value = res.data.schema_type;
            }
            document.getElementById('inputIsAi').value = '1';
            updateSERPPreview();
        } else {
            alert('AI Generation error: ' + (res.error || 'Failed to generate metadata'));
        }
    } catch (e) {
        alert('Network or server error while calling AI generator.');
    } finally {
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
