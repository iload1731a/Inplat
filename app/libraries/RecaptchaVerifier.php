<?php

declare(strict_types=1);

namespace App\Libraries;

final class RecaptchaVerifier
{
    public function verify(string $token, string $ipAddress): array
    {
        if (!(bool)config('app.recaptcha_enabled', false)) {
            return ['ok' => true];
        }

        $secret = trim((string)config('app.recaptcha_secret_key', ''));
        if ($secret === '' || $token === '') {
            return ['ok' => false, 'message' => 'Please complete reCAPTCHA verification'];
        }

        $payload = http_build_query([
            'secret' => $secret,
            'response' => $token,
            'remoteip' => $ipAddress,
        ]);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $payload,
                'timeout' => 5,
            ],
        ]);

        $verifyUrl = (string)config('app.recaptcha_verify_url', 'https://www.google.com/recaptcha/api/siteverify');
        $raw = @file_get_contents($verifyUrl, false, $context);
        if (!is_string($raw) || $raw === '') {
            return ['ok' => false, 'message' => 'reCAPTCHA verification failed'];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !($decoded['success'] ?? false)) {
            return ['ok' => false, 'message' => 'Please complete reCAPTCHA verification'];
        }

        return ['ok' => true];
    }
}
