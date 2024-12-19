<?php
class MiaPosAuthClient
{
    private $baseUrl;
    private $merchantId;
    private $secretKey;
    private $accessToken;
    private $refreshToken;
    private $accessExpireTime;

    public function __construct($baseUrl, $merchantId, $secretKey)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->merchantId = $merchantId;
        $this->secretKey = $secretKey;
    }

    public function getAccessToken()
    {

        if ($this->accessToken && !$this->isTokenExpired()) {
            return $this->accessToken;
        }

        if ($this->refreshToken) {
            try {
                return $this->refreshAccessToken();
            } catch (Exception $e) {
                error_log('Mia pos refresh token failed: ' . $e->getMessage());
            }
        }

        return $this->generateNewTokens();
    }


    private function generateNewTokens()
    {
        $url = $this->baseUrl . '/ecomm/api/v1/token';
        $data = [
            'merchantId' => $this->merchantId,
            'secretKey' => $this->secretKey,
        ];

        $response = $this->sendRequest('POST', $url, $data);

        $this->parseResponseToken($response);

        if (!$this->accessToken) {
            throw new Exception('Failed to retrieve access token.');
        }

        return $this->accessToken;
    }


    private function refreshAccessToken()
    {
        $url = $this->baseUrl . '/ecomm/api/v1/token/refresh';
        $data = [
            'refreshToken' => $this->refreshToken,
        ];

        $response = $this->sendRequest('POST', $url, $data);

        $this->parseResponseToken($response);

        if (!$this->accessToken) {
            throw new Exception('Failed to refresh access token.');
        }

        return $this->accessToken;
    }

    private function isTokenExpired()
    {
        return !$this->accessExpireTime || time() >= $this->accessExpireTime;
    }

    private function parseResponseToken($response)
    {
        $this->accessToken = isset($response['accessToken']) ? $response['accessToken'] : null;
        $this->refreshToken = isset($response['refreshToken']) ? $response['refreshToken'] : null;
        $this->accessExpireTime = time() + (isset($response['accessTokenExpiresIn']) ? $response['accessTokenExpiresIn'] : 0) - 10;
    }


    private function sendRequest($method, $url, $data = [])
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        if ($method === 'POST') {
            $payload = json_encode($data);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $errorMessage = 'cURL error: ' . curl_error($ch);
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
