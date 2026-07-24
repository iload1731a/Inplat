<?php
declare(strict_types=1);
namespace App\Repositories;

use App\Libraries\Database;
use PDO;

/**
 * AdminNotificationsRepository
 *
 * Database layer for admin Notification Center management:
 *  - Platform-wide notification KPIs and trend data
 *  - Full dispatch log with filtering / pagination
 *  - Broadcast campaign CRUD
 *  - Announcement CRUD (admin side)
 *  - Email template management
 *  - Recipient resolution for bulk sends
 */
final class AdminNotificationsRepository
{
    // =========================================================================
    // KPI / DASHBOARD
    // =========================================================================

    public function kpis(): array
    {
        $stmt = Database::connection()->query(
            'SELECT
               COUNT(*) AS total_sent,
               SUM(is_read = 0) AS total_unread,
               SUM(created_at >= NOW() - INTERVAL 24 HOUR) AS sent_24h,
               SUM(created_at >= NOW() - INTERVAL 7 DAY)   AS sent_7d
             FROM notifications'
        );
        $row = $stmt->fetch() ?: [];

        $logStmt = Database::connection()->query(
            'SELECT
               SUM(status = \'sent\')   AS log_sent,
               SUM(status = \'failed\') AS log_failed,
               SUM(status = \'pending\') AS log_pending
             FROM notification_log
             WHERE created_at >= NOW() - INTERVAL 7 DAY'
        );
        $logRow = $logStmt->fetch() ?: [];

        $broadcastStmt = Database::connection()->query(
            'SELECT COUNT(*) AS broadcasts_30d, SUM(recipient_count) AS broadcast_recipients
             FROM notification_broadcasts
             WHERE created_at >= NOW() - INTERVAL 30 DAY'
        );
        $bcRow = $broadcastStmt->fetch() ?: [];

        return array_merge(
            (array)$row,
            (array)$logRow,
            (array)$bcRow
        );
    }

    /** 30-day daily send volume */
    public function dailyVolume(int $days = 30): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT DATE(created_at) AS day, COUNT(*) AS cnt
             FROM notifications
             WHERE created_at >= NOW() - INTERVAL :days DAY
             GROUP BY day ORDER BY day'
        );
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /** Channel breakdown totals */
    public function channelBreakdown(): array
    {
        $stmt = Database::connection()->query(
            'SELECT channel, COUNT(*) AS cnt
             FROM notifications
             GROUP BY channel ORDER BY cnt DESC'
        );
        return $stmt->fetchAll() ?: [];
    }

    /** Type breakdown totals */
    public function typeBreakdown(): array
    {
        $stmt = Database::connection()->query(
            'SELECT type, COUNT(*) AS cnt
             FROM notifications
             GROUP BY type ORDER BY cnt DESC LIMIT 20'
        );
        return $stmt->fetchAll() ?: [];
    }

    /** Top 10 users by notification count in last 30 days */
    public function topRecipients(int $days = 30): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT n.user_id, u.email, u.username, COUNT(*) AS cnt
             FROM notifications n
             JOIN users u ON u.id = n.user_id
             WHERE n.created_at >= NOW() - INTERVAL :days DAY
             GROUP BY n.user_id ORDER BY cnt DESC LIMIT 10'
        );
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    // =========================================================================
    // NOTIFICATION LOG (admin history view)
    // =========================================================================

    public function logList(array $filters, int $page, int $perPage = 50): array
    {
        $wheres = [];
        $params = [];

        if (!empty($filters['user_id'])) {
            $wheres[] = 'nl.user_id = :uid';
            $params[':uid'] = (int)$filters['user_id'];
        }
        if (!empty($filters['channel'])) {
            $wheres[] = 'nl.channel = :ch';
            $params[':ch'] = $filters['channel'];
        }
        if (!empty($filters['status'])) {
            $wheres[] = 'nl.status = :st';
            $params[':st'] = $filters['status'];
        }
        if (!empty($filters['type'])) {
            $wheres[] = 'nl.type = :type';
            $params[':type'] = $filters['type'];
        }
        if (!empty($filters['date_from'])) {
            $wheres[] = 'nl.created_at >= :df';
            $params[':df'] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $wheres[] = 'nl.created_at <= :dt';
            $params[':dt'] = $filters['date_to'] . ' 23:59:59';
        }

        $where  = $wheres !== [] ? 'WHERE ' . implode(' AND ', $wheres) : '';
        $offset = ($page - 1) * $perPage;

        $countStmt = Database::connection()->prepare(
            "SELECT COUNT(*) FROM notification_log nl {$where}"
        );
        foreach ($params as $k => $v) {
            $countStmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();

        $stmt = Database::connection()->prepare(
            "SELECT nl.id, nl.user_id, u.email AS user_email, u.username,
                    nl.type, nl.channel, nl.title, nl.status, nl.error_message,
                    nl.sent_at, nl.created_at
             FROM notification_log nl
             LEFT JOIN users u ON u.id = nl.user_id
             {$where}
             ORDER BY nl.id DESC LIMIT :lim OFFSET :off"
        );
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset,  PDO::PARAM_INT);
        $stmt->execute();

        return [
            'rows'        => $stmt->fetchAll() ?: [],
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => (int)ceil($total / max(1, $perPage)),
        ];
    }

    /** Export up to 5000 rows as array for CSV streaming */
    public function logExport(array $filters): array
    {
        $wheres = [];
        $params = [];
        if (!empty($filters['date_from'])) {
            $wheres[] = 'nl.created_at >= :df';
            $params[':df'] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $wheres[] = 'nl.created_at <= :dt';
            $params[':dt'] = $filters['date_to'] . ' 23:59:59';
        }
        if (!empty($filters['channel'])) {
            $wheres[] = 'nl.channel = :ch';
            $params[':ch'] = $filters['channel'];
        }
        if (!empty($filters['status'])) {
            $wheres[] = 'nl.status = :st';
            $params[':st'] = $filters['status'];
        }

        $where = $wheres !== [] ? 'WHERE ' . implode(' AND ', $wheres) : '';
        $stmt  = Database::connection()->prepare(
            "SELECT nl.id, nl.user_id, u.email AS user_email,
                    nl.type, nl.channel, nl.title, nl.status,
                    nl.error_message, nl.sent_at, nl.created_at
             FROM notification_log nl
             LEFT JOIN users u ON u.id = nl.user_id
             {$where}
             ORDER BY nl.id DESC LIMIT 5000"
        );
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    // =========================================================================
    // BROADCAST CAMPAIGNS
    // =========================================================================

    public function broadcastList(int $page = 1, int $perPage = 30): array
    {
        $offset = ($page - 1) * $perPage;
        $total  = (int)Database::connection()->query(
            'SELECT COUNT(*) FROM notification_broadcasts'
        )->fetchColumn();

        $stmt = Database::connection()->prepare(
            'SELECT nb.id, nb.audience, nb.channel, nb.type, nb.title, nb.message,
                    nb.recipient_count, nb.sent_count, nb.failed_count, nb.status,
                    nb.created_at, nb.completed_at,
                    au.username AS admin_username
             FROM notification_broadcasts nb
             LEFT JOIN admin_users au ON au.id = nb.admin_id
             ORDER BY nb.id DESC LIMIT :lim OFFSET :off'
        );
        $stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset,  PDO::PARAM_INT);
        $stmt->execute();

        return [
            'rows'        => $stmt->fetchAll() ?: [],
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => (int)ceil($total / max(1, $perPage)),
        ];
    }

    public function createBroadcast(
        int    $adminId,
        string $audience,
        string $channel,
        string $type,
        string $title,
        string $message,
        int    $recipientCount
    ): int {
        $stmt = Database::connection()->prepare(
            'INSERT INTO notification_broadcasts
               (admin_id, audience, channel, type, title, message, recipient_count, status, created_at)
             VALUES (:aid, :aud, :ch, :type, :title, :msg, :rc, \'pending\', NOW())'
        );
        $stmt->bindValue(':aid',   $adminId, PDO::PARAM_INT);
        $stmt->bindValue(':aud',   $audience);
        $stmt->bindValue(':ch',    $channel);
        $stmt->bindValue(':type',  $type);
        $stmt->bindValue(':title', $title);
        $stmt->bindValue(':msg',   $message);
        $stmt->bindValue(':rc',    $recipientCount, PDO::PARAM_INT);
        $stmt->execute();
        return (int)Database::connection()->lastInsertId();
    }

    public function updateBroadcastStats(int $broadcastId, int $sent, int $failed): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE notification_broadcasts
             SET sent_count = :sent, failed_count = :failed,
                 status = \'completed\', completed_at = NOW()
             WHERE id = :id'
        );
        $stmt->bindValue(':sent',   $sent,        PDO::PARAM_INT);
        $stmt->bindValue(':failed', $failed,      PDO::PARAM_INT);
        $stmt->bindValue(':id',     $broadcastId, PDO::PARAM_INT);
        $stmt->execute();
    }

    // =========================================================================
    // ANNOUNCEMENTS
    // =========================================================================

    public function announcementList(int $page = 1, int $perPage = 30): array
    {
        $offset = ($page - 1) * $perPage;
        $total  = (int)Database::connection()->query(
            'SELECT COUNT(*) FROM announcements'
        )->fetchColumn();

        $stmt = Database::connection()->prepare(
            'SELECT a.id, a.title, a.category, a.is_pinned, a.is_published,
                    a.published_at, a.created_at,
                    au.username AS created_by_name,
                    (SELECT COUNT(*) FROM announcement_reads ar WHERE ar.announcement_id = a.id) AS read_count
             FROM announcements a
             LEFT JOIN admin_users au ON au.id = a.created_by
             ORDER BY a.id DESC LIMIT :lim OFFSET :off'
        );
        $stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset,  PDO::PARAM_INT);
        $stmt->execute();

        return [
            'rows'        => $stmt->fetchAll() ?: [],
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => (int)ceil($total / max(1, $perPage)),
        ];
    }

    public function announcementById(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM announcements WHERE id = :id LIMIT 1'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    public function createAnnouncement(array $data, int $adminId): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO announcements
               (title, body, category, is_pinned, is_published, published_at, created_by, created_at)
             VALUES (:title, :body, :cat, :pinned, :pub, :pub_at, :admin, NOW())'
        );
        $stmt->bindValue(':title',  $data['title']);
        $stmt->bindValue(':body',   $data['body']);
        $stmt->bindValue(':cat',    $data['category'] ?? 'general');
        $stmt->bindValue(':pinned', (int)(bool)($data['is_pinned'] ?? false), PDO::PARAM_INT);
        $stmt->bindValue(':pub',    (int)(bool)($data['is_published'] ?? false), PDO::PARAM_INT);
        $stmt->bindValue(':pub_at', !empty($data['published_at']) ? $data['published_at'] : null);
        $stmt->bindValue(':admin',  $adminId, PDO::PARAM_INT);
        $stmt->execute();
        return (int)Database::connection()->lastInsertId();
    }

    public function updateAnnouncement(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE announcements
             SET title = :title, body = :body, category = :cat,
                 is_pinned = :pinned, is_published = :pub, published_at = :pub_at,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->bindValue(':title',  $data['title']);
        $stmt->bindValue(':body',   $data['body']);
        $stmt->bindValue(':cat',    $data['category'] ?? 'general');
        $stmt->bindValue(':pinned', (int)(bool)($data['is_pinned'] ?? false), PDO::PARAM_INT);
        $stmt->bindValue(':pub',    (int)(bool)($data['is_published'] ?? false), PDO::PARAM_INT);
        $stmt->bindValue(':pub_at', !empty($data['published_at']) ? $data['published_at'] : null);
        $stmt->bindValue(':id',     $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function deleteAnnouncement(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM announcements WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function toggleAnnouncementPublish(int $id): bool
    {
        $stmt = Database::connection()->prepare(
            'UPDATE announcements
             SET is_published = NOT is_published,
                 published_at = CASE WHEN is_published = 0 THEN NOW() ELSE published_at END
             WHERE id = :id'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = Database::connection()->prepare('SELECT is_published FROM announcements WHERE id = :id LIMIT 1');
        $row->bindValue(':id', $id, PDO::PARAM_INT);
        $row->execute();
        return (bool)$row->fetchColumn();
    }

    // =========================================================================
    // RECIPIENT RESOLUTION
    // =========================================================================

    /**
     * Resolve user rows (id + email) for a broadcast audience.
     *
     * @param string $audience  all | active | kyc_approved | new_users | custom_ids
     * @param array  $opts      ['user_ids' => [...]] for custom_ids
     */
    public function resolveRecipients(string $audience, array $opts = []): array
    {
        switch ($audience) {
            case 'all':
                $stmt = Database::connection()->query(
                    "SELECT id, email, username FROM users WHERE status != 'banned' LIMIT 50000"
                );
                return $stmt->fetchAll() ?: [];

            case 'active':
                $stmt = Database::connection()->query(
                    "SELECT id, email, username FROM users WHERE status = 'active' LIMIT 50000"
                );
                return $stmt->fetchAll() ?: [];

            case 'kyc_approved':
                $stmt = Database::connection()->query(
                    "SELECT u.id, u.email, u.username
                     FROM users u
                     WHERE u.kyc_status = 'approved'"
                );
                return $stmt->fetchAll() ?: [];

            case 'kyc_pending':
                $stmt = Database::connection()->query(
                    "SELECT u.id, u.email, u.username
                     FROM users u
                     WHERE u.kyc_status = 'pending'"
                );
                return $stmt->fetchAll() ?: [];

            case 'new_users':
                $stmt = Database::connection()->query(
                    "SELECT id, email, username FROM users
                     WHERE created_at >= NOW() - INTERVAL 30 DAY AND status != 'banned'"
                );
                return $stmt->fetchAll() ?: [];

            case 'custom_ids':
                $ids = array_filter(array_map('intval', (array)($opts['user_ids'] ?? [])));
                if ($ids === []) {
                    return [];
                }
                $ids = array_slice($ids, 0, 1000);
                $ph  = implode(',', array_fill(0, count($ids), '?'));
                $stmt = Database::connection()->prepare(
                    "SELECT id, email, username FROM users WHERE id IN ({$ph})"
                );
                $stmt->execute($ids);
                return $stmt->fetchAll() ?: [];

            default:
                return [];
        }
    }

    /** Batch insert notifications for multiple users */
    public function bulkInsertNotifications(
        array  $recipients,
        string $type,
        string $title,
        string $message,
        string $channel    = 'in_app',
        string $actionUrl  = ''
    ): int {
        if ($recipients === []) {
            return 0;
        }
        $db      = Database::connection();
        $chunks  = array_chunk($recipients, 500);
        $created = 0;
        foreach ($chunks as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '(?,?,?,?,?,?,0,NOW())'));
            $stmt = $db->prepare(
                "INSERT INTO notifications (user_id, type, title, message, channel, action_url, is_read, created_at)
                 VALUES {$placeholders}"
            );
            $flat = [];
            foreach ($chunk as $r) {
                $flat[] = (int)$r['id'];
                $flat[] = $type;
                $flat[] = $title;
                $flat[] = $message;
                $flat[] = $channel;
                $flat[] = $actionUrl;
            }
            $stmt->execute($flat);
            $created += $stmt->rowCount();
        }
        return $created;
    }

    // =========================================================================
    // EMAIL TEMPLATES
    // =========================================================================

    public function listEmailTemplates(): array
    {
        $stmt = Database::connection()->query(
            'SELECT id, template_key, subject, is_active, updated_at
             FROM email_templates ORDER BY template_key'
        );
        return $stmt->fetchAll() ?: [];
    }

    public function emailTemplateById(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM email_templates WHERE id = :id LIMIT 1'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    public function saveEmailTemplate(array $data, int $adminId): int
    {
        $templateId  = (int)($data['template_id'] ?? 0);
        $templateKey = trim((string)($data['template_key'] ?? ''));
        $subject     = trim((string)($data['subject']      ?? ''));
        $bodyHtml    = trim((string)($data['body_html']    ?? ''));
        $isActive    = (int)(bool)($data['is_active'] ?? 1);

        if ($templateId > 0) {
            $stmt = Database::connection()->prepare(
                'UPDATE email_templates
                 SET subject = :sub, body_html = :body, is_active = :act,
                     updated_by = :uid, updated_at = NOW()
                 WHERE id = :id'
            );
            $stmt->bindValue(':sub',  $subject);
            $stmt->bindValue(':body', $bodyHtml);
            $stmt->bindValue(':act',  $isActive, PDO::PARAM_INT);
            $stmt->bindValue(':uid',  $adminId,  PDO::PARAM_INT);
            $stmt->bindValue(':id',   $templateId, PDO::PARAM_INT);
            $stmt->execute();
            return $templateId;
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO email_templates (template_key, subject, body_html, is_active, updated_by, updated_at)
             VALUES (:key, :sub, :body, :act, :uid, NOW())'
        );
        $stmt->bindValue(':key',  $templateKey);
        $stmt->bindValue(':sub',  $subject);
        $stmt->bindValue(':body', $bodyHtml);
        $stmt->bindValue(':act',  $isActive, PDO::PARAM_INT);
        $stmt->bindValue(':uid',  $adminId,  PDO::PARAM_INT);
        $stmt->execute();
        return (int)Database::connection()->lastInsertId();
    }

    public function deleteEmailTemplate(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM email_templates WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    // =========================================================================
    // RECENT NOTIFICATIONS (admin overview)
    // =========================================================================

    public function recentNotifications(int $limit = 50): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT n.id, n.user_id, u.email AS user_email, u.username,
                    n.type, n.channel, n.title, n.is_read, n.created_at
             FROM notifications n
             LEFT JOIN users u ON u.id = n.user_id
             ORDER BY n.id DESC LIMIT :lim'
        );
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }
}
