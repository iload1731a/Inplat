<?php

declare(strict_types=1);

namespace App\Libraries;

final class LicenseGuard
{
    public static function assertValidForRequest(string $requestPath): void
    {
        if (str_starts_with($requestPath, '/install')) {
            return;
        }

        $lockPath = (string)config('app.installed_lock');
        if (!is_file($lockPath)) {
            return;
        }

        $licensePath = (string)config('app.license_file');
        if (!is_file($licensePath)) {
            self::deny();
        }

        /** @var array<string, mixed> $license */
        $license = require $licensePath;
        if (!is_array($license) || !isset($license['code'], $license['domain'], $license['signature'])) {
            self::deny();
        }

        $domain = self::normalizeDomain((string)($_SERVER['HTTP_HOST'] ?? ''));
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

    private static function encrypt(string $value): string
    {
        $iv = random_bytes(16);
        $cipher = openssl_encrypt($value, 'AES-256-CBC', self::key(), OPENSSL_RAW_DATA, $iv);
        if ($cipher === false) {
            throw new \RuntimeException('Unable to encrypt license payload.');
        }

        return base64_encode($iv . $cipher);
    }

    private static function decrypt(string $payload): string
    {
        $raw = base64_decode($payload, true);
        if ($raw === false || strlen($raw) < 17) {
            return '';
        }

        $iv = substr($raw, 0, 16);
        $cipher = substr($raw, 16);
        $decoded = openssl_decrypt($cipher, 'AES-256-CBC', self::key(), OPENSSL_RAW_DATA, $iv);

        return is_string($decoded) ? $decoded : '';
    }

    private static function signature(string $purchaseCode, string $domain): string
    {
        return hash_hmac('sha256', $purchaseCode . '|' . $domain, (string)config('app.license_secret'));
    }

    private static function key(): string
    {
        return hash('sha256', (string)config('app.license_secret'), true);
    }

    private static function deny(): never
    {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'License validation failed.';
        exit;
    }
}
