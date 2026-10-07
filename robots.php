<?php
/**
 * Dynamic Robots.txt Generator
 */

require_once __DIR__ . '/core/seo.php';
require_once __DIR__ . '/core/helpers.php';

header('Content-Type: text/plain; charset=utf-8');

$settings = SEO::getSettings();
$sitemapUrl = base_url('/sitemap.xml');

$robotsContent = $settings['robots_txt_custom'] ?? "User-agent: *\nAllow: /\nDisallow: /admin/\nDisallow: /superadmin/\nDisallow: /customer/\nDisallow: /trainer/\nDisallow: /api/\n\nSitemap: %SITEMAP_URL%";

$robotsContent = str_replace('%SITEMAP_URL%', $sitemapUrl, $robotsContent);

echo trim($robotsContent) . "\n";
