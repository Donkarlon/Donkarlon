<?php

namespace App\Lib;

class GoogleDriveClient
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const DRIVE_API_URL = 'https://www.googleapis.com/drive/v3';

    private string $credentialsPath;
    private array $credentials;
    private ?string $accessToken = null;
    private int $tokenExpiresAt = 0;

    public function __construct(string $credentialsPath)
    {
        $this->credentialsPath = $credentialsPath;
        $this->credentials = $this->loadCredentials();
    }

    public function listFiles(string $folderId, int $pageSize = 10): array
    {
        $query = sprintf("'%s' in parents and trashed = false", addslashes($folderId));
        $params = http_build_query([
            'q' => $query,
            'pageSize' => $pageSize,
            'fields' => 'files(id,name,mimeType,modifiedTime)',
            'orderBy' => 'modifiedTime desc',
        ]);

        $response = $this->request('GET', '/files?' . $params);
        return $response['files'] ?? [];
    }

    public function downloadFile(string $fileId, string $destinationPath): void
    {
        $url = sprintf('/files/%s?alt=media', urlencode($fileId));
        $fp = fopen($destinationPath, 'w');
        if ($fp === false) {
            throw new \RuntimeException('Unable to open destination for writing: ' . $destinationPath);
        }

        $ch = curl_init(self::DRIVE_API_URL . $url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->getAccessToken(),
            ],
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $result = curl_exec($ch);
        if ($result === false) {
            $error = curl_error($ch);
            curl_close($ch);
            fclose($fp);
            throw new \RuntimeException('Download failed: ' . $error);
        }
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);

        if ($statusCode >= 400) {
            throw new \RuntimeException('Google Drive returned status ' . $statusCode . ' while downloading file ' . $fileId);
        }
    }

    private function request(string $method, string $path, array $body = []): array
    {
        $ch = curl_init(self::DRIVE_API_URL . $path);
        $headers = ['Authorization: Bearer ' . $this->getAccessToken()];

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
            $headers[] = 'Content-Type: application/json';
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException('Google Drive request failed: ' . $error);
        }

        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($statusCode >= 400) {
            throw new \RuntimeException('Google Drive API returned status ' . $statusCode . ': ' . $response);
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Invalid JSON response from Google Drive API.');
        }

        return $decoded;
    }

    private function loadCredentials(): array
    {
        if (!file_exists($this->credentialsPath)) {
            throw new \RuntimeException('Google credentials file not found at ' . $this->credentialsPath);
        }

        $contents = file_get_contents($this->credentialsPath);
        $decoded = json_decode($contents, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Invalid Google credentials JSON.');
        }

        foreach (['client_email', 'private_key'] as $requiredKey) {
            if (empty($decoded[$requiredKey])) {
                throw new \RuntimeException('Google credentials missing required key: ' . $requiredKey);
            }
        }

        return $decoded;
    }

    private function getAccessToken(): string
    {
        if ($this->accessToken && $this->tokenExpiresAt > time() + 60) {
            return $this->accessToken;
        }

        $jwt = $this->buildJwt();

        $postData = http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        $ch = curl_init(self::TOKEN_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException('Failed to retrieve Google access token: ' . $error);
        }
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($statusCode >= 400) {
            throw new \RuntimeException('Google token endpoint returned status ' . $statusCode . ': ' . $response);
        }

        $data = json_decode($response, true);
        if (!is_array($data) || empty($data['access_token']) || empty($data['expires_in'])) {
            throw new \RuntimeException('Invalid token response from Google.');
        }

        $this->accessToken = $data['access_token'];
        $this->tokenExpiresAt = time() + (int) $data['expires_in'];

        return $this->accessToken;
    }

    private function buildJwt(): string
    {
        $now = time();
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $claims = [
            'iss' => $this->credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/drive.readonly',
            'aud' => self::TOKEN_URL,
            'exp' => $now + 3600,
            'iat' => $now,
        ];

        $headerJson = json_encode($header);
        $claimsJson = json_encode($claims);
        if ($headerJson === false || $claimsJson === false) {
            throw new \RuntimeException('Failed to encode JWT payload.');
        }

        $segments = [
            Base64Url::encode($headerJson),
            Base64Url::encode($claimsJson),
        ];
        $signingInput = implode('.', $segments);

        $privateKey = openssl_pkey_get_private($this->credentials['private_key']);
        if ($privateKey === false) {
            throw new \RuntimeException('Unable to parse Google private key.');
        }

        $signature = '';
        $result = openssl_sign($signingInput, $signature, $privateKey, 'sha256');
        openssl_pkey_free($privateKey);

        if ($result === false) {
            throw new \RuntimeException('Failed to sign JWT.');
        }

        $segments[] = Base64Url::encode($signature);

        return implode('.', $segments);
    }
}
