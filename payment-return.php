<?php
/**
 * Public landing page for Cashfree's return_url on member renewals paid in the browser.
 * It deliberately shows no order data: the app confirms the payment with the server when the member returns.
 */
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="robots" content="noindex, nofollow" />
    <title>Payment Submitted | Fitisify</title>
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #0f1015; color: #e5e7eb; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; padding: 16px; box-sizing: border-box; }
        .card { max-width: 420px; width: 100%; background: #161822; border: 1px solid rgba(255,255,255,0.08); border-radius: 16px; padding: 28px 24px; text-align: center; }
        h1 { font-size: 1.3rem; margin: 0 0 10px; color: #fff; }
        p { font-size: 0.95rem; line-height: 1.55; margin: 0 0 10px; color: #cbd5e1; }
        .tick { width: 56px; height: 56px; border-radius: 50%; background: rgba(16,185,129,0.15); color: #34d399; display: inline-flex; align-items: center; justify-content: center; font-size: 30px; margin-bottom: 14px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="tick">&#10003;</div>
        <h1>Payment submitted</h1>
        <p>You can now return to the gym app. Your membership updates automatically once the payment is confirmed.</p>
        <p>If the app still shows the payment as pending after a few minutes, tap <strong>Check Status</strong> in the app.</p>
    </div>
</body>
</html>
