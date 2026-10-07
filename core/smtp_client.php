<?php
/**
 * Standalone RFC-Compliant SMTP Client with STARTTLS & SSL/TLS Verification
 * Zero Composer dependencies; works natively with PHP stream sockets.
 */

class SmtpClient {
    private $host;
    private $port;
    private $encryption;
    private $username;
    private $password;
    private $timeout;
    private $socket = null;
    private $lastError = null;
    private $diagnosticLog = [];

    public function __construct(array $config = []) {
        $this->host       = $config['host'] ?? 'smtp.gmail.com';
        $this->port       = (int)($config['port'] ?? 587);
        $this->encryption = strtolower($config['encryption'] ?? 'tls');
        $this->username   = $config['username'] ?? '';
        $this->password   = str_replace(' ', '', $config['password'] ?? '');
        $this->timeout    = (int)($config['timeout'] ?? 15);
    }

    /**
     * Send email via SMTP
     * 
     * @param string $to Recipient email
     * @param string $subject Email subject
     * @param string $htmlBody HTML email content
     * @param array $headers Additional headers (From, FromName, ReplyTo)
     * @return array ['success' => bool, 'error' => string|null, 'diagnostics' => array]
     */
    public function send($to, $subject, $htmlBody, array $headers = []) {
        $this->diagnosticLog = [];
        $this->lastError = null;

        if (empty($this->host) || empty($this->port)) {
            return $this->fail('Missing SMTP host or port configuration.');
        }

        if (empty($this->username) || empty($this->password)) {
            return $this->fail('Missing SMTP username or App Password in configuration.');
        }

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return $this->fail("Invalid recipient email address: '{$to}'");
        }

        $fromEmail = $headers['from_email'] ?? $this->username;
        $fromName  = $headers['from_name'] ?? 'Fitisify Gym Management';
        $replyTo   = $headers['reply_to'] ?? $fromEmail;

        try {
            $this->connect();
            $this->authenticate();
            $this->mailTransaction($fromEmail, $to, $subject, $htmlBody, $fromName, $replyTo, $headers['plain_text'] ?? null);
            $this->disconnect();

            return [
                'success' => true,
                'error' => null,
                'diagnostics' => $this->diagnosticLog
            ];
        } catch (Exception $e) {
            $this->disconnect();
            return $this->fail($e->getMessage());
        }
    }

    /**
     * Test SMTP connection & authentication without sending content
     */
    public function testConnection() {
        $this->diagnosticLog = [];
        $this->lastError = null;

        try {
            $this->connect();
            $this->authenticate();
            $this->disconnect();

            return [
                'success' => true,
                'message' => 'SMTP connection and Gmail authentication succeeded successfully.',
                'diagnostics' => $this->diagnosticLog
            ];
        } catch (Exception $e) {
            $this->disconnect();
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'diagnostics' => $this->diagnosticLog
            ];
        }
    }

    /**
     * Establish socket connection and negotiate TLS/STARTTLS
     */
    private function connect() {
        $protocol = ($this->encryption === 'ssl' || $this->port === 465) ? 'ssl://' : 'tcp://';
        $remoteSocket = $protocol . $this->host . ':' . $this->port;

        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
                'crypto_type' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT
            ]
        ]);

        $errno = 0;
        $errstr = '';

        $this->socket = @stream_socket_client(
            $remoteSocket,
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$this->socket) {
            $reason = $errstr ? " ({$errstr})" : "";
            if ($errno === 10060 || $errno === 110) {
                throw new Exception("Connection timed out connecting to {$this->host}:{$this->port}. Outbound port {$this->port} may be blocked by your network or hosting firewall.");
            }
            throw new Exception("Could not connect to SMTP server {$this->host}:{$this->port}{$reason}. Check network and firewall.");
        }

        stream_set_timeout($this->socket, $this->timeout);

        // Read initial server banner (220)
        $banner = $this->readResponse();
        $this->assertCode($banner, 220, "Invalid SMTP greeting from {$this->host}");

        // Initial EHLO
        $clientHost = !empty($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'localhost';
        $ehlo = $this->sendCommand("EHLO {$clientHost}");
        $this->assertCode($ehlo, 250, "EHLO greeting failed.");

        // Upgrade to STARTTLS if on port 587 or encryption = tls
        if ($this->encryption === 'tls' || ($this->port === 587 && $this->encryption !== 'ssl')) {
            $starttls = $this->sendCommand("STARTTLS");
            $this->assertCode($starttls, 220, "STARTTLS negotiation rejected by mail server.");

            $cryptoMethod = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
            $cryptoOk = @stream_socket_enable_crypto($this->socket, true, $cryptoMethod);

            if (!$cryptoOk) {
                throw new Exception("TLS encryption handshake failed with {$this->host}. Please ensure OpenSSL extension is enabled.");
            }

            // Send second EHLO after successful TLS upgrade as required by RFC 3207
            $ehlo2 = $this->sendCommand("EHLO {$clientHost}");
            $this->assertCode($ehlo2, 250, "Post-TLS EHLO greeting failed.");
        }
    }

    /**
     * Authenticate with Gmail SMTP using AUTH LOGIN
     */
    private function authenticate() {
        $authResp = $this->sendCommand("AUTH LOGIN");
        $this->assertCode($authResp, 334, "SMTP Server does not accept AUTH LOGIN.");

        // Send base64 username
        $userResp = $this->sendCommand(base64_encode($this->username), false);
        $this->assertCode($userResp, 334, "SMTP Username was rejected by mail server.");

        // Send base64 password (never logged)
        $passResp = $this->sendCommand(base64_encode($this->password), false);
        
        if (substr($passResp, 0, 3) !== '235') {
            if (strpos($passResp, '535') !== false || strpos($passResp, '5.7.8') !== false) {
                throw new Exception("Gmail SMTP Authentication failed (535 5.7.8). Please check your Gmail username and ensure you are using an active 16-character Gmail App Password (with 2-Step Verification enabled).");
            }
            throw new Exception("SMTP Authentication rejected by mail server: " . $this->sanitizeResponse($passResp));
        }

        $this->diagnosticLog[] = "Authentication successful (235).";
    }

    /**
     * Execute MAIL, RCPT, DATA envelope transaction
     */
    private function mailTransaction($fromEmail, $to, $subject, $htmlBody, $fromName, $replyTo, $customPlainText = null) {
        // MAIL FROM
        $mailFrom = $this->sendCommand("MAIL FROM:<{$fromEmail}>");
        $this->assertCode($mailFrom, 250, "MAIL FROM command was rejected for {$fromEmail}.");

        // RCPT TO
        $rcptTo = $this->sendCommand("RCPT TO:<{$to}>");
        $this->assertCode($rcptTo, 250, "Recipient email address was rejected by server: {$to}");

        // DATA
        $dataResp = $this->sendCommand("DATA");
        $this->assertCode($dataResp, 354, "DATA command rejected by server.");

        // Generate RFC 2822 email payload
        $boundary = "----=_Part_" . md5(uniqid(mt_rand(), true));
        $msgId = "<" . time() . "." . md5($to . $subject) . "@" . ($this->host) . ">";
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $encodedFromName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';

        $payload  = "Date: " . date('r') . "\r\n";
        $payload .= "To: <{$to}>\r\n";
        $payload .= "From: {$encodedFromName} <{$fromEmail}>\r\n";
        $payload .= "Reply-To: <{$replyTo}>\r\n";
        $payload .= "Subject: {$encodedSubject}\r\n";
        $payload .= "Message-ID: {$msgId}\r\n";
        $payload .= "X-Mailer: Fitisify-Gym-SaaS/2.0\r\n";
        $payload .= "MIME-Version: 1.0\r\n";
        $payload .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n\r\n";

        // Plain Text Alternative
        $plainText = !empty($customPlainText) ? $customPlainText : strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $htmlBody));
        $payload .= "--{$boundary}\r\n";
        $payload .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $payload .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $payload .= chunk_split(base64_encode($plainText)) . "\r\n";

        // HTML Part
        $payload .= "--{$boundary}\r\n";
        $payload .= "Content-Type: text/html; charset=UTF-8\r\n";
        $payload .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $payload .= chunk_split(base64_encode($htmlBody)) . "\r\n";

        $payload .= "--{$boundary}--\r\n";
        $payload .= "\r\n.\r\n";

        // Send payload
        fwrite($this->socket, $payload);
        $finalResp = $this->readResponse();
        $this->assertCode($finalResp, 250, "Message transmission failed: " . $this->sanitizeResponse($finalResp));

        $this->diagnosticLog[] = "Message accepted for delivery (250).";
    }

    /**
     * Send QUIT and close socket
     */
    private function disconnect() {
        if ($this->socket) {
            @fwrite($this->socket, "QUIT\r\n");
            @fclose($this->socket);
            $this->socket = null;
        }
    }

    /**
     * Send command and read multiline response
     */
    private function sendCommand($command, $log = true) {
        if (!$this->socket) {
            throw new Exception("SMTP socket is closed.");
        }

        fwrite($this->socket, $command . "\r\n");
        $response = $this->readResponse();

        if ($log) {
            $this->diagnosticLog[] = "> " . $this->sanitizeCommand($command);
            $this->diagnosticLog[] = "< " . trim($response);
        }

        return $response;
    }

    /**
     * Read complete (possibly multiline) response from socket
     */
    private function readResponse() {
        $response = '';
        while ($line = fgets($this->socket, 1024)) {
            $response .= $line;
            // RFC 5321: multiline responses have '-' after the 3-digit code (e.g. 250-SIZE)
            // Final line has a space after 3-digit code (e.g. 250 OK)
            if (strlen($line) >= 4 && substr($line, 3, 1) === ' ') {
                break;
            }
            if (strlen($line) === 3) {
                break;
            }
        }
        return $response;
    }

    /**
     * Assert response starts with expected 3-digit code
     */
    private function assertCode($response, $expectedCode, $errorMessage) {
        $actualCode = (int)substr(trim($response), 0, 3);
        if ($actualCode !== (int)$expectedCode) {
            $sanitized = $this->sanitizeResponse($response);
            throw new Exception("{$errorMessage} [Server code: {$actualCode}, Response: {$sanitized}]");
        }
    }

    /**
     * Sanitize command strings to prevent credential exposure in logs
     */
    private function sanitizeCommand($cmd) {
        if (str_starts_with(strtoupper($cmd), 'AUTH LOGIN')) {
            return 'AUTH LOGIN';
        }
        return $cmd;
    }

    /**
     * Sanitize response text
     */
    private function sanitizeResponse($resp) {
        return trim(preg_replace('/[\r\n]+/', ' ', $resp));
    }

    /**
     * Helper to return failure result
     */
    private function fail($message) {
        $this->lastError = $message;
        return [
            'success' => false,
            'error' => $message,
            'diagnostics' => $this->diagnosticLog
        ];
    }
}
