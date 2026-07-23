<?php
declare(strict_types=1);
namespace App\Repositories;

use App\Libraries\Database;
use PDO;

/**
 * NotificationsRepository
 *
 * Central database layer for the Notification Center:
 *  - Insert, read, mark-read, delete notifications
 *  - Notification preferences (per user, per category)
 *  - Notification dispatch log
 *  - Push-device registration
 *  - Unread-count for real-time badge
 */
final class NotificationsRepository
{
    // =========================================================================
    // NOTIFICATION CRUD
    // =========================================================================

    public function all(int $userId, int $limit = 200): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, type, title, message, channel, action_url, metadata, is_read, read_at, created_at
             FROM notifications
             WHERE user_id = :uid
             ORDER BY id DESC LIMIT :lim'
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', max(1, min(500, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /** Paginated list with optional type/read filter */
    public function paginated(int $userId, int $page, int $perPage, string $typeFilter, string $readFilter): array
    {
        $wheres = ['user_id = :uid'];
        $params = [':uid' => $userId];

        if ($typeFilter !== '' && $typeFilter !== 'all') {
            $wheres[] = 'type = :type';
            $params[':type'] = $typeFilter;
        }
        if ($readFilter === 'unread') {
            $wheres[] = 'is_read = 0';
        } elseif ($readFilter === 'read') {
            $wheres[] = 'is_read = 1';
        }

        $where  = implode(' AND ', $wheres);
        $offset = ($page - 1) * $perPage;

        $countStmt = Database::connection()->prepare("SELECT COUNT(*) FROM notifications WHERE {$where}");
        foreach ($params as $k => $v) {
            $countStmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();

        $stmt = Database::connection()->prepare(
            "SELECT id, type, title, message, channel, action_url, metadata, is_read, read_at, created_at
             FROM notifications WHERE {$where}
             ORDER BY id DESC LIMIT :lim OFFSET :off"
        );
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll() ?: [];

        return [
            'rows'       => $rows,
            'total'      => $total,
            'page'       => $page,
            'per_page'   => $perPage,
            'total_pages'=> (int)ceil($total / max(1, $perPage)),
        ];
    }

    public function unreadCount(int $userId): int
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = 0'
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    /** Count unread per category for badge breakdown */
    public function unreadByType(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT type, COUNT(*) AS cnt FROM notifications
             WHERE user_id = :uid AND is_read = 0
             GROUP BY type'
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll() ?: [];
        $map  = [];
        foreach ($rows as $r) {
            $map[(string)$r['type']] = (int)$r['cnt'];
        }
        return $map;
    }

    public function markRead(int $userId, int $notificationId): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE notifications SET is_read = 1, read_at = NOW()
             WHERE id = :nid AND user_id = :uid AND is_read = 0'
        );
        $stmt->bindValue(':nid', $notificationId, PDO::PARAM_INT);
        $stmt->bindValue(':uid', $userId,         PDO::PARAM_INT);
        $stmt->execute();
    }

    public function markAllRead(int $userId): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE notifications SET is_read = 1, read_at = NOW()
             WHERE user_id = :uid AND is_read = 0'
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
    }

    /** Mark all of a specific type as read */
    public function markTypeRead(int $userId, string $type): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE notifications SET is_read = 1, read_at = NOW()
             WHERE user_id = :uid AND type = :type AND is_read = 0'
        );
        $stmt->bindValue(':uid',  $userId, PDO::PARAM_INT);
        $stmt->bindValue(':type', $type);
        $stmt->execute();
    }

    public function delete(int $userId, int $notificationId): void
    {
        $stmt = Database::connection()->prepare(
            'DELETE FROM notifications WHERE id = :nid AND user_id = :uid'
        );
        $stmt->bindValue(':nid', $notificationId, PDO::PARAM_INT);
        $stmt->bindValue(':uid', $userId,         PDO::PARAM_INT);
        $stmt->execute();
    }

    public function deleteAllRead(int $userId): int
    {
        $stmt = Database::connection()->prepare(
            'DELETE FROM notifications WHERE user_id = :uid AND is_read = 1'
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
    }

    /** Insert a single in_app notification; returns new row ID */
    public function insert(
        int    $userId,
        string $type,
        string $title,
        string $message,
        string $channel   = 'in_app',
        string $actionUrl = '',
        array  $metadata  = []
    ): int {
        $stmt = Database::connection()->prepare(
            'INSERT INTO notifications (user_id, type, title, message, channel, action_url, metadata, is_read, created_at)
             VALUES (:uid, :type, :title, :message, :channel, :url, :meta, 0, NOW())'
        );
        $stmt->bindValue(':uid',     $userId,   PDO::PARAM_INT);
        $stmt->bindValue(':type',    $type);
        $stmt->bindValue(':title',   $title);
        $stmt->bindValue(':message', $message);
        $stmt->bindValue(':channel', $channel);
        $stmt->bindValue(':url',     $actionUrl);
        $stmt->bindValue(':meta',    $metadata !== [] ? json_encode($metadata) : null);
        $stmt->execute();
        return (int)Database::connection()->lastInsertId();
    }

    // =========================================================================
    // NOTIFICATION PREFERENCES
    // =========================================================================

    /** Return all preference rows for a user as category => prefs array */
    public function getPreferences(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT category, notify_in_app, notify_email, notify_push, notify_sms
             FROM notification_preferences WHERE user_id = :uid'
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $rows   = $stmt->fetchAll() ?: [];
        $result = [];
        foreach ($rows as $r) {
            $result[(string)$r['category']] = $r;
        }
        return $result;
    }

    /** Upsert a single category preference row */
    public function savePreference(int $userId, string $category, array $channels): void
    {
        $inApp = (int)(bool)($channels['in_app'] ?? 1);
        $email = (int)(bool)($channels['email']  ?? 1);
        $push  = (int)(bool)($channels['push']   ?? 0);
        $sms   = (int)(bool)($channels['sms']    ?? 0);

        $stmt = Database::connection()->prepare(
            'INSERT INTO notification_preferences (user_id, category, notify_in_app, notify_email, notify_push, notify_sms)
             VALUES (:uid, :cat, :ia, :em, :pu, :sm)
             ON DUPLICATE KEY UPDATE
               notify_in_app = VALUES(notify_in_app),
               notify_email  = VALUES(notify_email),
               notify_push   = VALUES(notify_push),
               notify_sms    = VALUES(notify_sms),
               updated_at    = NOW()'
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':cat', $category);
        $stmt->bindValue(':ia',  $inApp, PDO::PARAM_INT);
        $stmt->bindValue(':em',  $email, PDO::PARAM_INT);
        $stmt->bindValue(':pu',  $push,  PDO::PARAM_INT);
        $stmt->bindValue(':sm',  $sms,   PDO::PARAM_INT);
        $stmt->execute();
    }

    // =========================================================================
    // NOTIFICATION DISPATCH LOG
    // =========================================================================

    public function logDispatch(
        ?int   $notificationId,
        int    $userId,
        string $type,
        string $channel,
        string $title,
        string $status,
        string $error = ''
    ): void {
        $stmt = Database::connection()->prepare(
            'INSERT INTO notification_log
               (notification_id, user_id, type, channel, title, status, error_message, sent_at, created_at)
             VALUES (:nid, :uid, :type, :ch, :title, :status, :err,
                     CASE WHEN :status2 = \'sent\' THEN NOW() ELSE NULL END, NOW())'
        );
        $stmt->bindValue(':nid',     $notificationId, $notificationId !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':uid',     $userId,  PDO::PARAM_INT);
        $stmt->bindValue(':type',    $type);
        $stmt->bindValue(':ch',      $channel);
        $stmt->bindValue(':title',   $title);
        $stmt->bindValue(':status',  $status);
        $stmt->bindValue(':status2', $status);
        $stmt->bindValue(':err',     $error ?: null);
        $stmt->execute();
    }

    // =========================================================================
    // PUSH DEVICE REGISTRATION
    // =========================================================================

    public function upsertPushDevice(int $userId, string $token, string $platform): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO push_notification_devices (user_id, device_token, platform, is_active, last_used_at)
             VALUES (:uid, :tok, :plat, 1, NOW())
             ON DUPLICATE KEY UPDATE
               user_id = VALUES(user_id),
               platform = VALUES(platform),
               is_active = 1,
               last_used_at = NOW()'
        );
        $stmt->bindValue(':uid',  $userId, PDO::PARAM_INT);
        $stmt->bindValue(':tok',  $token);
        $stmt->bindValue(':plat', $platform);
        $stmt->execute();
    }

    public function deactivatePushDevice(int $userId, string $token): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE push_notification_devices SET is_active = 0
             WHERE user_id = :uid AND device_token = :tok'
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':tok', $token);
        $stmt->execute();
    }

    public function activePushDevices(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT device_token, platform FROM push_notification_devices
             WHERE user_id = :uid AND is_active = 1'
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    // =========================================================================
    // ANNOUNCEMENTS
    // =========================================================================

    public function activeAnnouncements(int $limit = 20): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, title, body, category, is_pinned, published_at
             FROM announcements
             WHERE is_published = 1 AND (published_at IS NULL OR published_at <= NOW())
             ORDER BY is_pinned DESC, published_at DESC LIMIT :lim'
        );
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function markAnnouncementRead(int $userId, int $announcementId): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT IGNORE INTO announcement_reads (user_id, announcement_id)
             VALUES (:uid, :aid)'
        );
        $stmt->bindValue(':uid', $userId,         PDO::PARAM_INT);
        $stmt->bindValue(':aid', $announcementId, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function unreadAnnouncementCount(int $userId): int
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM announcements a
             WHERE a.is_published = 1
               AND (a.published_at IS NULL OR a.published_at <= NOW())
               AND NOT EXISTS (
                 SELECT 1 FROM announcement_reads ar
                 WHERE ar.user_id = :uid AND ar.announcement_id = a.id
               )'
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    // =========================================================================
    // USER NOTIFICATION STATS
    // =========================================================================

    public function stats30d(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT
               COUNT(*) AS total,
               SUM(is_read = 0) AS unread,
               SUM(type = \'order_filled\') AS order_count,
               SUM(type IN (\'deposit_credited\',\'withdrawal_processed\')) AS finance_count,
               SUM(type IN (\'security_alert\',\'login_alert\',\'new_device\')) AS security_count,
               SUM(type IN (\'kyc_update\',\'system\',\'announcement\',\'admin_notice\')) AS system_count
             FROM notifications
             WHERE user_id = :uid AND created_at >= NOW() - INTERVAL 30 DAY'
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch() ?: [];
    }

    public function recentByDay(int $userId, int $days = 30): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT DATE(created_at) AS day, COUNT(*) AS cnt
             FROM notifications
             WHERE user_id = :uid AND created_at >= NOW() - INTERVAL :days DAY
             GROUP BY day ORDER BY day'
        );
        $stmt->bindValue(':uid',  $userId, PDO::PARAM_INT);
        $stmt->bindValue(':days', $days,   PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }
}
