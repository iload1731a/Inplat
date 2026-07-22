<?php

declare(strict_types=1);

namespace App\Libraries;

final class RecaptchaVerifier
{
    public function verify(string $token, string $ipAddress): array
    {
        if (!(bool)config('app.recaptcha_enabled', false)) {
            $this->log('reCAPTCHA skipped because RECAPTCHA_ENABLED is false');
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
        $errorMessage = null;
        set_error_handler(static function (int $severity, string $message) use (&$errorMessage): bool {
            $errorMessage = $message;
            return in_array($severity, [E_WARNING, E_NOTICE, E_USER_WARNING, E_USER_NOTICE, E_DEPRECATED, E_USER_DEPRECATED], true);
        });
        $raw = file_get_contents($verifyUrl, false, $context);
        restore_error_handler();

        if (!is_string($raw) || $raw === '') {
            if (is_string($errorMessage) && $errorMessage !== '') {
                $this->log('reCAPTCHA HTTP verification error: ' . $errorMessage);
            }
            return ['ok' => false, 'message' => 'reCAPTCHA verification failed'];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $this->log('reCAPTCHA response parse error: ' . json_last_error_msg());
            return ['ok' => false, 'message' => 'reCAPTCHA verification failed'];
        }

        if (!($decoded['success'] ?? false)) {
            return ['ok' => false, 'message' => 'Please complete reCAPTCHA verification'];
        }

        return ['ok' => true];
    }

    private function log(string $message): void
    {
        $line = '[' . date('c') . '] ' . $message . PHP_EOL;
        $logFile = (string)config('app.log_file');
        $logDir = dirname($logFile);
        if (!is_dir($logDir) && !mkdir($logDir, 0775, true) && !is_dir($logDir)) {
            error_log($line);
            return;
        }

        $written = file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
        if ($written === false) {
            error_log($line);
        }
    }
}
