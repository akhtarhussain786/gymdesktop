<?php
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/helpers.php';

Auth::logout();

// Start a fresh, empty session only to carry the flash message to the login page
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    @session_start();
}
redirect(base_url('/index2'), 'info', 'You have been securely logged out.');
