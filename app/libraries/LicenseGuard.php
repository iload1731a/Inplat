<?php

declare(strict_types=1);

namespace App\Libraries;

final class LicenseGuard
{
    public const TYPE_CODECANYON = 'codecanyon';
    public const TYPE_THIRD_PARTY = 'third_party';
    public const TYPE_OWNER = 'owner_self';

    private const GCM_IV_SIZE = 12;
    private const GCM_TAG_SIZE = 16;
    private const MIN_ENCRYPTED_PAYLOAD_SIZE = self::GCM_IV_SIZE + self::GCM_TAG_SIZE + 1;
    private const MIN_THIRD_PARTY_LICENSE_KEY_LENGTH = 8;
    private const MAX_THIRD_PARTY_LICENSE_KEY_LENGTH = 200;

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
            if (!is_array($license)) {
                self::deny();
            }

            $type = self::normalizeLicenseType((string)($license['type'] ?? self::TYPE_CODECANYON));
            if (!self::isSupportedLicenseType($type)) {
                self::deny();
            }

            $domain = self::requestDomain();
            if ($domain === '' || !hash_equals((string)$license['domain'], $domain)) {
                self::deny();
            }

            if (!self::verifyLicenseByType($type, $license, $domain)) {
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
            'type' => self::TYPE_CODECANYON,
            'code' => self::encrypt($purchaseCode),
            'domain' => $domain,
            'signature' => self::signature($purchaseCode, $domain),
        ];
    }

    public static function purchaseCodeHash(string $purchaseCode): string
    {
        return hash('sha256', $purchaseCode);
    }

    public static function packThirdParty(string $providerName, string $licenseKey, string $domain): array
    {
        $providerName = self::normalizeIdentityName($providerName);
        $domain = self::normalizeDomain($domain);
        $licenseKeyHash = hash('sha256', trim($licenseKey));
        $signature = self::signatureForParts([self::TYPE_THIRD_PARTY, $domain, $providerName, $licenseKeyHash]);

        return [
            'type' => self::TYPE_THIRD_PARTY,
            'provider_name' => $providerName,
            'license_key_hash' => $licenseKeyHash,
            'domain' => $domain,
            'signature' => $signature,
        ];
    }

    public static function packOwner(string $ownerName, string $ownerEmail, string $domain): array
    {
        $ownerName = self::normalizeIdentityName($ownerName);
        $ownerEmail = strtolower(trim($ownerEmail));
        $domain = self::normalizeDomain($domain);
        $ownerIdentityHash = hash('sha256', $ownerName . '|' . $ownerEmail);
        $signature = self::signatureForParts([self::TYPE_OWNER, $domain, $ownerIdentityHash]);

        return [
            'type' => self::TYPE_OWNER,
            'owner_name' => $ownerName,
            'owner_email' => $ownerEmail,
            'owner_identity_hash' => $ownerIdentityHash,
            'domain' => $domain,
            'signature' => $signature,
        ];
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

    public static function normalizeIdentityName(string $name): string
    {
        return self::normalizeBuyerName($name);
    }

    public static function isSupportedLicenseType(string $type): bool
    {
        return in_array(self::normalizeLicenseType($type), [
            self::TYPE_CODECANYON,
            self::TYPE_THIRD_PARTY,
            self::TYPE_OWNER,
        ], true);
    }

    public static function normalizeLicenseType(string $type): string
    {
        $normalized = strtolower(trim($type));
        return $normalized === '' ? self::TYPE_CODECANYON : $normalized;
    }

    public static function isValidThirdPartyLicenseKey(string $licenseKey): bool
    {
        $length = strlen(trim($licenseKey));
        if ($length < self::MIN_THIRD_PARTY_LICENSE_KEY_LENGTH || $length > self::MAX_THIRD_PARTY_LICENSE_KEY_LENGTH) {
            return false;
        }

        return (bool)preg_match('/^[A-Za-z0-9._:\-]+$/', trim($licenseKey));
    }

    public static function ownerLicenseEnabled(): bool
    {
        return (bool)config('app.owner_license_enabled');
    }

    public static function validateOwnerInstallToken(string $token): bool
    {
        $expected = trim((string)config('app.owner_install_token'));
        $provided = trim($token);

        return $expected !== '' && $provided !== '' && hash_equals($expected, $provided);
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

    private static function signatureForParts(array $parts): string
    {
        $secret = self::secret();
        if ($secret === '') {
            throw new \RuntimeException('License secret is missing.');
        }

        $normalized = array_map(static fn (mixed $value): string => trim((string)$value), $parts);
        return hash_hmac('sha256', implode('|', $normalized), $secret);
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

    private static function verifyLicenseByType(string $type, array $license, string $domain): bool
    {
        return match ($type) {
            self::TYPE_CODECANYON => self::verifyCodecanyonLicense($license, $domain),
            self::TYPE_THIRD_PARTY => self::verifyThirdPartyLicense($license, $domain),
            self::TYPE_OWNER => self::verifyOwnerLicense($license, $domain),
            default => false,
        };
    }

    private static function verifyCodecanyonLicense(array $license, string $domain): bool
    {
        if (!isset($license['code'], $license['signature'])) {
            return false;
        }

        $code = self::decrypt((string)$license['code']);
        if (!self::isValidPurchaseCode($code)) {
            return false;
        }

        $signature = self::signature($code, $domain);
        return hash_equals((string)$license['signature'], $signature);
    }

    private static function verifyThirdPartyLicense(array $license, string $domain): bool
    {
        if (!isset($license['provider_name'], $license['license_key_hash'], $license['signature'])) {
            return false;
        }

        $provider = self::normalizeIdentityName((string)$license['provider_name']);
        $hash = trim((string)$license['license_key_hash']);
        if ($provider === '' || $hash === '') {
            return false;
        }

        $expected = self::signatureForParts([self::TYPE_THIRD_PARTY, $domain, $provider, $hash]);
        return hash_equals((string)$license['signature'], $expected);
    }

    private static function verifyOwnerLicense(array $license, string $domain): bool
    {
        if (!self::ownerLicenseEnabled()) {
            return false;
        }

        if (!isset($license['owner_name'], $license['owner_email'], $license['owner_identity_hash'], $license['signature'])) {
            return false;
        }

        $ownerName = self::normalizeIdentityName((string)$license['owner_name']);
        $ownerEmail = strtolower(trim((string)$license['owner_email']));
        if ($ownerName === '' || filter_var($ownerEmail, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        $ownerIdentityHash = hash('sha256', $ownerName . '|' . $ownerEmail);
        if (!hash_equals((string)$license['owner_identity_hash'], $ownerIdentityHash)) {
            return false;
        }

        $expected = self::signatureForParts([self::TYPE_OWNER, $domain, $ownerIdentityHash]);
        return hash_equals((string)$license['signature'], $expected);
    }

    private static function deny(): never
    {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'License validation failed.';
        exit;
    }
}
