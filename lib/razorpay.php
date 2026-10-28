<?php
/**
 * Minimal Razorpay API client (orders + payments) with signature helpers.
 *
 * Uses cURL when available and falls back to PHP streams on hosts where the
 * cURL extension is disabled.
 */

class RazorpayException extends RuntimeException
{
}

final class RazorpayClient
{
    const API_BASE = 'https://api.razorpay.com/v1';

    /** @var string */
    private $keyId;

    /** @var string */
    private $keySecret;

    /** @var int */
    private $timeout;

    public function __construct($keyId, $keySecret, $timeout = 20)
    {
        $this->keyId = trim((string) $keyId);
        $this->keySecret = trim((string) $keySecret);
        $this->timeout = (int) $timeout;
    }

    /**
     * Build a client from the saved settings (null when keys are missing).
     *
     * @return RazorpayClient|null
     */
    public static function fromSettings(Settings $settings)
    {
        $all = $settings->all();
        if (trim((string) $all['razorpay_key_id']) === '' || trim((string) $all['razorpay_key_secret']) === '') {
            return null;
        }
        return new self($all['razorpay_key_id'], $all['razorpay_key_secret']);
    }

    public function keyId()
    {
        return $this->keyId;
    }

    /**
     * Create an order.
     *
     * @param  int                  $amountPaise
     * @param  string               $receipt
     * @param  array<string, mixed> $notes
     * @return array<string, mixed>
     */
    public function createOrder($amountPaise, $receipt, array $notes = [])
    {
        $payload = [
            'amount'          => (int) $amountPaise,
            'currency'        => 'INR',
            'receipt'         => (string) $receipt,
            'payment_capture' => 1,
        ];
        if (!empty($notes)) {
            $payload['notes'] = $notes;
        }
        return $this->request('POST', '/orders', $payload);
    }

    /**
     * Fetch a payment entity.
     *
     * @return array<string, mixed>
     */
    public function fetchPayment($paymentId)
    {
        return $this->request('GET', '/payments/' . rawurlencode((string) $paymentId));
    }

    /**
     * Verify the checkout signature: HMAC-SHA256(order_id|payment_id, secret).
     */
    public function verifyPaymentSignature($orderId, $paymentId, $signature)
    {
        if ($this->keySecret === '' || $orderId === '' || $paymentId === '' || $signature === '') {
            return false;
        }
        $expected = hash_hmac('sha256', (string) $orderId . '|' . (string) $paymentId, $this->keySecret);
        return hash_equals($expected, (string) $signature);
    }

    /**
     * Verify a webhook signature (raw body, webhook secret).
     */
    public static function verifyWebhookSignature($rawBody, $signature, $webhookSecret)
    {
        if ((string) $webhookSecret === '' || (string) $signature === '') {
            return false;
        }
        $expected = hash_hmac('sha256', (string) $rawBody, (string) $webhookSecret);
        return hash_equals($expected, (string) $signature);
    }

    /**
     * Sign a fake signature for tests / demo mode only.
     */
    public function sign($orderId, $paymentId)
    {
        return hash_hmac('sha256', (string) $orderId . '|' . (string) $paymentId, $this->keySecret);
    }

    /**
     * Perform an API request.
     *
     * @param  array<string, mixed>|null $body
     * @return array<string, mixed>
     */
    private function request($method, $path, $body = null)
    {
        if ($this->keyId === '' || $this->keySecret === '') {
            throw new RazorpayException('Razorpay keys are not configured.');
        }

        $url = self::API_BASE . $path;
        $payload = $body === null ? null : json_encode($body, JSON_UNESCAPED_SLASHES);

        if (function_exists('curl_init')) {
            list($status, $response) = $this->viaCurl($method, $url, $payload);
        } elseif (ini_get('allow_url_fopen')) {
            list($status, $response) = $this->viaStream($method, $url, $payload);
        } else {
            throw new RazorpayException('Neither cURL nor allow_url_fopen is available on this server.');
        }

        $decoded = json_decode((string) $response, true);

        if ($status < 200 || $status >= 300) {
            $message = 'Razorpay request failed (HTTP ' . $status . ').';
            if (is_array($decoded) && isset($decoded['error']['description'])) {
                $message = (string) $decoded['error']['description'];
            }
            app_log('error', 'Razorpay API error', ['status' => $status, 'path' => $path, 'response' => substr((string) $response, 0, 500)]);
            throw new RazorpayException($message, $status);
        }

        if (!is_array($decoded)) {
            throw new RazorpayException('Unexpected response from Razorpay.');
        }

        return $decoded;
    }

    /**
     * @return array{0:int, 1:string}
     */
    private function viaCurl($method, $url, $payload)
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD        => $this->keyId . ':' . $this->keySecret,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT      => APP_NAME . '/' . APP_VERSION,
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, (string) $payload);
        }

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RazorpayException('Could not reach Razorpay: ' . $error);
        }
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [$status, (string) $response];
    }

    /**
     * @return array{0:int, 1:string}
     */
    private function viaStream($method, $url, $payload)
    {
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Basic ' . base64_encode($this->keyId . ':' . $this->keySecret),
        ];
        $options = [
            'method'        => $method,
            'header'        => implode("\r\n", $headers),
            'timeout'       => $this->timeout,
            'ignore_errors' => true,
            'user_agent'    => APP_NAME . '/' . APP_VERSION,
            'content'       => $payload === null ? '' : $payload,
        ];
        $context = stream_context_create(['http' => $options]);
        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            throw new RazorpayException('Could not reach Razorpay from this server.');
        }
        $status = 0;
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $line) {
                if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m)) {
                    $status = (int) $m[1];
                }
            }
        }
        return [$status, (string) $response];
    }
}
