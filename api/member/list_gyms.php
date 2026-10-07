<?php
/**
 * Public Directory / List of Verified Gyms for App Selector
 */

require_once __DIR__ . '/common.php';

$query = trim((string)($_GET['q'] ?? $_GET['search'] ?? ''));

$cityCol = api_column_exists('tenants', 'city');
$sql = "SELECT id, gym_code, gym_name, slug, logo, address, " . ($cityCol ? "city, " : "") . "currency, primary_color, secondary_color, status, subscription_expiry 
        FROM tenants 
        WHERE status IN ('active', 'trial') ";
$params = [];

if (!empty($query)) {
    $searchTerm = '%' . addcslashes(strtolower($query), '%_\\') . '%';
    $sql .= " AND (LOWER(gym_name) LIKE ? OR LOWER(gym_code) LIKE ? OR LOWER(slug) LIKE ?" . ($cityCol ? " OR LOWER(city) LIKE ?" : "") . ") ";
    $params = [$searchTerm, $searchTerm, $searchTerm];
    if ($cityCol) {
        $params[] = $searchTerm;
    }
}

$sql .= " ORDER BY gym_name ASC LIMIT 50";

$gyms = DB::fetchAll($sql, $params);
// Hide gyms whose SaaS subscription lapsed beyond the grace period (members could not log in anyway)
$gyms = array_values(array_filter($gyms, fn($g) => api_tenant_block_reason($g) === null));

$data = array_map(function($g) {
    $logoUrl = null;
    if (!empty($g['logo'])) {
        if (str_starts_with($g['logo'], 'http')) {
            $logoUrl = $g['logo'];
        } elseif (file_exists(__DIR__ . '/../../uploads/logos/' . $g['logo'])) {
            $logoUrl = base_url('/uploads/logos/' . $g['logo']);
        } elseif (file_exists(__DIR__ . '/../../img/' . $g['logo'])) {
            $logoUrl = base_url('/img/' . $g['logo']);
        } else {
            $logoUrl = base_url('/uploads/logos/' . $g['logo']);
        }
    }

    return [
        'id' => (int)$g['id'],
        'gym_code' => $g['gym_code'] ?: 'GYM-' . $g['id'],
        'gym_name' => $g['gym_name'],
        'slug' => $g['slug'],
        'logo' => $logoUrl,
        'address' => $g['address'] ?: '',
        'city' => $g['city'] ?? '',
        'currency' => $g['currency'] ?: '₹',
        'primary_color' => $g['primary_color'] ?: '#3b82f6',
        'secondary_color' => $g['secondary_color'] ?: '#10b981'
    ];
}, $gyms);

ApiResponse::success($data, 'Verified gyms list retrieved successfully.');
