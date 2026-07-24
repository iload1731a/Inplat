<?php
declare(strict_types=1);
namespace App\Repositories;

use App\Libraries\Database;
use PDO;

final class UserNotificationsRepository
{
    public function all(int $userId, int $limit = 100): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, type, title, message, is_read, action_url, created_at
             FROM notifications
             WHERE user_id = :uid
             ORDER BY id DESC LIMIT :lim'
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
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

    public function markRead(int $userId, int $notificationId): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE notifications SET is_read = 1 WHERE id = :nid AND user_id = :uid'
        );
        $stmt->bindValue(':nid', $notificationId, PDO::PARAM_INT);
        $stmt->bindValue(':uid', $userId,         PDO::PARAM_INT);
        $stmt->execute();
    }

    public function markAllRead(int $userId): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE notifications SET is_read = 1 WHERE user_id = :uid AND is_read = 0'
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
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
}
