<?php

declare(strict_types=1);

namespace App\Libraries;

final class RequestContext
{
    public static function ipAddress(): string
    {
        $forwarded = (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
        if ($forwarded !== '') {
            $parts = array_map('trim', explode(',', $forwarded));
            foreach ($parts as $part) {
                if (filter_var($part, FILTER_VALIDATE_IP)) {
                    return $part;
                }
            }
        }

        $candidate = (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        return filter_var($candidate, FILTER_VALIDATE_IP) ? $candidate : '0.0.0.0';
    }

    public static function userAgent(): string
    {
        return substr(trim((string)($_SERVER['HTTP_USER_AGENT'] ?? 'unknown')), 0, 500);
    }
}
