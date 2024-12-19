<?php
class MiaPosApiClient
{
    private $baseUrl;

    public function __construct($baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function createPayment($token, $paymentData)
    {
        $url = $this->baseUrl . '/ecomm/api/v1/pay';
        return $this->sendRequest('POST', $url, $paymentData, $token);
    }

    public function getPaymentStatus($token, $paymentId)
    {
        $url = $this->baseUrl . '/ecomm/api/v1/payment/' . $paymentId;
        return $this->sendRequest('GET', $url, [], $token);
    }

    public function getPublicKey($token)
    {
        $url = $this->baseUrl . '/ecomm/api/v1/public-key';
        $response = $this->sendRequest('GET', $url, [], $token);

        if (isset($response['publicKey'])) {
            return $response['publicKey'];
        }

        throw new Exception('Public key not found in the response');
    }

    private function sendRequest($method, $url, $data = [], $token = null)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);

        $headers = ['Content-Type: application/json'];

        if ($token) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        if ($method === 'POST' && !empty($data)) {
            $payload = json_encode($data);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $errorMessage = "cURL error: " . curl_error($ch);
            curl_close($ch);
            throw new Exception($errorMessage);
        }

        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($statusCode >= 400) {
            throw new Exception("HTTP Error: $statusCode, Response: $response");
        }

        $decodedResponse = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Failed to decode JSON response: ' . json_last_error_msg());
        }

        return $decodedResponse;
    }
}
