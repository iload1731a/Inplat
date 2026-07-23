<?php
declare(strict_types=1);
namespace App\Libraries;

use App\Libraries\Database;
use PDO;

/**
 * EmailService
 *
 * Renders HTML email templates stored in the email_templates table and
 * dispatches via PHP's built-in mail() (SMTP relay / sendmail).
 * Drop-in placeholders in subject and body_html use {{VAR}} syntax.
 */
final class EmailService
{
    private static ?self $instance = null;

    private function __construct() {}

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    // -------------------------------------------------------------------------
    // Public dispatch helpers
    // -------------------------------------------------------------------------

    /**
     * Send using a stored email_template record.
     *
     * @param string   $toEmail      Recipient e-mail address
     * @param string   $templateKey  Value of email_templates.template_key
     * @param array    $vars         Placeholder replacements: ['NAME' => 'Alice', ...]
     * @return bool
     */
    public function sendTemplate(string $toEmail, string $templateKey, array $vars = []): bool
    {
        $tpl = $this->fetchTemplate($templateKey);
        if ($tpl === null) {
            $this->log('warn', "Email template not found: {$templateKey}");
            return false;
        }

        $subject = $this->replacePlaceholders((string)$tpl['subject'], $vars);
        $body    = $this->replacePlaceholders((string)$tpl['body_html'], $vars);

        return $this->sendHtml($toEmail, $subject, $body);
    }

    /**
     * Send a plain-text/HTML message without a stored template.
     */
    public function sendHtml(string $toEmail, string $subject, string $htmlBody): bool
    {
        if (filter_var($toEmail, FILTER_VALIDATE_EMAIL) === false) {
            $this->log('warn', "Invalid email address: {$toEmail}");
            return false;
        }

        $fromHost = $this->fromHost();
        $fromName = (string)config('app.name', 'Platform');
        $boundary = 'MP_' . bin2hex(random_bytes(8));
        $fromAddr = 'no-reply@' . $fromHost;

        $headers = implode("\r\n", [
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            'From: ' . $fromName . ' <' . $fromAddr . '>',
            'X-Mailer: PHP/' . PHP_VERSION,
        ]);

        $plain = strip_tags($htmlBody);
        $plain = preg_replace('/[ \t]+/', ' ', $plain) ?? $plain;
        $plain = preg_replace('/\n{3,}/', "\n\n", $plain) ?? $plain;

        $body  = "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
        $body .= quoted_printable_encode($plain) . "\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
        $body .= quoted_printable_encode($htmlBody) . "\r\n";
        $body .= "--{$boundary}--";

        $sent = mail($toEmail, $subject, $body, $headers);
        if ($sent === false) {
            $err = error_get_last();
            $this->log('error', 'mail() failed for ' . $toEmail . ': ' . ($err['message'] ?? 'unknown error'));
        }
        $this->log($sent ? 'info' : 'error', sprintf(
            'Email %s to %s subject=%s',
            $sent ? 'sent' : 'FAILED',
            $toEmail,
            $subject
        ));

        return $sent;
    }

    // -------------------------------------------------------------------------
    // Template helpers
    // -------------------------------------------------------------------------

    public function fetchTemplate(string $key): ?array
    {
        try {
            $stmt = Database::connection()->prepare(
                'SELECT id, template_key, subject, body_html, is_active
                 FROM email_templates WHERE template_key = :k LIMIT 1'
            );
            $stmt->bindValue(':k', $key);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return ($row !== false && (bool)$row['is_active']) ? $row : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function replacePlaceholders(string $text, array $vars): string
    {
        foreach ($vars as $key => $value) {
            $text = str_replace('{{' . strtoupper((string)$key) . '}}', (string)$value, $text);
        }
        return $text;
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Send a test email using a specific SMTP configuration row.
     * Returns ['ok' => bool, 'message' => string].
     */
    public function sendSmtpTest(array $config, string $toEmail): array
    {
        $host    = trim((string)($config['host'] ?? ''));
        $port    = (int)($config['port'] ?? 587);
        $enc     = strtolower(trim((string)($config['encryption'] ?? 'tls')));
        $user    = trim((string)($config['username'] ?? ''));
        $pass    = trim((string)($config['password'] ?? ''));
        $from    = trim((string)($config['from_email'] ?? ''));
        $fromName = trim((string)($config['from_name'] ?? 'Trading Platform'));

        if ($host === '') {
            return ['ok' => false, 'message' => 'SMTP host is not configured.'];
        }

        // Build SMTP prefix for stream_socket_client.
        // 'ssl'/'tls' = implicit TLS (direct encrypted connection, typically port 465) — use ssl:// prefix.
        // 'starttls'  = explicit TLS upgrade (plain TCP then STARTTLS command, typically port 587) — no prefix.
        $prefix = match($enc) {
            'ssl', 'tls' => 'ssl://',
            'starttls'   => '',
            default      => '',
        };

        $errno  = 0;
        $errstr = '';
        $socket = @stream_socket_client(
            $prefix . $host . ':' . $port,
            $errno,
            $errstr,
            10,
            STREAM_CLIENT_CONNECT
        );

        if ($socket === false) {
            return ['ok' => false, 'message' => "Connection failed: $errstr ($errno)"];
        }

        try {
            $read = fgets($socket, 512);
            if (substr($read, 0, 3) !== '220') {
                return ['ok' => false, 'message' => "Unexpected greeting: $read"];
            }

            $domain = $this->fromHost();
            fwrite($socket, "EHLO $domain\r\n");
            $ehloResp = '';
            while (true) {
                $line = fgets($socket, 512);
                if ($line === false) { break; }
                $ehloResp .= $line;
                if (isset($line[3]) && $line[3] === ' ') { break; }
            }

            // STARTTLS upgrade only when encryption mode is explicitly 'starttls'
            if ($enc === 'starttls') {
                fwrite($socket, "STARTTLS\r\n");
                $tlsResp = fgets($socket, 512);
                if (substr($tlsResp, 0, 3) !== '220') {
                    return ['ok' => false, 'message' => "STARTTLS failed: $tlsResp"];
                }
                // Enforce TLS 1.2+ to prevent downgrade attacks
                $cryptoMethod = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
                    $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
                }
                stream_socket_enable_crypto($socket, true, $cryptoMethod);
                fwrite($socket, "EHLO $domain\r\n");
                while (true) {
                    $line = fgets($socket, 512);
                    if ($line === false) { break; }
                    if (isset($line[3]) && $line[3] === ' ') { break; }
                }
            }

            // AUTH LOGIN
            if ($user !== '') {
                fwrite($socket, "AUTH LOGIN\r\n");
                $authResp = fgets($socket, 512);
                if (substr($authResp, 0, 3) !== '334') {
                    return ['ok' => false, 'message' => "AUTH failed: $authResp"];
                }
                fwrite($socket, base64_encode($user) . "\r\n");
                fgets($socket, 512); // 334 prompt
                fwrite($socket, base64_encode($pass) . "\r\n");
                $authOk = fgets($socket, 512);
                if (substr($authOk, 0, 3) !== '235') {
                    return ['ok' => false, 'message' => "Authentication failed: $authOk"];
                }
            }

            // MAIL FROM
            fwrite($socket, "MAIL FROM:<$from>\r\n");
            $mfResp = fgets($socket, 512);
            if (substr($mfResp, 0, 3) !== '250') {
                return ['ok' => false, 'message' => "MAIL FROM rejected: $mfResp"];
            }

            // RCPT TO
            fwrite($socket, "RCPT TO:<$toEmail>\r\n");
            $rtResp = fgets($socket, 512);
            if (substr($rtResp, 0, 3) !== '250') {
                return ['ok' => false, 'message' => "RCPT TO rejected: $rtResp"];
            }

            // DATA
            fwrite($socket, "DATA\r\n");
            fgets($socket, 512); // 354

            $headers = "From: $fromName <$from>\r\nTo: $toEmail\r\nSubject: SMTP Test Email\r\nContent-Type: text/plain\r\n\r\n";
            $body    = "This is a test email sent from the Trading Platform SMTP Configuration.\r\nDate: " . date('r') . "\r\n";
            fwrite($socket, $headers . $body . "\r\n.\r\n");
            $dataResp = fgets($socket, 512);
            if (substr($dataResp, 0, 3) !== '250') {
                return ['ok' => false, 'message' => "Message delivery failed: $dataResp"];
            }

            fwrite($socket, "QUIT\r\n");
        } finally {
            fclose($socket);
        }

        return ['ok' => true, 'message' => 'Test email sent successfully to ' . $toEmail];
    }

    private function fromHost(): string
    {
        $url  = (string)config('app.url', 'http://localhost');
        $host = parse_url($url, PHP_URL_HOST);
        return (is_string($host) && $host !== '') ? $host : 'localhost';
    }

    private function log(string $level, string $message): void
    {
        $logFile = (string)config('app.log_file', storage_path('logs/app.log'));
        $line    = sprintf("[%s] [EmailService] [%s] %s%s", date('c'), strtoupper($level), $message, PHP_EOL);
        @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }
}
