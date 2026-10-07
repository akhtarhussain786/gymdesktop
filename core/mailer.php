<?php
/**
 * Professional HTML & Plain-Text Email Service & Gmail SMTP Dispatcher
 * Sends legitimate transactional emails using authenticated Gmail SMTP.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/env.php';
require_once __DIR__ . '/smtp_client.php';

class Mailer {

    /**
     * Get Mail Configuration
     */
    public static function getConfig() {
        $configFile = __DIR__ . '/../config/mail.php';
        if (file_exists($configFile)) {
            return self::sanitizeConfig(include $configFile);
        }

        $appPass = env('GMAIL_APP_PASSWORD', env('MAIL_PASSWORD', ''));

        return self::sanitizeConfig([
            'host'         => env('MAIL_HOST', 'smtp.gmail.com'),
            'port'         => (int)env('MAIL_PORT', 587),
            'encryption'   => strtolower(env('MAIL_ENCRYPTION', 'tls')),
            'auth'         => true,
            'username'     => env('MAIL_USERNAME', 'nexoralabtechnologies@gmail.com'),
            'password'     => $appPass,
            'from_address' => env('MAIL_FROM_ADDRESS', 'nexoralabtechnologies@gmail.com'),
            'from_name'    => env('MAIL_FROM_NAME', 'Fitisify Gym Management'),
            'reply_to'     => env('MAIL_REPLY_TO', 'nexoralabtechnologies@gmail.com'),
            'timeout'      => 15,
        ]);
    }

    /**
     * Strip CR/LF from values that end up in SMTP commands / message headers (header injection guard)
     */
    private static function sanitizeConfig($config) {
        if (!is_array($config)) {
            return [];
        }
        foreach (['from_address', 'from_name', 'reply_to', 'username', 'host'] as $k) {
            if (isset($config[$k])) {
                $config[$k] = trim(str_replace(["\r", "\n", "\0"], '', (string)$config[$k]));
            }
        }
        foreach (['from_address', 'reply_to'] as $k) {
            if (!empty($config[$k]) && !filter_var($config[$k], FILTER_VALIDATE_EMAIL)) {
                error_log("Mailer: invalid {$k} configured; falling back to username.");
                $config[$k] = filter_var($config['username'] ?? '', FILTER_VALIDATE_EMAIL) ? $config['username'] : '';
            }
        }
        return $config;
    }

    /**
     * Generic HTML email sender (subject is single-line; recipient must be a valid address)
     *
     * @return array ['success' => bool, 'error' => string|null]
     */
    public static function send($to, $subject, $htmlBody, $emailType = 'general', $tenantId = null) {
        $to = trim((string)$to);
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Invalid destination email address.'];
        }
        $subject = trim(str_replace(["\r", "\n"], ' ', (string)$subject));

        $config = self::getConfig();
        $smtp = new SmtpClient($config);
        $res = $smtp->send($to, $subject, $htmlBody, [
            'from_email' => $config['from_address'],
            'from_name'  => $config['from_name'],
            'reply_to'   => $config['reply_to']
        ]);

        self::logDelivery([
            'recipient_email' => $to,
            'email_type'      => $emailType,
            'order_ref'       => null,
            'tenant_id'       => $tenantId,
            'status'          => $res['success'] ? 'sent' : 'failed',
            'sent_at'         => $res['success'] ? date('Y-m-d H:i:s') : null,
            'failure_reason'  => $res['error'] ?? null
        ]);

        return ['success' => (bool)$res['success'], 'error' => $res['error'] ?? null];
    }

    /**
     * Send password reset link email
     */
    public static function sendPasswordResetEmail($to, $name, $resetUrl, $tenantId = null) {
        $safeName = htmlspecialchars((string)($name ?: 'there'), ENT_QUOTES, 'UTF-8');
        $safeUrl  = htmlspecialchars((string)$resetUrl, ENT_QUOTES, 'UTF-8');
        $subject  = 'Reset your Fitisify password';
        $htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>Reset your password</title></head>
<body style="margin:0;padding:30px 10px;background:#f4f6f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#1e293b;">
  <div style="max-width:560px;margin:0 auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;padding:28px;">
    <h2 style="margin-top:0;color:#0f172a;">Password reset request</h2>
    <p style="font-size:15px;line-height:1.6;">Hello {$safeName},</p>
    <p style="font-size:15px;line-height:1.6;">We received a request to reset the password for your Fitisify account. Click the button below to choose a new password. This link expires in 1 hour and can only be used once.</p>
    <p style="text-align:center;margin:28px 0;">
      <a href="{$safeUrl}" style="background:#2563eb;color:#ffffff;padding:12px 26px;border-radius:8px;text-decoration:none;font-weight:700;display:inline-block;">Reset Password</a>
    </p>
    <p style="font-size:13px;line-height:1.6;color:#475569;">If the button does not work, copy this link into your browser:<br><span style="word-break:break-all;">{$safeUrl}</span></p>
    <p style="font-size:13px;line-height:1.6;color:#475569;">If you did not request a password reset, you can safely ignore this email. Your password will not change.</p>
  </div>
</body>
</html>
HTML;
        return self::send($to, $subject, $htmlBody, 'password_reset', $tenantId);
    }

    /**
     * Send Credentials Email to Gym Owner (with strict Idempotency & Delivery Tracking)
     * 
     * @param array $details [
     *   'to_email' => string,
     *   'owner_name' => string,
     *   'gym_name' => string,
     *   'plan_name' => string,
     *   'amount_paid' => float,
     *   'currency' => string,
     *   'activation_date' => string,
     *   'expiry_date' => string,
     *   'username' => string,
     *   'temp_password' => string,
     *   'order_ref' => string,
     *   'tenant_id' => int|null,
     *   'force_resend' => bool (default false)
     * ]
     * @return array ['success' => bool, 'error' => string|null, 'skipped' => bool]
     */
    public static function sendCredentialsEmail(array $details) {
        $to = trim($details['to_email'] ?? '');
        if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Invalid destination email address.', 'skipped' => false];
        }

        $orderRef    = trim($details['order_ref'] ?? '');
        $tenantId    = !empty($details['tenant_id']) ? (int)$details['tenant_id'] : null;
        $forceResend = !empty($details['force_resend']);
        $emailType   = $forceResend ? 'admin_resend_credentials' : 'onboarding_credentials';

        // 1. Idempotency Check: if this order already has a successfully delivered credentials email, skip
        if (!$forceResend && !empty($orderRef)) {
            $existingLog = DB::fetchOne(
                "SELECT * FROM email_delivery_logs WHERE order_ref = ? AND email_type = 'onboarding_credentials' AND status = 'sent' LIMIT 1",
                [$orderRef]
            );
            if ($existingLog) {
                return [
                    'success' => true,
                    'error' => null,
                    'skipped' => true,
                    'message' => 'Credentials email was already dispatched for this order reference.'
                ];
            }
        }

        // 2. Prepare & Escape Dynamic Template Variables safely
        $customerName = htmlspecialchars((string)($details['owner_name'] ?? 'Gym Owner'), ENT_QUOTES, 'UTF-8');
        $gymName      = htmlspecialchars((string)($details['gym_name'] ?? 'Your Gym'), ENT_QUOTES, 'UTF-8');
        $planName     = htmlspecialchars((string)($details['plan_name'] ?? 'Subscription Tier'), ENT_QUOTES, 'UTF-8');
        $currency     = htmlspecialchars((string)($details['currency'] ?? '₹'), ENT_QUOTES, 'UTF-8');
        $amount       = number_format((float)($details['amount_paid'] ?? 0), 2);
        $actDate      = htmlspecialchars((string)($details['activation_date'] ?? date('Y-m-d')), ENT_QUOTES, 'UTF-8');
        $expDate      = htmlspecialchars((string)($details['expiry_date'] ?? date('Y-m-d', strtotime('+30 days'))), ENT_QUOTES, 'UTF-8');
        $username     = htmlspecialchars((string)($details['username'] ?? ''), ENT_QUOTES, 'UTF-8');
        $tempPassword = htmlspecialchars((string)($details['temp_password'] ?? ''), ENT_QUOTES, 'UTF-8');
        $safeOrderRef = htmlspecialchars((string)$orderRef, ENT_QUOTES, 'UTF-8');
        $supportEmail = 'nexoralabtechnologies@gmail.com';
        $loginUrl     = self::getBaseAppUrl() . '/index2.php';

        $subject = "Your Fitisify Account Is Ready";

        // HTML Email Template (Table-based, 600px width, clean light design with lime accents)
        $htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Fitisify Account Is Ready</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f6f9; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color:#1e293b; -webkit-text-size-adjust:100%;">
    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color:#f4f6f9; padding:40px 10px;">
        <tr>
            <td align="center">
                <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width:600px; background-color:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e2e8f0; box-shadow:0 4px 16px rgba(0,0,0,0.05);">
                    <!-- Header -->
                    <tr>
                        <td style="background-color:#0f172a; padding:32px 28px; text-align:center; border-bottom:4px solid #84cc16;">
                            <h1 style="margin:0; font-size:24px; font-weight:800; color:#ffffff; letter-spacing:-0.5px;">Fitisify Gym Management</h1>
                            <p style="margin:6px 0 0; font-size:14px; color:#94a3b8;">Account Activation Notification</p>
                        </td>
                    </tr>

                    <!-- Body Content -->
                    <tr>
                        <td style="padding:32px 28px;">
                            <p style="font-size:16px; line-height:1.6; color:#0f172a; margin:0 0 20px;">
                                Hello <strong>{$customerName}</strong>,
                            </p>
                            <p style="font-size:15px; line-height:1.6; color:#334155; margin:0 0 24px;">
                                Welcome to <strong>Fitisify Gym Management</strong>.<br>
                                Your account for <strong>{$gymName}</strong> has been successfully activated and is ready to use.
                            </p>

                            <!-- Account Details Box -->
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; margin-bottom:24px;">
                                <tr>
                                    <td style="padding:16px 20px; border-bottom:1px solid #e2e8f0; background-color:#f1f5f9;">
                                        <strong style="font-size:13px; text-transform:uppercase; letter-spacing:0.5px; color:#475569;">Account Details</strong>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:16px 20px;">
                                        <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                                            <tr>
                                                <td style="padding:6px 0; font-size:14px; color:#64748b; width:40%;">Gym Name:</td>
                                                <td style="padding:6px 0; font-size:14px; color:#0f172a; font-weight:700; text-align:right;">{$gymName}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:6px 0; font-size:14px; color:#64748b;">Plan:</td>
                                                <td style="padding:6px 0; font-size:14px; color:#0f172a; font-weight:700; text-align:right;">{$planName}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:6px 0; font-size:14px; color:#64748b;">Subscription Valid Until:</td>
                                                <td style="padding:6px 0; font-size:14px; color:#0f172a; font-weight:700; text-align:right;">{$expDate}</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- Credentials Box -->
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; margin-bottom:24px;">
                                <tr>
                                    <td style="padding:16px 20px; border-bottom:1px solid #dcfce7; background-color:#dcfce7;">
                                        <strong style="font-size:13px; text-transform:uppercase; letter-spacing:0.5px; color:#166534;">Login Details</strong>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:20px;">
                                        <p style="margin:0 0 10px; font-size:13px; color:#166534; font-weight:600;">Username:</p>
                                        <div style="font-family:'Courier New', Courier, monospace; font-size:16px; font-weight:700; color:#0f172a; background-color:#ffffff; padding:10px 14px; border-radius:6px; border:1px solid #bbf7d0; margin-bottom:16px;">{$username}</div>

                                        <p style="margin:0 0 10px; font-size:13px; color:#166534; font-weight:600;">Temporary Password:</p>
                                        <div style="font-family:'Courier New', Courier, monospace; font-size:16px; font-weight:700; color:#0f172a; background-color:#ffffff; padding:10px 14px; border-radius:6px; border:1px solid #bbf7d0;">{$tempPassword}</div>
                                    </td>
                                </tr>
                            </table>

                            <p style="font-size:14px; line-height:1.5; color:#475569; margin:0 0 12px;">
                                For your security, please change your temporary password after signing in for the first time.
                            </p>
                            <p style="font-size:14px; line-height:1.5; color:#dc2626; font-weight:600; margin:0 0 28px;">
                                Please do not share your login credentials with anyone.
                            </p>

                            <!-- Single CTA Button -->
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:28px;">
                                <tr>
                                    <td align="center">
                                        <a href="{$loginUrl}" target="_blank" style="display:inline-block; background-color:#65a30d; color:#ffffff !important; text-decoration:none; padding:14px 36px; border-radius:8px; font-weight:700; font-size:16px; text-align:center;">Sign In to Your Account</a>
                                    </td>
                                </tr>
                            </table>

                            <p style="font-size:14px; line-height:1.6; color:#475569; margin:0 0 24px;">
                                If you need assistance with your account, reply to this email and our team will assist you.
                            </p>

                            <p style="font-size:14px; line-height:1.6; color:#1e293b; margin:0;">
                                Regards,<br>
                                <strong>Fitisify Gym Management Team</strong><br>
                                <strong>NexoraLab Technologies</strong>
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color:#f8fafc; padding:20px 28px; text-align:center; font-size:12px; color:#94a3b8; border-top:1px solid #e2e8f0; line-height:1.5;">
                            This is an automated transactional notification sent after successful Fitisify account activation.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;

        // Dynamic Plain Text Fallback Body
        $plainTextBody = <<<TEXT
Hello {$details['owner_name']},

Welcome to Fitisify Gym Management.

Your account for {$details['gym_name']} has been successfully activated and is ready to use.

Account Details
Gym Name: {$details['gym_name']}
Plan: {$details['plan_name']}
Subscription Valid Until: {$details['expiry_date']}

Login Details
Username: {$details['username']}
Temporary Password: {$details['temp_password']}

For your security, please change your temporary password after signing in for the first time.

Please do not share your login credentials with anyone.

Sign In: {$loginUrl}

If you need assistance with your account, reply to this email and our team will assist you.

Regards,
Fitisify Gym Management Team
NexoraLab Technologies

This is an automated transactional notification sent after successful Fitisify account activation.
TEXT;

        // 3. Dispatch via SmtpClient
        $config = self::getConfig();
        $smtp = new SmtpClient($config);

        $sendResult = $smtp->send($to, $subject, $htmlBody, [
            'from_email' => $config['from_address'],
            'from_name'  => $config['from_name'],
            'reply_to'   => $config['reply_to'],
            'plain_text' => $plainTextBody
        ]);

        $status = $sendResult['success'] ? 'sent' : 'failed';
        $failureReason = $sendResult['error'] ?? null;
        $sentAt = $sendResult['success'] ? date('Y-m-d H:i:s') : null;

        // 4. Log Delivery Tracking in DB (Never log sensitive password data)
        self::logDelivery([
            'recipient_email' => $to,
            'email_type'      => $emailType,
            'order_ref'       => $orderRef,
            'tenant_id'       => $tenantId,
            'status'          => $status,
            'sent_at'         => $sentAt,
            'failure_reason'  => $failureReason
        ]);

        // 5. Append Sanitized Audit Log File (Never log sensitive password data)
        self::writeAuditFile($to, $subject, $orderRef, $status, $failureReason);

        return [
            'success' => $sendResult['success'],
            'error'   => $failureReason,
            'skipped' => false
        ];
    }

    /**
     * Send Super Admin SMTP Test Email
     * 
     * @param string $to Destination test email
     * @return array ['success' => bool, 'message' => string, 'error' => string|null, 'diagnostics' => array]
     */
    public static function sendTestEmail($to) {
        $to = trim($to);
        if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Please provide a valid recipient email address.'];
        }

        $config = self::getConfig();
        $smtp = new SmtpClient($config);

        $subject = "Fitisify SMTP Diagnostic Test - " . date('Y-m-d H:i:s');
        $currentTime = date('Y-m-d H:i:s T');
        $fromName = htmlspecialchars($config['from_name']);
        $fromEmail = htmlspecialchars($config['from_address']);
        $host = htmlspecialchars($config['host']);
        $port = (int)$config['port'];
        $enc = htmlspecialchars(strtoupper($config['encryption']));

        $htmlBody = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>SMTP Test Email</title>
</head>
<body style="background:#0f172a;color:#e2e8f0;font-family:Segoe UI,Arial,sans-serif;padding:30px;">
    <div style="max-width:540px;margin:0 auto;background:#1e293b;border:1px solid #334155;border-radius:10px;padding:24px;">
        <h2 style="color:#10b981;margin-top:0;">✅ SMTP Test Succeeded!</h2>
        <p>This test email confirms that your Gmail SMTP server configuration is working correctly.</p>
        <div style="background:#0f172a;border:1px solid #334155;border-radius:6px;padding:16px;font-size:13px;line-height:1.6;">
            <div><strong>SMTP Host:</strong> {$host}:{$port} ({$enc})</div>
            <div><strong>From Address:</strong> {$fromName} &lt;{$fromEmail}&gt;</div>
            <div><strong>Timestamp:</strong> {$currentTime}</div>
            <div><strong>Status:</strong> TLS Handshake & Authentication Verified</div>
        </div>
        <p style="font-size:12px;color:#94a3b8;margin-top:20px;">
            Fitisify SaaS Gym Management Platform • Super Admin Diagnostics
        </p>
    </div>
</body>
</html>
HTML;

        $res = $smtp->send($to, $subject, $htmlBody, [
            'from_email' => $config['from_address'],
            'from_name'  => $config['from_name'],
            'reply_to'   => $config['reply_to']
        ]);

        $status = $res['success'] ? 'sent' : 'failed';
        $failureReason = $res['error'] ?? null;

        self::logDelivery([
            'recipient_email' => $to,
            'email_type'      => 'smtp_test',
            'order_ref'       => 'TEST_' . time(),
            'tenant_id'       => null,
            'status'          => $status,
            'sent_at'         => $res['success'] ? date('Y-m-d H:i:s') : null,
            'failure_reason'  => $failureReason
        ]);

        return $res;
    }

    /**
     * Log delivery event into database
     */
    private static function logDelivery(array $data) {
        try {
            $orderRef = $data['order_ref'] ?? null;
            $emailType = $data['email_type'] ?? 'general';

            // Check if existing record exists for this order_ref and type
            if (!empty($orderRef)) {
                $existing = DB::fetchOne("SELECT id, attempts FROM email_delivery_logs WHERE order_ref = ? AND email_type = ?", [$orderRef, $emailType]);
                if ($existing) {
                    $attempts = (int)$existing['attempts'] + 1;
                    DB::update('email_delivery_logs', [
                        'recipient_email' => $data['recipient_email'],
                        'status'          => $data['status'],
                        'attempts'        => $attempts,
                        'sent_at'         => $data['sent_at'],
                        'last_attempt_at' => date('Y-m-d H:i:s'),
                        'failure_reason'  => $data['failure_reason']
                    ], 'id = ?', [$existing['id']]);
                    return;
                }
            }

            DB::insert('email_delivery_logs', [
                'recipient_email' => $data['recipient_email'],
                'email_type'      => $emailType,
                'order_ref'       => $orderRef,
                'tenant_id'       => $data['tenant_id'] ?? null,
                'status'          => $data['status'],
                'attempts'        => 1,
                'sent_at'         => $data['sent_at'],
                'last_attempt_at' => date('Y-m-d H:i:s'),
                'failure_reason'  => $data['failure_reason']
            ]);
        } catch (Exception $e) {
            error_log("Failed to log email delivery to DB: " . $e->getMessage());
        }
    }

    /**
     * Write sanitized log entry to logs/email.log
     */
    private static function writeAuditFile($to, $subject, $orderRef, $status, $error = null) {
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }

        $sanitizedError = $error ? " | ERROR: " . preg_replace('/[\r\n]+/', ' ', $error) : "";
        $entry = date('Y-m-d H:i:s') . " | TO: {$to} | ORDER: {$orderRef} | STATUS: {$status}{$sanitizedError}\n";
        @file_put_contents($logDir . '/email.log', $entry, FILE_APPEND);
    }

    /**
     * Dynamic Base URL Helper
     */
    private static function getBaseAppUrl() {
        if (function_exists('base_url')) {
            return base_url('');
        }
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . '://' . $host;
    }
}
