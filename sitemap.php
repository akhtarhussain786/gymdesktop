<?php
/**
 * Dynamic XML Sitemap Generator
 * Automatically maps all public pages with timestamps and priority weights for Google & search crawlers.
 */

require_once __DIR__ . '/core/seo.php';
require_once __DIR__ . '/core/helpers.php';

header('Content-Type: application/xml; charset=utf-8');

$pages = SEO::getAllPages();
$baseUrl = rtrim(base_url('/'), '/');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9
        http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">

<?php foreach ($pages as $p): ?>
    <?php
    // Skip noindex pages from sitemap
    if (isset($p['robots_index']) && (int)$p['robots_index'] === 0) {
        continue;
    }

    $route = $p['route'] ?? '/';
    $url = $baseUrl . ($route === '/' ? '' : $route);
    $lastmod = !empty($p['updated_at']) ? date('Y-m-d', strtotime($p['updated_at'])) : date('Y-m-d');
    
    // Calculate intelligent crawl priority
    $priority = '0.8';
    $changefreq = 'weekly';
    if ($route === '/' || $route === '') {
        $priority = '1.0';
        $changefreq = 'daily';
    } elseif ($route === '/pricing' || $route === '/register-gym') {
        $priority = '0.9';
        $changefreq = 'weekly';
    } elseif ($route === '/privacy-policy' || $route === '/terms-of-service' || $route === '/security-whitepaper') {
        $priority = '0.6';
        $changefreq = 'monthly';
    }
    ?>
    <url>
        <loc><?php echo htmlspecialchars($url, ENT_XML1, 'UTF-8'); ?></loc>
        <lastmod><?php echo $lastmod; ?></lastmod>
        <changefreq><?php echo $changefreq; ?></changefreq>
        <priority><?php echo $priority; ?></priority>
    </url>
<?php endforeach; ?>

</urlset>
