<?php

declare(strict_types=1);

namespace App\Libraries;

final class LicenseGuard
{
    private const GCM_IV_SIZE = 12;
    private const GCM_TAG_SIZE = 16;
    private const MIN_ENCRYPTED_PAYLOAD_SIZE = self::GCM_IV_SIZE + self::GCM_TAG_SIZE + 1;

    public static function assertValidForRequest(string $requestPath): void
    {
        if (str_starts_with($requestPath, '/install')) {
            return;
        }

        // Demo mode bypasses all license checks for local evaluation.
        if ((bool)config('app.demo_mode')) {
            return;
        }

        $lockPath = (string)config('app.installed_lock');
        if (!is_file($lockPath)) {
            return;
        }

        try {
            $licensePath = (string)config('app.license_file');
            if (!is_file($licensePath)) {
                self::deny();
            }

            $json = file_get_contents($licensePath);
            $license = is_string($json) ? json_decode($json, true) : null;
            if (!is_array($license) || !isset($license['code'], $license['domain'], $license['signature'])) {
                self::deny();
            }

            $domain = self::requestDomain();
            if ($domain === '' || !hash_equals((string)$license['domain'], $domain)) {
                self::deny();
            }

            $code = self::decrypt((string)$license['code']);
            if (!self::isValidPurchaseCode($code)) {
                self::deny();
            }

            $signature = self::signature($code, $domain);
            if (!hash_equals((string)$license['signature'], $signature)) {
                self::deny();
            }
        } catch (\Throwable) {
            self::deny();
        }
    }

    public static function ensureSecretFile(): void
    {
        $secretPath = (string)config('app.license_secret_file');
        if (is_file($secretPath)) {
            return;
        }

        $secret = bin2hex(random_bytes(32));
        if (file_put_contents($secretPath, $secret, LOCK_EX) === false) {
            throw new \RuntimeException('Unable to create license secret file.');
        }
        if (!chmod($secretPath, 0600)) {
            throw new \RuntimeException('Unable to secure license secret file permissions.');
        }
    }

    public static function pack(string $purchaseCode, string $domain): array
    {
        return [
            'code' => self::encrypt($purchaseCode),
            'domain' => $domain,
            'signature' => self::signature($purchaseCode, $domain),
        ];
    }

    public static function purchaseCodeHash(string $purchaseCode): string
    {
        return hash('sha256', $purchaseCode);
    }

    public static function normalizeDomain(string $domain): string
    {
        $clean = strtolower(trim($domain));
        $clean = preg_replace('/:\d+$/', '', $clean) ?? $clean;
        return (string)$clean;
    }

    public static function isValidPurchaseCode(string $purchaseCode): bool
    {
        return (bool)preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', trim($purchaseCode));
    }

    public static function isValidBuyerName(string $name): bool
    {
        return (bool)preg_match('/^[\pL\pN .\'\-]{2,120}$/u', $name);
    }

    public static function normalizeBuyerName(string $name): string
    {
        return (string)preg_replace('/\s+/', ' ', trim($name));
    }

    private static function encrypt(string $value): string
    {
        // 12-byte IV is the recommended nonce size for AES-GCM.
        $iv = random_bytes(self::GCM_IV_SIZE);
        $tag = '';
        $cipher = openssl_encrypt($value, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            throw new \RuntimeException('Unable to encrypt license payload.');
        }

        return base64_encode($iv . $tag . $cipher);
    }

    private static function decrypt(string $payload): string
    {
        $raw = base64_decode($payload, true);
        if ($raw === false || strlen($raw) < self::MIN_ENCRYPTED_PAYLOAD_SIZE) {
            return '';
        }

        $iv = substr($raw, 0, self::GCM_IV_SIZE);
        $tag = substr($raw, self::GCM_IV_SIZE, self::GCM_TAG_SIZE);
        $cipher = substr($raw, self::GCM_IV_SIZE + self::GCM_TAG_SIZE);
        $decoded = openssl_decrypt($cipher, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);

        return is_string($decoded) ? $decoded : '';
    }

    private static function signature(string $purchaseCode, string $domain): string
    {
        $secret = self::secret();
        if ($secret === '') {
            throw new \RuntimeException('License secret is missing.');
        }

        return hash_hmac('sha256', $purchaseCode . '|' . $domain, $secret);
    }

    private static function key(): string
    {
        $secret = self::secret();
        if ($secret === '') {
            throw new \RuntimeException('License secret is missing.');
        }

        return hash('sha256', $secret, true);
    }

    private static function secret(): string
    {
        $secretPath = (string)config('app.license_secret_file');
        if (!is_file($secretPath)) {
            return '';
        }

        $secret = trim((string)file_get_contents($secretPath));
        return $secret;
    }

    private static function requestDomain(): string
    {
        $serverName = self::normalizeDomain((string)($_SERVER['SERVER_NAME'] ?? ''));
        if ($serverName !== '') {
            return $serverName;
        }

        $appUrlHost = self::normalizeDomain((string)(parse_url((string)config('app.url', ''), PHP_URL_HOST) ?? ''));
        if ($appUrlHost !== '') {
            return $appUrlHost;
        }
        
        return '';
    }

    private static function deny(): never
    {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'License validation failed.';
        exit;
    }
}
