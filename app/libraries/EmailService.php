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

        $sent = @mail($toEmail, $subject, $body, $headers);
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
