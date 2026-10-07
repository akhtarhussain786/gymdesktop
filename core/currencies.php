<?php
/**
 * Centralized Currency & Timezone Data for Location-Based Auto-Detection
 * Used by: register-gym.php, admin/settings.php, core/helpers.php
 */

/**
 * Get all supported currencies with their symbols, country codes, and default timezones
 */
function get_supported_currencies() {
    return [
        ['code' => 'INR', 'symbol' => '₹',    'name' => 'Indian Rupee',        'country' => 'IN', 'timezone' => 'Asia/Kolkata'],
        ['code' => 'USD', 'symbol' => '$',    'name' => 'US Dollar',           'country' => 'US', 'timezone' => 'America/New_York'],
        ['code' => 'GBP', 'symbol' => '£',    'name' => 'British Pound',       'country' => 'GB', 'timezone' => 'Europe/London'],
        ['code' => 'EUR', 'symbol' => '€',    'name' => 'Euro',                'country' => 'EU', 'timezone' => 'Europe/Paris'],
        ['code' => 'NPR', 'symbol' => 'Rs. ', 'name' => 'Nepalese Rupee',      'country' => 'NP', 'timezone' => 'Asia/Kathmandu'],
        ['code' => 'AUD', 'symbol' => 'A$',   'name' => 'Australian Dollar',   'country' => 'AU', 'timezone' => 'Australia/Sydney'],
        ['code' => 'CAD', 'symbol' => 'C$',   'name' => 'Canadian Dollar',     'country' => 'CA', 'timezone' => 'America/Toronto'],
        ['code' => 'AED', 'symbol' => 'د.إ',  'name' => 'UAE Dirham',          'country' => 'AE', 'timezone' => 'Asia/Dubai'],
        ['code' => 'SAR', 'symbol' => '﷼',    'name' => 'Saudi Riyal',         'country' => 'SA', 'timezone' => 'Asia/Riyadh'],
        ['code' => 'PKR', 'symbol' => 'Rs ',   'name' => 'Pakistani Rupee',     'country' => 'PK', 'timezone' => 'Asia/Karachi'],
        ['code' => 'BDT', 'symbol' => '৳',    'name' => 'Bangladeshi Taka',    'country' => 'BD', 'timezone' => 'Asia/Dhaka'],
        ['code' => 'LKR', 'symbol' => 'Rs ',   'name' => 'Sri Lankan Rupee',    'country' => 'LK', 'timezone' => 'Asia/Colombo'],
        ['code' => 'JPY', 'symbol' => '¥',    'name' => 'Japanese Yen',        'country' => 'JP', 'timezone' => 'Asia/Tokyo'],
        ['code' => 'CNY', 'symbol' => '¥',    'name' => 'Chinese Yuan',        'country' => 'CN', 'timezone' => 'Asia/Shanghai'],
        ['code' => 'KRW', 'symbol' => '₩',    'name' => 'South Korean Won',    'country' => 'KR', 'timezone' => 'Asia/Seoul'],
        ['code' => 'MYR', 'symbol' => 'RM ',   'name' => 'Malaysian Ringgit',   'country' => 'MY', 'timezone' => 'Asia/Kuala_Lumpur'],
        ['code' => 'SGD', 'symbol' => 'S$',   'name' => 'Singapore Dollar',    'country' => 'SG', 'timezone' => 'Asia/Singapore'],
        ['code' => 'THB', 'symbol' => '฿',    'name' => 'Thai Baht',           'country' => 'TH', 'timezone' => 'Asia/Bangkok'],
        ['code' => 'IDR', 'symbol' => 'Rp ',   'name' => 'Indonesian Rupiah',   'country' => 'ID', 'timezone' => 'Asia/Jakarta'],
        ['code' => 'PHP', 'symbol' => '₱',    'name' => 'Philippine Peso',     'country' => 'PH', 'timezone' => 'Asia/Manila'],
        ['code' => 'ZAR', 'symbol' => 'R ',    'name' => 'South African Rand',  'country' => 'ZA', 'timezone' => 'Africa/Johannesburg'],
        ['code' => 'NGN', 'symbol' => '₦',    'name' => 'Nigerian Naira',      'country' => 'NG', 'timezone' => 'Africa/Lagos'],
        ['code' => 'KES', 'symbol' => 'KSh ', 'name' => 'Kenyan Shilling',     'country' => 'KE', 'timezone' => 'Africa/Nairobi'],
        ['code' => 'EGP', 'symbol' => 'E£',   'name' => 'Egyptian Pound',      'country' => 'EG', 'timezone' => 'Africa/Cairo'],
        ['code' => 'BRL', 'symbol' => 'R$',   'name' => 'Brazilian Real',      'country' => 'BR', 'timezone' => 'America/Sao_Paulo'],
        ['code' => 'MXN', 'symbol' => 'MX$',  'name' => 'Mexican Peso',        'country' => 'MX', 'timezone' => 'America/Mexico_City'],
        ['code' => 'TRY', 'symbol' => '₺',    'name' => 'Turkish Lira',        'country' => 'TR', 'timezone' => 'Europe/Istanbul'],
        ['code' => 'RUB', 'symbol' => '₽',    'name' => 'Russian Ruble',       'country' => 'RU', 'timezone' => 'Europe/Moscow'],
        ['code' => 'CHF', 'symbol' => 'CHF ', 'name' => 'Swiss Franc',         'country' => 'CH', 'timezone' => 'Europe/Zurich'],
        ['code' => 'NZD', 'symbol' => 'NZ$',  'name' => 'New Zealand Dollar',  'country' => 'NZ', 'timezone' => 'Pacific/Auckland'],
    ];
}

/**
 * Get all supported timezones
 */
function get_supported_timezones() {
    return [
        ['value' => 'Asia/Kathmandu',       'label' => 'Asia/Kathmandu (UTC+5:45)'],
        ['value' => 'Asia/Kolkata',          'label' => 'Asia/Kolkata (UTC+5:30)'],
        ['value' => 'Asia/Karachi',          'label' => 'Asia/Karachi (UTC+5:00)'],
        ['value' => 'Asia/Dhaka',            'label' => 'Asia/Dhaka (UTC+6:00)'],
        ['value' => 'Asia/Colombo',          'label' => 'Asia/Colombo (UTC+5:30)'],
        ['value' => 'Asia/Dubai',            'label' => 'Asia/Dubai (UTC+4:00)'],
        ['value' => 'Asia/Riyadh',           'label' => 'Asia/Riyadh (UTC+3:00)'],
        ['value' => 'Asia/Tokyo',            'label' => 'Asia/Tokyo (UTC+9:00)'],
        ['value' => 'Asia/Shanghai',         'label' => 'Asia/Shanghai (UTC+8:00)'],
        ['value' => 'Asia/Seoul',            'label' => 'Asia/Seoul (UTC+9:00)'],
        ['value' => 'Asia/Kuala_Lumpur',     'label' => 'Asia/Kuala Lumpur (UTC+8:00)'],
        ['value' => 'Asia/Singapore',        'label' => 'Asia/Singapore (UTC+8:00)'],
        ['value' => 'Asia/Bangkok',          'label' => 'Asia/Bangkok (UTC+7:00)'],
        ['value' => 'Asia/Jakarta',          'label' => 'Asia/Jakarta (UTC+7:00)'],
        ['value' => 'Asia/Manila',           'label' => 'Asia/Manila (UTC+8:00)'],
        ['value' => 'America/New_York',      'label' => 'America/New York (EST, UTC-5)'],
        ['value' => 'America/Los_Angeles',   'label' => 'America/Los Angeles (PST, UTC-8)'],
        ['value' => 'America/Chicago',       'label' => 'America/Chicago (CST, UTC-6)'],
        ['value' => 'America/Toronto',       'label' => 'America/Toronto (EST, UTC-5)'],
        ['value' => 'America/Sao_Paulo',     'label' => 'America/São Paulo (UTC-3)'],
        ['value' => 'America/Mexico_City',   'label' => 'America/Mexico City (CST, UTC-6)'],
        ['value' => 'Europe/London',         'label' => 'Europe/London (GMT, UTC+0)'],
        ['value' => 'Europe/Paris',          'label' => 'Europe/Paris (CET, UTC+1)'],
        ['value' => 'Europe/Istanbul',       'label' => 'Europe/Istanbul (UTC+3)'],
        ['value' => 'Europe/Moscow',         'label' => 'Europe/Moscow (UTC+3)'],
        ['value' => 'Europe/Zurich',         'label' => 'Europe/Zurich (CET, UTC+1)'],
        ['value' => 'Africa/Johannesburg',   'label' => 'Africa/Johannesburg (SAST, UTC+2)'],
        ['value' => 'Africa/Lagos',          'label' => 'Africa/Lagos (WAT, UTC+1)'],
        ['value' => 'Africa/Nairobi',        'label' => 'Africa/Nairobi (EAT, UTC+3)'],
        ['value' => 'Africa/Cairo',          'label' => 'Africa/Cairo (EET, UTC+2)'],
        ['value' => 'Australia/Sydney',      'label' => 'Australia/Sydney (AEST, UTC+10)'],
        ['value' => 'Pacific/Auckland',      'label' => 'Pacific/Auckland (NZST, UTC+12)'],
    ];
}

/**
 * Render currency <option> tags for a <select> dropdown
 * @param string|null $selected Currently selected currency symbol
 */
function render_currency_options($selected = null) {
    if ($selected === null) $selected = '₹';
    $currencies = get_supported_currencies();
    $html = '';
    foreach ($currencies as $c) {
        $isSelected = ($selected !== null && $selected === $c['symbol']) ? ' selected' : '';
        $html .= '<option value="' . htmlspecialchars($c['symbol'], ENT_QUOTES, 'UTF-8') . '"' . $isSelected . '>';
        $html .= htmlspecialchars($c['code'] . ' (' . trim($c['symbol']) . ') — ' . $c['name'], ENT_QUOTES, 'UTF-8');
        $html .= '</option>' . "\n";
    }
    return $html;
}

/**
 * Render timezone <option> tags for a <select> dropdown
 * @param string|null $selected Currently selected timezone value
 */
function render_timezone_options($selected = null) {
    if ($selected === null) $selected = 'Asia/Kolkata';
    $timezones = get_supported_timezones();
    $html = '';
    foreach ($timezones as $tz) {
        $isSelected = ($selected !== null && $selected === $tz['value']) ? ' selected' : '';
        $html .= '<option value="' . htmlspecialchars($tz['value'], ENT_QUOTES, 'UTF-8') . '"' . $isSelected . '>';
        $html .= htmlspecialchars($tz['label'], ENT_QUOTES, 'UTF-8');
        $html .= '</option>' . "\n";
    }
    return $html;
}

/**
 * Map from country code to currency symbol (used for matching EU countries to EUR)
 */
function get_country_to_currency_map() {
    $currencies = get_supported_currencies();
    $map = [];
    foreach ($currencies as $c) {
        $map[$c['country']] = $c['symbol'];
    }
    // Map common EU countries to EUR
    $euCountries = ['DE', 'FR', 'IT', 'ES', 'NL', 'BE', 'AT', 'PT', 'GR', 'FI', 'IE', 'LU', 'MT', 'CY', 'SK', 'SI', 'EE', 'LV', 'LT', 'HR'];
    foreach ($euCountries as $cc) {
        if (!isset($map[$cc])) {
            $map[$cc] = '€';
        }
    }
    return $map;
}
