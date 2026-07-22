<?php

declare(strict_types=1);

namespace App\Libraries;

final class RequestContext
{
    public static function ipAddress(): string
    {
        $remoteAddress = (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        $remoteAddress = filter_var($remoteAddress, FILTER_VALIDATE_IP) ? $remoteAddress : '0.0.0.0';

        $trustedProxies = (array)config('app.trusted_proxies', []);
        if (!in_array($remoteAddress, $trustedProxies, true)) {
            return $remoteAddress;
        }

        $forwarded = (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
        if ($forwarded === '') {
            return $remoteAddress;
        }

        // Rightmost forwarded address is closest to the client when traversing trusted proxies.
        $parts = array_reverse(array_map('trim', explode(',', $forwarded)));
        foreach ($parts as $part) {
            if (filter_var($part, FILTER_VALIDATE_IP)) {
                return $part;
            }
        }

        return $remoteAddress;
    }

    public static function userAgent(): string
    {
        return substr(trim((string)($_SERVER['HTTP_USER_AGENT'] ?? 'unknown')), 0, 500);
    }
}
