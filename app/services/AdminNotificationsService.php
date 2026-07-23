<?php
declare(strict_types=1);
namespace App\Services;

use App\Libraries\EmailService;
use App\Libraries\RequestContext;
use App\Repositories\AdminNotificationsRepository;
use App\Repositories\AdminRepository;
use InvalidArgumentException;
use Throwable;

/**
 * AdminNotificationsService
 *
 * Business logic for the Admin Notification Center:
 *  - Dashboard bundle (KPIs, charts, recent)
 *  - Notification log with pagination and CSV export
 *  - Broadcast dispatch (multi-channel)
 *  - Announcement CRUD with publish toggle
 *  - Email template management
 */
final class AdminNotificationsService
{
    private readonly AdminNotificationsRepository $repo;
    private readonly EmailService                 $email;

    public function __construct(
        ?AdminNotificationsRepository $repo  = null,
        ?EmailService                 $email = null
    ) {
        $this->repo  = $repo  ?? new AdminNotificationsRepository();
        $this->email = $email ?? EmailService::instance();
    }

    // =========================================================================
    // DASHBOARD
    // =========================================================================

    public function dashboard(): array
    {
        try {
            $kpis            = $this->repo->kpis();
            $daily30         = $this->repo->dailyVolume(30);
            $channelBreakdown= $this->repo->channelBreakdown();
            $typeBreakdown   = $this->repo->typeBreakdown();
            $topRecipients   = $this->repo->topRecipients(30);
            $recent          = $this->repo->recentNotifications(30);
            $broadcasts      = $this->repo->broadcastList(1, 5);
        } catch (Throwable) {
            $kpis             = [];
            $daily30          = [];
            $channelBreakdown = [];
            $typeBreakdown    = [];
            $topRecipients    = [];
            $recent           = [];
            $broadcasts       = ['rows' => [], 'total' => 0, 'page' => 1, 'per_page' => 5, 'total_pages' => 0];
        }

        return compact(
            'kpis', 'daily30', 'channelBreakdown', 'typeBreakdown',
            'topRecipients', 'recent', 'broadcasts'
        );
    }

    // =========================================================================
    // NOTIFICATION LOG
    // =========================================================================

    public function historyList(array $filters, int $page): array
    {
        $page = max(1, $page);
        try {
            return $this->repo->logList($filters, $page, 50);
        } catch (Throwable) {
            return ['rows' => [], 'total' => 0, 'page' => 1, 'per_page' => 50, 'total_pages' => 0];
        }
    }

    public function exportCsv(array $filters): void
    {
        $rows = $this->repo->logExport($filters);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="notification_log_' . date('Ymd_His') . '.csv"');
        header('Cache-Control: no-cache, must-revalidate');

        $out = fopen('php://output', 'w');
        if ($out === false) {
            return;
        }
        fputcsv($out, ['ID','User ID','Email','Type','Channel','Title','Status','Error','Sent At','Created At']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['id'],
                $r['user_id'],
                $r['user_email'] ?? '',
                $r['type'],
                $r['channel'],
                $r['title'],
                $r['status'],
                $r['error_message'] ?? '',
                $r['sent_at']       ?? '',
                $r['created_at'],
            ]);
        }
        fclose($out);
    }

    // =========================================================================
    // BROADCAST
    // =========================================================================

    /**
     * Execute a broadcast notification send.
     *
     * @param int   $adminId
     * @param array $payload [audience, channel, type, title, message, user_ids, action_url]
     * @return array ['broadcast_id'=>int, 'recipients'=>int, 'sent'=>int, 'failed'=>int]
     */
    public function broadcast(int $adminId, array $payload): array
    {
        $audience  = trim((string)($payload['audience']   ?? 'all'));
        $channel   = trim((string)($payload['channel']    ?? 'in_app'));
        $type      = trim((string)($payload['type']       ?? 'admin_notice'));
        $title     = trim((string)($payload['title']      ?? ''));
        $message   = trim((string)($payload['message']    ?? ''));
        $actionUrl = trim((string)($payload['action_url'] ?? ''));

        $allowedAudiences = ['all','active','kyc_approved','kyc_pending','new_users','custom_ids'];
        $allowedChannels  = ['in_app','email','sms','push'];

        if (!in_array($audience, $allowedAudiences, true)) {
            throw new InvalidArgumentException('Invalid audience.');
        }
        if (!in_array($channel, $allowedChannels, true)) {
            throw new InvalidArgumentException('Invalid channel.');
        }
        if ($title === '' || $message === '') {
            throw new InvalidArgumentException('Title and message are required.');
        }

        $opts       = [];
        $rawUserIds = (string)($payload['user_ids'] ?? '');
        if ($audience === 'custom_ids' && $rawUserIds !== '') {
            $opts['user_ids'] = array_map('intval', explode(',', $rawUserIds));
        }

        $recipients = $this->repo->resolveRecipients($audience, $opts);
        if ($recipients === []) {
            throw new InvalidArgumentException('No recipients found for this audience.');
        }

        $broadcastId = $this->repo->createBroadcast(
            $adminId, $audience, $channel, $type, $title, $message, count($recipients)
        );

        $sent   = 0;
        $failed = 0;

        if ($channel === 'in_app' || $channel === 'push' || $channel === 'sms') {
            // Insert in_app notifications in bulk
            $created = $this->repo->bulkInsertNotifications(
                $recipients, $type, $title, $message, $channel, $actionUrl
            );
            $sent   = $created;
            $failed = count($recipients) - $created;
        } elseif ($channel === 'email') {
            foreach ($recipients as $recipient) {
                $toEmail = (string)($recipient['email'] ?? '');
                if ($toEmail === '') {
                    $failed++;
                    continue;
                }
                try {
                    $ok = $this->email->sendHtml(
                        $toEmail,
                        $title,
                        $this->buildEmailBody($title, $message, $actionUrl)
                    );
                    if ($ok) {
                        $sent++;
                    } else {
                        $failed++;
                    }
                } catch (Throwable) {
                    $failed++;
                }
            }
            // Also insert in_app records for email broadcasts
            $this->repo->bulkInsertNotifications(
                $recipients, $type, $title, $message, 'email', $actionUrl
            );
        }

        $this->repo->updateBroadcastStats($broadcastId, $sent, $failed);

        return [
            'broadcast_id' => $broadcastId,
            'recipients'   => count($recipients),
            'sent'         => $sent,
            'failed'       => $failed,
        ];
    }

    public function broadcastList(int $page): array
    {
        return $this->repo->broadcastList($page, 30);
    }

    // =========================================================================
    // ANNOUNCEMENTS
    // =========================================================================

    public function announcementList(int $page): array
    {
        return $this->repo->announcementList($page, 30);
    }

    public function announcementById(int $id): array
    {
        $row = $this->repo->announcementById($id);
        if ($row === null) {
            throw new InvalidArgumentException('Announcement not found.');
        }
        return $row;
    }

    public function createAnnouncement(int $adminId, array $payload): int
    {
        $title  = trim((string)($payload['title'] ?? ''));
        $body   = trim((string)($payload['body']  ?? ''));
        if ($title === '' || $body === '') {
            throw new InvalidArgumentException('Title and body are required.');
        }

        $data = [
            'title'        => $title,
            'body'         => $body,
            'category'     => $payload['category']     ?? 'general',
            'is_pinned'    => $payload['is_pinned']    ?? 0,
            'is_published' => $payload['is_published'] ?? 0,
            'published_at' => !empty($payload['published_at']) ? $payload['published_at'] : null,
        ];
        return $this->repo->createAnnouncement($data, $adminId);
    }

    public function updateAnnouncement(int $adminId, int $id, array $payload): void
    {
        $title = trim((string)($payload['title'] ?? ''));
        $body  = trim((string)($payload['body']  ?? ''));
        if ($title === '' || $body === '') {
            throw new InvalidArgumentException('Title and body are required.');
        }

        $data = [
            'title'        => $title,
            'body'         => $body,
            'category'     => $payload['category']     ?? 'general',
            'is_pinned'    => $payload['is_pinned']    ?? 0,
            'is_published' => $payload['is_published'] ?? 0,
            'published_at' => !empty($payload['published_at']) ? $payload['published_at'] : null,
        ];
        $this->repo->updateAnnouncement($id, $data);
    }

    public function deleteAnnouncement(int $id): void
    {
        $this->repo->deleteAnnouncement($id);
    }

    public function toggleAnnouncementPublish(int $id): bool
    {
        return $this->repo->toggleAnnouncementPublish($id);
    }

    // =========================================================================
    // EMAIL TEMPLATES
    // =========================================================================

    public function templateList(): array
    {
        return $this->repo->listEmailTemplates();
    }

    public function templateById(int $id): array
    {
        $row = $this->repo->emailTemplateById($id);
        if ($row === null) {
            throw new InvalidArgumentException('Email template not found.');
        }
        return $row;
    }

    public function saveTemplate(int $adminId, array $payload): int
    {
        $subject  = trim((string)($payload['subject']  ?? ''));
        $bodyHtml = trim((string)($payload['body_html'] ?? ''));
        if ($subject === '' || $bodyHtml === '') {
            throw new InvalidArgumentException('Subject and body are required.');
        }

        $templateId  = (int)($payload['template_id'] ?? 0);
        $templateKey = trim((string)($payload['template_key'] ?? ''));
        if ($templateId === 0 && $templateKey === '') {
            throw new InvalidArgumentException('Template key is required for new templates.');
        }

        return $this->repo->saveEmailTemplate([
            'template_id'  => $templateId,
            'template_key' => $templateKey,
            'subject'      => $subject,
            'body_html'    => $bodyHtml,
            'is_active'    => (string)($payload['is_active'] ?? '1') === '1' ? 1 : 0,
        ], $adminId);
    }

    public function deleteTemplate(int $id): void
    {
        $this->repo->deleteEmailTemplate($id);
    }

    // =========================================================================
    // Helper
    // =========================================================================

    private function buildEmailBody(string $title, string $message, string $actionUrl): string
    {
        $platformName = (string)config('app.name', 'Trading Platform');
        $url          = htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8');
        $safeTitle    = htmlspecialchars($title,     ENT_QUOTES, 'UTF-8');
        $safeMsg      = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
        $btn          = $url ? "<p><a href=\"{$url}\" style=\"background:#38bdf8;color:#000;padding:10px 20px;border-radius:6px;text-decoration:none;font-weight:600;\">View Details</a></p>" : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"></head>
<body style="background:#0f172a;color:#e2e8f0;font-family:system-ui,sans-serif;padding:40px 20px;margin:0">
  <div style="max-width:600px;margin:0 auto;background:#1e293b;border-radius:12px;padding:32px;border:1px solid rgba(148,163,184,0.15)">
    <div style="font-size:22px;font-weight:700;color:#38bdf8;margin-bottom:24px">{$platformName}</div>
    <h2 style="font-size:18px;margin:0 0 16px;color:#f8fafc">{$safeTitle}</h2>
    <p style="color:#94a3b8;line-height:1.6;margin:0 0 20px">{$safeMsg}</p>
    {$btn}
    <hr style="border:none;border-top:1px solid rgba(148,163,184,0.15);margin:24px 0">
    <p style="font-size:12px;color:#64748b;margin:0">This message was sent by the {$platformName} admin team.</p>
  </div>
</body>
</html>
HTML;
    }
}
