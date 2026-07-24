<?php
declare(strict_types=1);
namespace App\Services;

use App\Libraries\EmailService;
use App\Repositories\NotificationsRepository;
use Throwable;

/**
 * NotificationsService
 *
 * Central notification dispatch service:
 *  - dispatch()  — insert in_app + optionally send email; logs every attempt
 *  - Preferences get/save
 *  - History (paginated)
 *  - Announcement tracking
 *  - Push device registration
 *  - User notification center data bundle
 */
final class NotificationsService
{
    private readonly NotificationsRepository $repo;
    private readonly EmailService            $email;

    public function __construct(
        ?NotificationsRepository $repo  = null,
        ?EmailService            $email = null
    ) {
        $this->repo  = $repo  ?? new NotificationsRepository();
        $this->email = $email ?? EmailService::instance();
    }

    // =========================================================================
    // DISPATCH
    // =========================================================================

    /**
     * Dispatch a notification to a single user.
     *
     * Checks preferences before dispatching any channel.
     * Always inserts an in_app row if notify_in_app preference allows.
     * Sends email if notify_email preference allows and $sendEmail is true.
     *
     * @param int    $userId
     * @param string $type       e.g. 'order_filled', 'security_alert'
     * @param string $title
     * @param string $message
     * @param array  $opts       [
     *   'action_url'  => string,
     *   'metadata'    => array,
     *   'send_email'  => bool,   (default false — caller must opt-in)
     *   'to_email'    => string, (override recipient email)
     *   'email_template' => string,
     *   'template_vars'  => array,
     *   'channel'     => string, (in_app|email|sms|push)
     * ]
     * @return int Inserted notification ID (0 if skipped by prefs)
     */
    public function dispatch(int $userId, string $type, string $title, string $message, array $opts = []): int
    {
        $actionUrl = (string)($opts['action_url']  ?? '');
        $metadata  = (array) ($opts['metadata']    ?? []);
        $channel   = (string)($opts['channel']     ?? 'in_app');
        $sendEmail = (bool)  ($opts['send_email']  ?? false);

        $prefs = $this->getPreferencesMap($userId);

        // In-app insert
        $notificationId = 0;
        if ((bool)($prefs[$this->categoryOf($type)]['notify_in_app'] ?? 1)) {
            try {
                $notificationId = $this->repo->insert(
                    $userId, $type, $title, $message, $channel, $actionUrl, $metadata
                );
                $this->repo->logDispatch(
                    $notificationId, $userId, $type, 'in_app', $title, 'sent'
                );
            } catch (Throwable $e) {
                $this->repo->logDispatch(null, $userId, $type, 'in_app', $title, 'failed', $e->getMessage());
            }
        }

        // Email dispatch
        if ($sendEmail && (bool)($prefs[$this->categoryOf($type)]['notify_email'] ?? 1)) {
            $toEmail  = (string)($opts['to_email'] ?? '');
            $tplKey   = (string)($opts['email_template'] ?? '');
            $tplVars  = (array) ($opts['template_vars']  ?? []);

            if ($toEmail !== '') {
                $sent = false;
                try {
                    if ($tplKey !== '') {
                        $sent = $this->email->sendTemplate($toEmail, $tplKey, $tplVars);
                    } else {
                        $sent = $this->email->sendHtml($toEmail, $title, $this->buildDefaultEmailBody($title, $message, $actionUrl));
                    }
                } catch (Throwable $e) {
                    $sent = false;
                    $this->repo->logDispatch($notificationId ?: null, $userId, $type, 'email', $title, 'failed', $e->getMessage());
                }
                if ($sent) {
                    $this->repo->logDispatch($notificationId ?: null, $userId, $type, 'email', $title, 'sent');
                } else {
                    $this->repo->logDispatch($notificationId ?: null, $userId, $type, 'email', $title, 'failed');
                }
            }
        }

        return $notificationId;
    }

    // =========================================================================
    // NOTIFICATION CENTER DATA
    // =========================================================================

    public function centerData(int $userId): array
    {
        try {
            $stats30d      = $this->repo->stats30d($userId);
            $recentByDay   = $this->repo->recentByDay($userId, 30);
            $unreadByType  = $this->repo->unreadByType($userId);
            $unreadCount   = $this->repo->unreadCount($userId);
            $announcements = $this->repo->activeAnnouncements(10);
            $unreadAnnounce= $this->repo->unreadAnnouncementCount($userId);
        } catch (Throwable) {
            $stats30d      = [];
            $recentByDay   = [];
            $unreadByType  = [];
            $unreadCount   = 0;
            $announcements = [];
            $unreadAnnounce= 0;
        }

        return compact('stats30d', 'recentByDay', 'unreadByType', 'unreadCount',
                       'announcements', 'unreadAnnounce');
    }

    /** Return paginated notification list with optional filters */
    public function history(int $userId, int $page, int $perPage, string $type, string $readFilter): array
    {
        $page    = max(1, $page);
        $perPage = max(10, min(100, $perPage));
        try {
            return $this->repo->paginated($userId, $page, $perPage, $type, $readFilter);
        } catch (Throwable) {
            return ['rows' => [], 'total' => 0, 'page' => 1, 'per_page' => $perPage, 'total_pages' => 0];
        }
    }

    // =========================================================================
    // MARK READ / DELETE
    // =========================================================================

    public function markRead(int $userId, int $id): void
    {
        $this->repo->markRead($userId, $id);
    }

    public function markAllRead(int $userId): void
    {
        $this->repo->markAllRead($userId);
    }

    public function markTypeRead(int $userId, string $type): void
    {
        $this->repo->markTypeRead($userId, $type);
    }

    public function delete(int $userId, int $id): void
    {
        $this->repo->delete($userId, $id);
    }

    public function deleteAllRead(int $userId): int
    {
        return $this->repo->deleteAllRead($userId);
    }

    public function unreadCount(int $userId): int
    {
        return $this->repo->unreadCount($userId);
    }

    // =========================================================================
    // PREFERENCES
    // =========================================================================

    public static function defaultCategories(): array
    {
        return [
            'order_filled'        => ['label' => 'Order Filled',          'icon' => 'fa-check-circle'],
            'order_cancelled'     => ['label' => 'Order Cancelled',       'icon' => 'fa-times-circle'],
            'deposit'             => ['label' => 'Deposit',               'icon' => 'fa-arrow-down'],
            'withdrawal'          => ['label' => 'Withdrawal',            'icon' => 'fa-arrow-up'],
            'security'            => ['label' => 'Security Alerts',       'icon' => 'fa-shield-alt'],
            'kyc'                 => ['label' => 'KYC / Verification',    'icon' => 'fa-id-card'],
            'trade'               => ['label' => 'Trade Execution',       'icon' => 'fa-exchange-alt'],
            'price_alert'         => ['label' => 'Price Alerts',          'icon' => 'fa-bell'],
            'signal'              => ['label' => 'Trading Signals',       'icon' => 'fa-broadcast-tower'],
            'liquidation'         => ['label' => 'Liquidation Warnings',  'icon' => 'fa-exclamation-triangle'],
            'announcement'        => ['label' => 'System Announcements',  'icon' => 'fa-bullhorn'],
            'support'             => ['label' => 'Support Tickets',       'icon' => 'fa-headset'],
            'referral'            => ['label' => 'Referral Rewards',      'icon' => 'fa-gift'],
            'admin_notice'        => ['label' => 'Admin Notices',         'icon' => 'fa-info-circle'],
        ];
    }

    public function getPreferences(int $userId): array
    {
        $saved      = $this->repo->getPreferences($userId);
        $categories = self::defaultCategories();
        $result     = [];

        foreach ($categories as $cat => $meta) {
            $row = $saved[$cat] ?? [
                'notify_in_app' => 1,
                'notify_email'  => 1,
                'notify_push'   => 0,
                'notify_sms'    => 0,
            ];
            $result[$cat] = array_merge($meta, [
                'category'      => $cat,
                'notify_in_app' => (bool)$row['notify_in_app'],
                'notify_email'  => (bool)$row['notify_email'],
                'notify_push'   => (bool)$row['notify_push'],
                'notify_sms'    => (bool)$row['notify_sms'],
            ]);
        }

        return $result;
    }

    public function savePreferences(int $userId, array $payload): void
    {
        $categories = self::defaultCategories();
        foreach ($categories as $cat => $meta) {
            $channels = [
                'in_app' => isset($payload["in_app_{$cat}"]) ? 1 : 0,
                'email'  => isset($payload["email_{$cat}"])  ? 1 : 0,
                'push'   => isset($payload["push_{$cat}"])   ? 1 : 0,
                'sms'    => isset($payload["sms_{$cat}"])    ? 1 : 0,
            ];
            $this->repo->savePreference($userId, $cat, $channels);
        }
    }

    // =========================================================================
    // ANNOUNCEMENTS
    // =========================================================================

    public function markAnnouncementRead(int $userId, int $announcementId): void
    {
        $this->repo->markAnnouncementRead($userId, $announcementId);
    }

    // =========================================================================
    // PUSH DEVICE
    // =========================================================================

    public function registerPushDevice(int $userId, string $token, string $platform): void
    {
        $allowed = ['ios', 'android', 'web'];
        if (!in_array($platform, $allowed, true)) {
            $platform = 'web';
        }
        $this->repo->upsertPushDevice($userId, $token, $platform);
    }

    public function deregisterPushDevice(int $userId, string $token): void
    {
        $this->repo->deactivatePushDevice($userId, $token);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /** Map notification type to preference category */
    private function categoryOf(string $type): string
    {
        $map = [
            'order_filled'         => 'order_filled',
            'order_cancelled'      => 'order_cancelled',
            'order_rejected'       => 'order_cancelled',
            'deposit_credited'     => 'deposit',
            'deposit_pending'      => 'deposit',
            'withdrawal_processed' => 'withdrawal',
            'withdrawal_failed'    => 'withdrawal',
            'withdrawal_pending'   => 'withdrawal',
            'security_alert'       => 'security',
            'login_alert'          => 'security',
            'new_device'           => 'security',
            'password_changed'     => 'security',
            'kyc_update'           => 'kyc',
            'trade'                => 'trade',
            'price_alert'          => 'price_alert',
            'signal'               => 'signal',
            'liquidation'          => 'liquidation',
            'announcement'         => 'announcement',
            'support'              => 'support',
            'referral'             => 'referral',
            'admin_notice'         => 'admin_notice',
        ];
        return $map[$type] ?? 'admin_notice';
    }

    private function getPreferencesMap(int $userId): array
    {
        try {
            return $this->repo->getPreferences($userId);
        } catch (Throwable) {
            return [];
        }
    }

    private function buildDefaultEmailBody(string $title, string $message, string $actionUrl): string
    {
        $platformName = (string)config('app.name', 'Trading Platform');
        $url          = htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8');
        $safeTitle    = htmlspecialchars($title,     ENT_QUOTES, 'UTF-8');
        $safeMessage  = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
        $actionBtn    = $url ? "<p><a href=\"{$url}\" style=\"background:#38bdf8;color:#000;padding:10px 20px;border-radius:6px;text-decoration:none;font-weight:600;\">View Details</a></p>" : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="background:#0f172a;color:#e2e8f0;font-family:system-ui,sans-serif;padding:40px 20px;margin:0">
  <div style="max-width:600px;margin:0 auto;background:#1e293b;border-radius:12px;padding:32px;border:1px solid rgba(148,163,184,0.15)">
    <div style="font-size:22px;font-weight:700;color:#38bdf8;margin-bottom:24px">{$platformName}</div>
    <h2 style="font-size:18px;margin:0 0 16px;color:#f8fafc">{$safeTitle}</h2>
    <p style="color:#94a3b8;line-height:1.6;margin:0 0 20px">{$safeMessage}</p>
    {$actionBtn}
    <hr style="border:none;border-top:1px solid rgba(148,163,184,0.15);margin:24px 0">
    <p style="font-size:12px;color:#64748b;margin:0">You received this notification from {$platformName}. To manage your notification preferences, visit your account settings.</p>
  </div>
</body>
</html>
HTML;
    }
}
