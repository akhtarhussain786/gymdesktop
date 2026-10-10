<?php
/**
 * Cashfree Payment Gateway Helper
 * REST API integration using cURL — no Composer/SDK required.
 * 
 * Docs: https://docs.cashfree.com/docs/create-order
 * API Version: 2023-08-01
 */

require_once __DIR__ . '/env.php';

class CashfreeGateway {

    // ─── Configuration ──────────────────────────────────────
    // Credentials MUST come from the environment (.env / server env):
    //   CASHFREE_APP_ID, CASHFREE_SECRET_KEY, CASHFREE_ENV (sandbox|production)
    // No literal credentials are kept in source control.
    private static $appId     = '';
    private static $secretKey = '';
    private static $env       = 'production'; // legacy default when CASHFREE_ENV is not set
    private static $modeWarned = false;
    private static $apiVersion = '2023-08-01';

    public static function getAppId() {
        if (defined('CASHFREE_APP_ID')) return CASHFREE_APP_ID;
        $env = env('CASHFREE_APP_ID');
        return $env ? $env : self::$appId;
    }

    public static function getSecretKey() {
        if (defined('CASHFREE_SECRET_KEY')) return CASHFREE_SECRET_KEY;
        $env = env('CASHFREE_SECRET_KEY');
        return $env ? $env : self::$secretKey;
    }

    public static function getMode() {
        // 1. Explicit configuration always wins
        if (defined('CASHFREE_ENV')) {
            $explicit = strtolower(trim(CASHFREE_ENV));
        } else {
            $explicit = strtolower(trim((string)env('CASHFREE_ENV', '')));
        }
        if ($explicit !== '') {
            if (in_array($explicit, ['sandbox', 'test', 'testing'], true)) return 'sandbox';
            if (in_array($explicit, ['production', 'prod', 'live'], true)) return 'production';
            error_log("Cashfree: unrecognised CASHFREE_ENV value '$explicit' — falling back to key-based detection.");
        }

        // 2. Legacy fallback: infer from key format, but log loudly so ops sets CASHFREE_ENV explicitly
        $secret = self::getSecretKey();
        $appId = self::getAppId();
        $mode = self::$env ?: 'production';
        if (stripos($secret, '_test_') !== false || stripos($appId, 'TEST') !== false || stripos($secret, 'TEST') !== false) {
            $mode = 'sandbox';
        } elseif (stripos($secret, '_prod_') !== false) {
            $mode = 'production';
        }
        if (!self::$modeWarned) {
            self::$modeWarned = true;
            error_log("Cashfree: CASHFREE_ENV is not set; inferred mode '$mode' from key format. Set CASHFREE_ENV explicitly.");
        }
        return $mode;
    }

    /**
     * Get the base API URL based on environment
     */
    public static function getBaseUrl() {
        return self::getMode() === 'production'
            ? 'https://api.cashfree.com/pg'
            : 'https://sandbox.cashfree.com/pg';
    }

    /**
     * Get the Cashfree JS SDK URL for checkout drop-in
     */
    public static function getJsSdkUrl() {
        return 'https://sdk.cashfree.com/js/v3/cashfree.js';
    }

    /**
     * Generate unique Order ID
     */
    public static function generateOrderId($prefix = 'ORDER') {
        return $prefix . '_' . date('Ymd') . '_' . substr(bin2hex(random_bytes(5)), 0, 8);
    }

    /**
     * Create a Cashfree payment order
     * 
     * @param string $orderId      Unique order ID
     * @param float  $amount       Payment amount (minimum ₹1)
     * @param string $currency     Currency code (default: 'INR')
     * @param string $customerName Customer's full name
     * @param string $customerPhone Customer's phone number
     * @param string $customerEmail Customer's email
     * @param string $returnUrl    URL to redirect after payment
     * @return array ['success' => bool, 'payment_session_id' => string, 'order_id' => string, 'error' => string]
     */
    public static function createOrder($orderId, $amount, $currency, $customerName, $customerPhone, $customerEmail, $returnUrl) {
        $url = self::getBaseUrl() . '/orders';

        $cleanPhone = preg_replace('/[^0-9]/', '', $customerPhone);
        if (strlen($cleanPhone) < 10) {
            $cleanPhone = '9876543210';
        } elseif (strlen($cleanPhone) > 10) {
            $cleanPhone = substr($cleanPhone, -10);
        }

        // Clean duplicate scheme/host prefix if present (e.g. https://domain.comhttps://domain.com/path)
        if (substr_count($returnUrl, 'https://') > 1 || substr_count($returnUrl, 'http://') > 1) {
            $lastHttpPos = max((int)strrpos($returnUrl, 'https://'), (int)strrpos($returnUrl, 'http://'));
            $returnUrl = substr($returnUrl, $lastHttpPos);
        }

        $formattedReturnUrl = $returnUrl . (strpos($returnUrl, '?') !== false ? '&' : '?') . 'order_id={order_id}';
        if (self::getMode() === 'production' && str_starts_with($formattedReturnUrl, 'http://')) {
            $formattedReturnUrl = 'https://' . substr($formattedReturnUrl, 7);
        }

        $payload = [
            'order_id'       => $orderId,
            'order_amount'   => round((float)$amount, 2),
            'order_currency' => $currency ?: 'INR',
            'customer_details' => [
                'customer_id'    => 'CUST_' . substr(md5($cleanPhone . $customerEmail), 0, 16),
                'customer_name'  => $customerName ?: 'Gym Customer',
                'customer_phone' => $cleanPhone,
                'customer_email' => $customerEmail ?: 'customer@fitisify.com'
            ],
            'order_meta' => [
                'return_url' => $formattedReturnUrl
            ]
        ];

        $response = self::apiRequest('POST', $url, $payload);

        if (isset($response['payment_session_id'])) {
            return [
                'success' => true,
                'payment_session_id' => $response['payment_session_id'],
                'order_id' => $response['order_id'] ?? $orderId,
                'cf_order_id' => $response['cf_order_id'] ?? '',
                'order_status' => $response['order_status'] ?? 'ACTIVE',
                'error' => null
            ];
        }

        $errorMsg = 'Unknown error creating Cashfree order.';
        if (isset($response['message'])) {
            $errorMsg = $response['message'];
        } elseif (isset($response['type'])) {
            $errorMsg = $response['type'] . ': ' . ($response['message'] ?? 'API error');
        }

        if (stripos($errorMsg, 'authentication') !== false || (isset($response['type']) && stripos($response['type'], 'authentication') !== false)) {
            // Details stay in the server log; callers may show this message to members
            error_log("Cashfree authentication failed: check CASHFREE_APP_ID / CASHFREE_SECRET_KEY / CASHFREE_ENV in .env");
            $errorMsg = "Online payments are temporarily unavailable. Please try again later or pay at the gym.";
        }

        error_log("Cashfree createOrder error: " . json_encode($response));

        return [
            'success' => false,
            'payment_session_id' => null,
            'order_id' => $orderId,
            'error' => $errorMsg
        ];
    }

    /**
     * Initialize UPI Payment Session (Link / Intent payload) for direct mobile UPI app launch
     * 
     * @param string $paymentSessionId Cashfree payment_session_id
     * @param string $channel 'link' or 'intent'
     * @return array
     */
    public static function createUpiPayment($paymentSessionId, $channel = 'link') {
        $url = self::getBaseUrl() . '/orders/sessions';
        $payload = [
            'payment_session_id' => $paymentSessionId,
            'payment_method' => [
                'upi' => [
                    'channel' => $channel
                ]
            ]
        ];

        return self::apiRequest('POST', $url, $payload);
    }

    /**
     * Verify payment status for an order
     * 
     * @param string $orderId The order ID to check
     * @return array ['success' => bool, 'status' => string, 'order_amount' => float, 'transaction_id' => string, 'payment_method' => string, 'error' => string]
     */
    public static function verifyOrder($orderId) {
        $url = self::getBaseUrl() . '/orders/' . urlencode($orderId);
        $orderResponse = self::apiRequest('GET', $url);

        if (!isset($orderResponse['order_status'])) {
            return [
                'success' => false,
                'status' => 'FAILED',
                'order_amount' => 0,
                'currency' => 'INR',
                'transaction_id' => null,
                'payment_method' => null,
                'error' => $orderResponse['message'] ?? 'Could not fetch order status from Cashfree.'
            ];
        }

        $orderStatus = strtoupper($orderResponse['order_status']);
        $orderAmount = (float)($orderResponse['order_amount'] ?? 0);
        $orderCurrency = $orderResponse['order_currency'] ?? 'INR';

        // Check payments list to extract transaction details
        $paymentsUrl = self::getBaseUrl() . '/orders/' . urlencode($orderId) . '/payments';
        $paymentsResponse = self::apiRequest('GET', $paymentsUrl);

        $transactionId = null;
        $paymentMethod = null;
        $paymentStatus = $orderStatus;

        // Only treat the response as a payments list when it is a JSON array (error responses are objects)
        if (is_array($paymentsResponse) && !empty($paymentsResponse) && isset($paymentsResponse[0])) {
            foreach ($paymentsResponse as $payment) {
                if (isset($payment['payment_status']) && strtoupper($payment['payment_status']) === 'SUCCESS') {
                    $transactionId = $payment['cf_payment_id'] ?? null;
                    $paymentMethod = $payment['payment_group'] ?? 'online';
                    $paymentStatus = 'PAID';
                    break;
                }
            }
            if (!$transactionId && !empty($paymentsResponse)) {
                $lastPayment = end($paymentsResponse);
                $transactionId = $lastPayment['cf_payment_id'] ?? null;
                $paymentMethod = $lastPayment['payment_group'] ?? 'online';
                $paymentStatus = strtoupper($lastPayment['payment_status'] ?? $orderStatus);
            }
        }

        // If Cashfree marks order as PAID, status is PAID
        if ($orderStatus === 'PAID') {
            $paymentStatus = 'PAID';
        }

        return [
            'success' => true,
            'status' => $paymentStatus,
            'order_status' => $orderStatus,
            'order_amount' => $orderAmount,
            'currency' => $orderCurrency,
            'transaction_id' => $transactionId,
            'payment_method' => $paymentMethod,
            'raw_order' => $orderResponse,
            'error' => null
        ];
    }

    /**
     * Verify Cashfree Webhook Signature
     * 
     * @param string $rawBody   Raw JSON payload
     * @param string $signature Value of x-webhook-signature header
     * @param string $timestamp Value of x-webhook-timestamp header
     * @return bool True if valid, false otherwise
     */
    public static function verifyWebhookSignature($rawBody, $signature, $timestamp) {
        if (empty($signature) || empty($timestamp) || empty($rawBody)) {
            return false;
        }

        // Reject if timestamp is older than 15 minutes (replay attack protection).
        // Cashfree may send x-webhook-timestamp in milliseconds — normalise to seconds.
        if (is_numeric($timestamp)) {
            $tsSeconds = (float)$timestamp;
            if ($tsSeconds > 1e11) {
                $tsSeconds = $tsSeconds / 1000;
            }
            if (abs(time() - (int)$tsSeconds) > 900) {
                return false;
            }
        }

        $secretKey = self::getSecretKey();
        if ($secretKey === '') {
            error_log('Cashfree: cannot verify webhook signature — CASHFREE_SECRET_KEY is not configured.');
            return false;
        }

        // Cashfree webhook signature algorithm: HMAC-SHA256 of ($timestamp . $rawBody)
        $dataToSign = $timestamp . $rawBody;
        $expectedSignature = base64_encode(hash_hmac('sha256', $dataToSign, $secretKey, true));

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Internal helper to make cURL HTTP requests
     */
    private static function apiRequest($method, $url, $data = null) {
        $ch = curl_init();

        $headers = [
            'x-client-id: ' . self::getAppId(),
            'x-client-secret: ' . self::getSecretKey(),
            'x-api-version: ' . self::$apiVersion,
            'Content-Type: application/json',
            'Accept: application/json'
        ];

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'GET') {
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            error_log("Cashfree cURL Error: $curlError");
            return ['message' => 'Connection failed: ' . $curlError];
        }

        $decoded = json_decode($response, true);
        if ($decoded === null && $httpCode >= 400) {
            return ['message' => "HTTP $httpCode: $response"];
        }

        return $decoded;
    }
}
