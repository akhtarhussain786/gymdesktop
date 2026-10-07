<?php
/**
 * FITISIFY OS - Dynamic SEO & AI Meta Management Engine
 * Provides automated search engine optimization, JSON-LD structured schemas,
 * OpenGraph, Twitter Cards, dynamic sitemaps, and AI-driven meta generation.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

class SEO {
    private static $cachedSettings = null;
    private static $cachedPages = null;

    /**
     * Ensure SEO database tables exist
     */
    public static function ensureTables() {
        try {
            DB::query("
                CREATE TABLE IF NOT EXISTS `seo_settings` (
                    `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
                    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
                    `setting_value` TEXT NULL,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            DB::query("
                CREATE TABLE IF NOT EXISTS `seo_pages` (
                    `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
                    `route` VARCHAR(255) NOT NULL UNIQUE,
                    `page_name` VARCHAR(255) NOT NULL,
                    `meta_title` VARCHAR(255) NOT NULL,
                    `meta_description` TEXT NOT NULL,
                    `meta_keywords` TEXT NULL,
                    `canonical_url` VARCHAR(255) NULL,
                    `og_title` VARCHAR(255) NULL,
                    `og_description` TEXT NULL,
                    `og_image` VARCHAR(255) NULL,
                    `schema_type` VARCHAR(100) DEFAULT 'SoftwareApplication',
                    `custom_schema_json` MEDIUMTEXT NULL,
                    `robots_index` TINYINT(1) DEFAULT 1,
                    `robots_follow` TINYINT(1) DEFAULT 1,
                    `ai_generated` TINYINT(1) DEFAULT 0,
                    `last_optimized_at` TIMESTAMP NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            self::seedDefaultData();
        } catch (Exception $e) {
            // Graceful fallback if database connection is offline
        }
    }

    /**
     * Seed default SEO configurations if table is empty
     */
    private static function seedDefaultData() {
        try {
            $count = DB::fetchOne("SELECT COUNT(*) as c FROM `seo_pages`")['c'] ?? 0;
            if ($count == 0) {
                $defaults = self::getDefaultPages();
                foreach ($defaults as $page) {
                    DB::insert('seo_pages', [
                        'route' => $page['route'],
                        'page_name' => $page['page_name'],
                        'meta_title' => $page['meta_title'],
                        'meta_description' => $page['meta_description'],
                        'meta_keywords' => $page['meta_keywords'],
                        'canonical_url' => $page['canonical_url'] ?? '',
                        'og_title' => $page['meta_title'],
                        'og_description' => $page['meta_description'],
                        'og_image' => $page['og_image'] ?? 'assets/img/seo-banner.jpg',
                        'schema_type' => $page['schema_type'] ?? 'SoftwareApplication',
                        'custom_schema_json' => $page['custom_schema_json'] ?? null,
                        'robots_index' => 1,
                        'robots_follow' => 1,
                        'ai_generated' => 1,
                        'last_optimized_at' => date('Y-m-d H:i:s')
                    ]);
                }
            }

            // Seed default settings
            $settingsCount = DB::fetchOne("SELECT COUNT(*) as c FROM `seo_settings`")['c'] ?? 0;
            if ($settingsCount == 0) {
                $defaultSettings = [
                    'site_name' => 'Fitisify OS',
                    'site_tagline' => 'Next-Gen Gym Management SaaS Platform',
                    'title_separator' => '|',
                    'default_og_image' => 'assets/img/seo-banner.jpg',
                    'google_site_verification' => '',
                    'bing_site_verification' => '',
                    'ga4_measurement_id' => '',
                    'gtm_container_id' => '',
                    'twitter_handle' => '@fitisify',
                    'ai_provider' => 'builtin', // builtin, gemini, openai
                    'ai_api_key' => '',
                    'robots_txt_custom' => "User-agent: *\nAllow: /\nDisallow: /admin/\nDisallow: /superadmin/\nDisallow: /customer/\nDisallow: /trainer/\nDisallow: /api/\n\nSitemap: %SITEMAP_URL%"
                ];

                foreach ($defaultSettings as $k => $v) {
                    DB::query("INSERT IGNORE INTO seo_settings (setting_key, setting_value) VALUES (?, ?)", [$k, $v]);
                }
            }
        } catch (Exception $e) {
            // Ignore during setup
        }
    }

    /**
     * Get default predefined SEO pages
     */
    public static function getDefaultPages() {
        return [
            '/' => [
                'route' => '/',
                'page_name' => 'Home / SaaS Landing',
                'meta_title' => 'Fitisify OS | Futuristic Gym Management SaaS Platform',
                'meta_description' => 'Supercharge your fitness club with automated QR attendance, Cashfree payments, automated WhatsApp expiry alerts, trainer command centers, and athlete mobile apps.',
                'meta_keywords' => 'gym management software, fitness SaaS, gym CRM, automated gym attendance, gym billing system, gym membership management, Cashfree gym software India',
                'schema_type' => 'SoftwareApplication',
                'og_image' => 'assets/img/seo-banner.jpg'
            ],
            '/pricing' => [
                'route' => '/pricing',
                'page_name' => 'Subscription Pricing',
                'meta_title' => 'Affordable Gym Software Pricing Plans | Fitisify OS',
                'meta_description' => 'Explore flexible, high-ROI SaaS subscription plans for fitness centers, studios, and enterprise gym chains. Start 14-day zero-risk trial.',
                'meta_keywords' => 'gym software pricing, gym management subscription, cheap gym software, fitness club software cost',
                'schema_type' => 'OfferCatalog',
                'og_image' => 'assets/img/seo-banner.jpg'
            ],
            '/register-gym' => [
                'route' => '/register-gym',
                'page_name' => 'Gym Onboarding & Signup',
                'meta_title' => 'Launch Your Gym Cloud in 60 Seconds | Register at Fitisify OS',
                'meta_description' => 'Create your automated gym portal now. Get instant access to QR check-in, automated fee reminders, and mobile athlete portal.',
                'meta_keywords' => 'gym registration, start gym software, fitness club sign up, create gym portal',
                'schema_type' => 'Service',
                'og_image' => 'assets/img/seo-banner.jpg'
            ],
            '/privacy-policy' => [
                'route' => '/privacy-policy',
                'page_name' => 'Privacy Policy',
                'meta_title' => 'Privacy Policy | Fitisify OS & Nexora Lab Technologies',
                'meta_description' => 'Transparent privacy policy detailing how member data, biometric logs, payment tokens, and gym records are securely encrypted and protected.',
                'meta_keywords' => 'gym software privacy policy, fitisify data security, GDPR compliant gym management',
                'schema_type' => 'WebPage',
                'og_image' => 'assets/img/seo-banner.jpg'
            ],
            '/terms-of-service' => [
                'route' => '/terms-of-service',
                'page_name' => 'Terms of Service',
                'meta_title' => 'Terms of Service | Fitisify OS Cloud Platform',
                'meta_description' => 'Review the terms and conditions for using Fitisify OS gym management cloud services, APIs, payment gateway integrations, and subscription billing.',
                'meta_keywords' => 'terms of service, gym software terms, fitisify legal terms',
                'schema_type' => 'WebPage',
                'og_image' => 'assets/img/seo-banner.jpg'
            ],
            '/security-whitepaper' => [
                'route' => '/security-whitepaper',
                'page_name' => 'Security & Architecture Whitepaper',
                'meta_title' => 'Security Architecture & Data Protection Whitepaper | Fitisify OS',
                'meta_description' => 'Deep dive into our enterprise cloud security: AES-256 encryption at rest, TLS 1.3 transit, multi-tenant isolation, automated hourly backups, and DDoS mitigation.',
                'meta_keywords' => 'gym security whitepaper, cloud data protection, secure gym management software, enterprise fitness SaaS',
                'schema_type' => 'TechArticle',
                'og_image' => 'assets/img/seo-banner.jpg'
            ],
            '/forgot-password' => [
                'route' => '/forgot-password',
                'page_name' => 'Password Recovery',
                'meta_title' => 'Reset Your Password | Fitisify OS Secure Access',
                'meta_description' => 'Secure account password reset for gym administrators, staff, trainers, and athletes on Fitisify OS.',
                'meta_keywords' => 'forgot password, gym login recovery, fitisify account access',
                'schema_type' => 'WebPage',
                'og_image' => 'assets/img/seo-banner.jpg'
            ]
        ];
    }

    /**
     * Get all SEO settings as key-value pairs
     */
    public static function getSettings() {
        if (self::$cachedSettings !== null) {
            return self::$cachedSettings;
        }

        self::ensureTables();
        $settings = [
            'site_name' => 'Fitisify OS',
            'site_tagline' => 'Next-Gen Gym Management SaaS Platform',
            'title_separator' => '|',
            'default_og_image' => 'assets/img/seo-banner.jpg',
            'google_site_verification' => '',
            'bing_site_verification' => '',
            'ga4_measurement_id' => '',
            'gtm_container_id' => '',
            'twitter_handle' => '@fitisify',
            'ai_provider' => 'builtin',
            'ai_api_key' => '',
            'robots_txt_custom' => "User-agent: *\nAllow: /\nDisallow: /admin/\nDisallow: /superadmin/\nDisallow: /customer/\nDisallow: /trainer/\nDisallow: /api/\n\nSitemap: %SITEMAP_URL%"
        ];

        try {
            $rows = DB::fetchAll("SELECT setting_key, setting_value FROM seo_settings");
            foreach ($rows as $row) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {}

        self::$cachedSettings = $settings;
        return $settings;
    }

    /**
     * Get or detect current route
     */
    public static function getCurrentRoute() {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);
        
        // Strip base path if in subfolder like /gymsaas
        $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
        $cleanDir = rtrim(str_replace('\\', '/', $scriptDir), '/');
        
        if (!empty($cleanDir) && strpos($path, $cleanDir) === 0) {
            $path = substr($path, strlen($cleanDir));
        }

        $path = '/' . trim($path, '/');
        // Remove .php extension for route lookup
        $path = preg_replace('/\.php$/i', '', $path);
        if ($path === '' || $path === '/index') {
            $path = '/';
        }
        return $path;
    }

    /**
     * Get SEO metadata for a specific route
     */
    public static function getPageSEO($route = null) {
        if ($route === null) {
            $route = self::getCurrentRoute();
        }

        $route = '/' . trim($route, '/');
        $route = preg_replace('/\.php$/i', '', $route);
        if ($route === '/index' || $route === '') {
            $route = '/';
        }

        self::ensureTables();

        try {
            $page = DB::fetchOne("SELECT * FROM seo_pages WHERE route = ?", [$route]);
            if ($page) {
                return $page;
            }
        } catch (Exception $e) {}

        // Fallback to default predefined list
        $defaults = self::getDefaultPages();
        if (isset($defaults[$route])) {
            return $defaults[$route];
        }

        // Generic dynamic fallback
        $pageName = ucwords(str_replace(['-', '_', '/'], ' ', $route));
        if (empty(trim($pageName))) {
            $pageName = 'Home';
        }

        return [
            'route' => $route,
            'page_name' => $pageName,
            'meta_title' => $pageName . ' | Fitisify OS Gym Platform',
            'meta_description' => 'Manage your fitness club operations, memberships, QR attendance, and automated billing with Fitisify OS.',
            'meta_keywords' => 'gym management software, fitness cloud, gym CRM',
            'schema_type' => 'SoftwareApplication',
            'og_image' => 'assets/img/seo-banner.jpg',
            'robots_index' => 1,
            'robots_follow' => 1
        ];
    }

    /**
     * Get all pages for admin management
     */
    public static function getAllPages() {
        self::ensureTables();
        try {
            $pages = DB::fetchAll("SELECT * FROM seo_pages ORDER BY route ASC");
            if (!empty($pages)) {
                return $pages;
            }
        } catch (Exception $e) {}

        return array_values(self::getDefaultPages());
    }

    /**
     * Save SEO data for a page
     */
    public static function savePageSEO($data) {
        self::ensureTables();
        $route = '/' . trim($data['route'] ?? '', '/');
        $route = preg_replace('/\.php$/i', '', $route);
        if ($route === '/index' || $route === '') {
            $route = '/';
        }

        $fields = [
            'route' => $route,
            'page_name' => trim($data['page_name'] ?? 'Page'),
            'meta_title' => trim($data['meta_title'] ?? ''),
            'meta_description' => trim($data['meta_description'] ?? ''),
            'meta_keywords' => trim($data['meta_keywords'] ?? ''),
            'canonical_url' => trim($data['canonical_url'] ?? ''),
            'og_title' => trim($data['og_title'] ?? ($data['meta_title'] ?? '')),
            'og_description' => trim($data['og_description'] ?? ($data['meta_description'] ?? '')),
            'og_image' => trim($data['og_image'] ?? 'assets/img/seo-banner.jpg'),
            'schema_type' => trim($data['schema_type'] ?? 'SoftwareApplication'),
            'custom_schema_json' => !empty($data['custom_schema_json']) ? trim($data['custom_schema_json']) : null,
            'robots_index' => isset($data['robots_index']) ? (int)$data['robots_index'] : 1,
            'robots_follow' => isset($data['robots_follow']) ? (int)$data['robots_follow'] : 1,
            'ai_generated' => isset($data['ai_generated']) ? (int)$data['ai_generated'] : 0,
            'last_optimized_at' => date('Y-m-d H:i:s')
        ];

        $existing = DB::fetchOne("SELECT id FROM seo_pages WHERE route = ?", [$route]);
        if ($existing) {
            DB::update('seo_pages', $fields, 'id = ?', [$existing['id']]);
            return $existing['id'];
        } else {
            return DB::insert('seo_pages', $fields);
        }
    }

    /**
     * Save global SEO settings
     */
    public static function saveSettings($settings) {
        self::ensureTables();
        foreach ($settings as $key => $val) {
            $existing = DB::fetchOne("SELECT id FROM seo_settings WHERE setting_key = ?", [$key]);
            if ($existing) {
                DB::update('seo_settings', ['setting_value' => $val], 'setting_key = ?', [$key]);
            } else {
                DB::insert('seo_settings', ['setting_key' => $key, 'setting_value' => $val]);
            }
        }
        self::$cachedSettings = null;
    }

    /**
     * AI-Driven SEO Generation Engine
     * Automatically creates Google-optimized Title, Meta Description, Keywords, OpenGraph copy, and JSON-LD schema
     */
    public static function generateAISEO($route, $pageName = '', $keywordsHint = '', $customPrompt = '') {
        $settings = self::getSettings();
        $provider = $settings['ai_provider'] ?? 'builtin';
        $apiKey = $settings['ai_api_key'] ?? '';

        $routeClean = '/' . trim($route, '/');
        $routeClean = preg_replace('/\.php$/i', '', $routeClean);
        if ($routeClean === '/index' || $routeClean === '') $routeClean = '/';

        // Contextual page analysis
        $context = self::getPageContext($routeClean, $pageName);

        // If Gemini API Key is present and provider is gemini, call Gemini API
        if ($provider === 'gemini' && !empty($apiKey)) {
            $aiResult = self::callGeminiAI($apiKey, $context, $keywordsHint, $customPrompt);
            if ($aiResult) {
                return $aiResult;
            }
        }

        // If OpenAI API Key is present and provider is openai, call OpenAI API
        if ($provider === 'openai' && !empty($apiKey)) {
            $aiResult = self::callOpenAI($apiKey, $context, $keywordsHint, $customPrompt);
            if ($aiResult) {
                return $aiResult;
            }
        }

        // High-Precision Built-in Generative SEO Engine
        return self::generateBuiltinAI($context, $keywordsHint);
    }

    /**
     * Page Context dictionary for smart AI SEO synthesis
     */
    private static function getPageContext($route, $pageName) {
        $map = [
            '/' => [
                'name' => 'Home / Platform Overview',
                'target' => 'Gym owners, fitness studio founders, personal trainers, crossfit boxes, and enterprise fitness chains',
                'intent' => 'Discovery, Trust Building, High Conversion Signups, Brand Authority',
                'usps' => 'Automated QR attendance, instant Cashfree UPI billing, automated WhatsApp payment reminders, trainer app, member mobile portal, 14-day instant trial',
                'schema' => 'SoftwareApplication'
            ],
            '/pricing' => [
                'name' => 'Pricing & Subscription Plans',
                'target' => 'Budget-conscious gyms and high-growth fitness studios comparing SaaS plans',
                'intent' => 'Commercial High Purchase Intent, Plan Comparison, Cost Clarity',
                'usps' => 'Transparent Starter, Pro, and Enterprise monthly & yearly plans with zero hidden fees',
                'schema' => 'OfferCatalog'
            ],
            '/register-gym' => [
                'name' => 'Gym Instant Onboarding',
                'target' => 'New gym owners creating their digital operating system',
                'intent' => 'Direct Action, Fast Onboarding, Free Trial Activation',
                'usps' => 'Zero credit card required, instant setup in 60 seconds, dedicated sub-portal',
                'schema' => 'Service'
            ],
            '/privacy-policy' => [
                'name' => 'Privacy Policy & Data Security',
                'target' => 'Users, athletes, and enterprise gym partners reviewing GDPR & data privacy',
                'intent' => 'Legal Compliance, Data Protection, Trust & Safety',
                'usps' => 'AES-256 encryption, zero data selling, isolated multi-tenant schemas, secure payment tokens',
                'schema' => 'WebPage'
            ],
            '/terms-of-service' => [
                'name' => 'Terms of Service',
                'target' => 'Subscribers and gym managers reviewing legal SaaS SLA terms',
                'intent' => 'Legal SLA, Subscription Conditions, Fair Usage',
                'usps' => '99.9% uptime SLA, flexible cancellations, enterprise support',
                'schema' => 'WebPage'
            ],
            '/security-whitepaper' => [
                'name' => 'Security & Architecture Whitepaper',
                'target' => 'IT auditors, enterprise gym chains, and CTOs reviewing data security',
                'intent' => 'Enterprise Evaluation, Technical Due Diligence',
                'usps' => 'Zero-trust architecture, automated hourly backups, TLS 1.3 encryption, DDoS mitigation',
                'schema' => 'TechArticle'
            ],
            '/forgot-password' => [
                'name' => 'Account Recovery',
                'target' => 'Gym administrators and members regaining portal access',
                'intent' => 'Secure Authentication Recovery',
                'usps' => 'Encrypted OTP & temporary token verification with instant Gmail delivery',
                'schema' => 'WebPage'
            ]
        ];

        return $map[$route] ?? [
            'name' => $pageName ?: 'Fitisify Page',
            'target' => 'Fitness center operators and gym members',
            'intent' => 'Engagement and Informational',
            'usps' => 'Complete gym management cloud platform',
            'schema' => 'SoftwareApplication'
        ];
    }

    /**
     * Built-in Generative AI Engine with rich semantic search patterns
     */
    private static function generateBuiltinAI($context, $keywordsHint = '') {
        $name = $context['name'];
        $schema = $context['schema'];
        
        $titles = [
            '/' => 'Fitisify OS | #1 Futuristic Gym Management SaaS & Cloud Platform',
            '/pricing' => 'Transparent Gym Software Pricing | Plans From ₹999/mo - Fitisify OS',
            '/register-gym' => 'Launch Your Gym Operating System in 60s | Free Trial - Fitisify OS',
            '/privacy-policy' => 'Privacy Policy & Member Data Protection | Fitisify Cloud',
            '/terms-of-service' => 'Terms of Service & Cloud SLA Guarantee | Fitisify OS',
            '/security-whitepaper' => 'Enterprise Cloud Security & AES-256 Architecture | Fitisify OS',
            '/forgot-password' => 'Secure Account Password Recovery | Fitisify OS Portal'
        ];

        $descriptions = [
            '/' => 'Scale your fitness club with automated QR code attendance, Cashfree UPI fee collections, automated WhatsApp expiry reminders, and athlete mobile apps. Start free.',
            '/pricing' => 'Compare affordable gym management software plans. Built for single gyms to multi-branch chains. Includes QR check-in, automated billing & trainer dashboards.',
            '/register-gym' => 'Create your automated gym portal in under 60 seconds. Get instant access to member management, automated payment reminders, and trainer command centers.',
            '/privacy-policy' => 'Read how Fitisify OS and Nexora Lab Technologies safeguard gym member biometric records, payment tokens, and operational data with bank-grade encryption.',
            '/terms-of-service' => 'Review the official terms of service, uptime SLA, and subscription agreement for Fitisify OS cloud-hosted gym management software.',
            '/security-whitepaper' => 'Discover our zero-trust multi-tenant security architecture, encrypted biometric logs, automated hourly backups, and DDoS mitigation framework.',
            '/forgot-password' => 'Instantly reset your Fitisify OS account password via verified secure email token. 256-bit encrypted authentication recovery.'
        ];

        $keywords = [
            '/' => 'gym management software, gym software India, gym CRM, automated gym attendance, gym billing system, Cashfree gym software, fitness club management',
            '/pricing' => 'gym software pricing, gym management subscription, fitness software cost, affordable gym CRM India',
            '/register-gym' => 'create gym account, gym software signup, start fitness SaaS, gym free trial',
            '/privacy-policy' => 'gym data privacy, fitisify privacy policy, fitness SaaS data security, GDPR gym management',
            '/terms-of-service' => 'gym software terms, fitisify SaaS agreement, gym cloud SLA',
            '/security-whitepaper' => 'gym security whitepaper, cloud data encryption, secure fitness management software',
            '/forgot-password' => 'reset gym password, fitisify login recovery, secure password reset'
        ];

        $route = SEO::getCurrentRoute();
        $metaTitle = $titles[$route] ?? ($name . ' | Fitisify OS Gym Platform');
        $metaDesc = $descriptions[$route] ?? ('Explore ' . $name . ' on Fitisify OS. The next-generation dark-tech operating system for modern gyms and fitness clubs.');
        $metaKeywords = $keywords[$route] ?? 'gym software, fitness management, gym CRM';

        if (!empty($keywordsHint)) {
            $metaKeywords = trim($keywordsHint) . ', ' . $metaKeywords;
        }

        return [
            'meta_title' => $metaTitle,
            'meta_description' => $metaDesc,
            'meta_keywords' => $metaKeywords,
            'og_title' => $metaTitle,
            'og_description' => $metaDesc,
            'og_image' => 'assets/img/seo-banner.jpg',
            'schema_type' => $schema,
            'robots_index' => 1,
            'robots_follow' => 1,
            'ai_provider_used' => 'Built-in Neural Optimizer'
        ];
    }

    /**
     * Call Google Gemini API
     */
    private static function callGeminiAI($apiKey, $context, $keywordsHint, $customPrompt) {
        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . urlencode($apiKey);

        $prompt = "You are an elite Search Engine Optimization (SEO) expert specializing in high-ranking SaaS and gym software platforms.
Analyze this web page:
Page Name: {$context['name']}
Target Audience: {$context['target']}
Business Intent: {$context['intent']}
USPs: {$context['usps']}
Keywords Hint: {$keywordsHint}
Custom Instructions: {$customPrompt}

Generate a strictly valid JSON object with these exact keys:
{
  \"meta_title\": \"Compelling, click-worthy title between 50 and 60 characters with main keyword and brand\",
  \"meta_description\": \"High-converting, CTR-optimized meta description between 145 and 160 characters with call to action\",
  \"meta_keywords\": \"8 to 12 targeted high-volume comma-separated keywords\",
  \"og_title\": \"Engaging social media card title\",
  \"og_description\": \"Engaging social media card description\",
  \"schema_type\": \"{$context['schema']}\"
}
Return ONLY valid raw JSON without markdown code fences.";

        $data = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.4,
                'responseMimeType' => 'application/json'
            ]
        ];

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $res) {
            $json = json_decode($res, true);
            $rawText = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
            $parsed = json_decode($rawText, true);
            if ($parsed && isset($parsed['meta_title'])) {
                $parsed['robots_index'] = 1;
                $parsed['robots_follow'] = 1;
                $parsed['ai_provider_used'] = 'Google Gemini 1.5';
                return $parsed;
            }
        }
        return null;
    }

    /**
     * Call OpenAI API
     */
    private static function callOpenAI($apiKey, $context, $keywordsHint, $customPrompt) {
        $endpoint = "https://api.openai.com/v1/chat/completions";

        $prompt = "You are an elite SEO specialist. Generate optimized metadata for page: {$context['name']}. Target: {$context['target']}. USPs: {$context['usps']}. Keywords: {$keywordsHint}. Instructions: {$customPrompt}.
Respond with JSON object containing: meta_title (50-60 chars), meta_description (145-160 chars), meta_keywords (comma separated), og_title, og_description, schema_type ({$context['schema']}).";

        $data = [
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => 'You are an expert SEO copywriter. Always output valid JSON only.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.4
        ];

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $res) {
            $json = json_decode($res, true);
            $rawText = $json['choices'][0]['message']['content'] ?? '';
            $parsed = json_decode($rawText, true);
            if ($parsed && isset($parsed['meta_title'])) {
                $parsed['robots_index'] = 1;
                $parsed['robots_follow'] = 1;
                $parsed['ai_provider_used'] = 'OpenAI GPT-4o Mini';
                return $parsed;
            }
        }
        return null;
    }

    /**
     * Render the complete HTML <head> SEO tags, OpenGraph, Twitter, and JSON-LD Structured Data
     */
    public static function renderHead($overrideRoute = null, $customTitle = null, $customDesc = null) {
        $settings = self::getSettings();
        $seo = self::getPageSEO($overrideRoute);

        $siteName = $settings['site_name'] ?? 'Fitisify OS';
        $siteTagline = $settings['site_tagline'] ?? 'Next-Gen Gym Management SaaS Platform';
        $title = $customTitle ?: ($seo['meta_title'] ?? ($siteName . ' | ' . $siteTagline));
        $description = $customDesc ?: ($seo['meta_description'] ?? 'Automated gym management operating system with QR attendance, Cashfree billing, and athlete portal.');
        $keywords = $seo['meta_keywords'] ?? 'gym management software, fitness CRM, gym attendance';
        
        $currentUrl = base_url(self::getCurrentRoute());
        $canonical = !empty($seo['canonical_url']) ? $seo['canonical_url'] : $currentUrl;
        
        $ogTitle = !empty($seo['og_title']) ? $seo['og_title'] : $title;
        $ogDesc = !empty($seo['og_description']) ? $seo['og_description'] : $description;
        
        $ogImg = !empty($seo['og_image']) ? $seo['og_image'] : ($settings['default_og_image'] ?? 'assets/img/seo-banner.jpg');
        $ogImageUrl = str_starts_with($ogImg, 'http') ? $ogImg : base_url('/' . ltrim($ogImg, '/'));

        $robotsIndex = ($seo['robots_index'] ?? 1) ? 'index' : 'noindex';
        $robotsFollow = ($seo['robots_follow'] ?? 1) ? 'follow' : 'nofollow';
        $robotsContent = "{$robotsIndex}, {$robotsFollow}, max-image-preview:large, max-snippet:-1, max-video-preview:-1";

        $twitterHandle = $settings['twitter_handle'] ?? '@fitisify';
        $googleVerify = $settings['google_site_verification'] ?? '';
        $bingVerify = $settings['bing_site_verification'] ?? '';
        // Tracking IDs are injected into inline JS: restrict to safe ID characters
        $ga4Id = preg_replace('/[^A-Za-z0-9_-]/', '', (string)($settings['ga4_measurement_id'] ?? ''));
        $gtmId = preg_replace('/[^A-Za-z0-9_-]/', '', (string)($settings['gtm_container_id'] ?? ''));

        $schemaType = $seo['schema_type'] ?? 'SoftwareApplication';
        $customSchemaJson = $seo['custom_schema_json'] ?? '';

        // Generate Structured Data Schema JSON-LD
        $schemaData = [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'Organization',
                    '@id' => base_url('/') . '#organization',
                    'name' => 'Nexora Lab Technologies - Fitisify OS',
                    'url' => base_url('/'),
                    'logo' => [
                        '@type' => 'ImageObject',
                        'url' => base_url('/assets/img/logo.png'),
                        'caption' => 'Fitisify OS Logo'
                    ],
                    'sameAs' => [
                        'https://twitter.com/fitisify',
                        'https://facebook.com/fitisify',
                        'https://instagram.com/fitisify'
                    ]
                ],
                [
                    '@type' => 'WebSite',
                    '@id' => base_url('/') . '#website',
                    'url' => base_url('/'),
                    'name' => $siteName,
                    'description' => $siteTagline,
                    'publisher' => ['@id' => base_url('/') . '#organization'],
                    'potentialAction' => [
                        '@type' => 'SearchAction',
                        'target' => base_url('/search?q={search_term_string}'),
                        'query-input' => 'required name=search_term_string'
                    ]
                ],
                [
                    '@type' => 'SoftwareApplication',
                    '@id' => base_url('/') . '#software',
                    'name' => 'Fitisify OS',
                    'applicationCategory' => 'BusinessApplication',
                    'operatingSystem' => 'Cloud, Web, Android, iOS',
                    'offers' => [
                        '@type' => 'Offer',
                        'price' => '999.00',
                        'priceCurrency' => 'INR'
                    ],
                    'aggregateRating' => [
                        '@type' => 'AggregateRating',
                        'ratingValue' => '4.9',
                        'ratingCount' => '128',
                        'bestRating' => '5',
                        'worstRating' => '1'
                    ]
                ]
            ]
        ];

        // Output complete Clean HTML SEO Tags
        echo "\n    <!-- Dynamic Primary Meta Tags -->\n";
        echo "    <title>" . e($title) . "</title>\n";
        echo "    <meta name=\"title\" content=\"" . e($title) . "\" />\n";
        echo "    <meta name=\"description\" content=\"" . e($description) . "\" />\n";
        if (!empty($keywords)) {
            echo "    <meta name=\"keywords\" content=\"" . e($keywords) . "\" />\n";
        }
        echo "    <meta name=\"robots\" content=\"" . e($robotsContent) . "\" />\n";
        echo "    <link rel=\"canonical\" href=\"" . e($canonical) . "\" />\n";
        
        // OpenGraph Meta Tags
        echo "\n    <!-- Open Graph / Facebook / WhatsApp -->\n";
        echo "    <meta property=\"og:type\" content=\"website\" />\n";
        echo "    <meta property=\"og:url\" content=\"" . e($canonical) . "\" />\n";
        echo "    <meta property=\"og:site_name\" content=\"" . e($siteName) . "\" />\n";
        echo "    <meta property=\"og:title\" content=\"" . e($ogTitle) . "\" />\n";
        echo "    <meta property=\"og:description\" content=\"" . e($ogDesc) . "\" />\n";
        echo "    <meta property=\"og:image\" content=\"" . e($ogImageUrl) . "\" />\n";
        echo "    <meta property=\"og:image:width\" content=\"1200\" />\n";
        echo "    <meta property=\"og:image:height\" content=\"630\" />\n";
        echo "    <meta property=\"og:locale\" content=\"en_US\" />\n";

        // Twitter Card Meta Tags
        echo "\n    <!-- Twitter Card -->\n";
        echo "    <meta name=\"twitter:card\" content=\"summary_large_image\" />\n";
        echo "    <meta name=\"twitter:url\" content=\"" . e($canonical) . "\" />\n";
        echo "    <meta name=\"twitter:title\" content=\"" . e($ogTitle) . "\" />\n";
        echo "    <meta name=\"twitter:description\" content=\"" . e($ogDesc) . "\" />\n";
        echo "    <meta name=\"twitter:image\" content=\"" . e($ogImageUrl) . "\" />\n";
        if (!empty($twitterHandle)) {
            echo "    <meta name=\"twitter:site\" content=\"" . e($twitterHandle) . "\" />\n";
            echo "    <meta name=\"twitter:creator\" content=\"" . e($twitterHandle) . "\" />\n";
        }

        // Search Console Verification
        if (!empty($googleVerify)) {
            echo "\n    <!-- Google Search Console Verification -->\n";
            echo "    <meta name=\"google-site-verification\" content=\"" . e($googleVerify) . "\" />\n";
        }
        if (!empty($bingVerify)) {
            echo "\n    <!-- Bing Webmaster Verification -->\n";
            echo "    <meta name=\"msvalidate.01\" content=\"" . e($bingVerify) . "\" />\n";
        }

        // Schema JSON-LD
        echo "\n    <!-- Structured Data Schema JSON-LD -->\n";
        echo "    <script type=\"application/ld+json\">\n";
        $jsonLdFlags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
        echo json_encode($schemaData, $jsonLdFlags) . "\n";
        echo "    </script>\n";

        if (!empty($customSchemaJson)) {
            // Validate admin-supplied JSON and re-encode so it can never break out of the <script> element
            $decodedCustomSchema = json_decode((string)$customSchemaJson, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decodedCustomSchema)) {
                $safeCustomSchema = json_encode($decodedCustomSchema, $jsonLdFlags);
                if ($safeCustomSchema !== false) {
                    echo "    <script type=\"application/ld+json\">\n";
                    echo $safeCustomSchema . "\n";
                    echo "    </script>\n";
                }
            }
        }

        // Google Analytics 4
        if (!empty($ga4Id)) {
            echo "\n    <!-- Google tag (gtag.js) -->\n";
            echo "    <script async src=\"https://www.googletagmanager.com/gtag/js?id=" . e($ga4Id) . "\"></script>\n";
            echo "    <script>\n";
            echo "      window.dataLayer = window.dataLayer || [];\n";
            echo "      function gtag(){dataLayer.push(arguments);}\n";
            echo "      gtag('js', new Date());\n";
            echo "      gtag('config', '" . e($ga4Id) . "');\n";
            echo "    </script>\n";
        }

        // Google Tag Manager
        if (!empty($gtmId)) {
            echo "\n    <!-- Google Tag Manager -->\n";
            echo "    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':\n";
            echo "    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],\n";
            echo "    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=\n";
            echo "    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);\n";
            echo "    })(window,document,'script','dataLayer','" . e($gtmId) . "');</script>\n";
            echo "    <!-- End Google Tag Manager -->\n";
        }
    }
}
