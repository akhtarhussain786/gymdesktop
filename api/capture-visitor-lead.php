<?php
/**
 * API Endpoint: Capture Real Visitor Lead (Name, Phone, Email, Gym, City)
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../core/visitor_tracker.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

// Allow JSON payload or POST form (cap body size)
$raw = file_get_contents('php://input', false, null, 0, 65536);
$input = json_decode((string)$raw, true);
if (!is_array($input) || empty($input)) {
    $input = $_POST;
}

// Honeypot: real visitors never fill the hidden "website" / "company_url" field; bots usually do.
if (!empty($input['website']) || !empty($input['company_url'])) {
    echo json_encode(['success' => true, 'message' => 'Thank you! Our fitness SaaS consultant will connect with you shortly.']);
    exit();
}

try {
    // Public website endpoint: needs the visitor session to link page views with the lead
    secure_session_start();
    $response = VisitorTracker::captureLead($input);
} catch (Throwable $e) {
    error_log('capture-visitor-lead failed: ' . $e->getMessage());
    $response = ['success' => false, 'error' => 'Unable to save lead. Please try again.'];
}
echo json_encode($response);
exit();
