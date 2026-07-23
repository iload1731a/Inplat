<?php
declare(strict_types=1);
namespace App\Repositories;

use App\Libraries\Database;
use PDO;

final class UserTicketsRepository
{
    public function tickets(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, subject, category, priority, status, created_at, updated_at
             FROM support_tickets
             WHERE user_id = :uid
             ORDER BY id DESC'
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function findTicket(int $userId, int $ticketId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, subject, category, priority, status, created_at, updated_at
             FROM support_tickets
             WHERE id = :tid AND user_id = :uid LIMIT 1'
        );
        $stmt->bindValue(':tid', $ticketId, PDO::PARAM_INT);
        $stmt->bindValue(':uid', $userId,   PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function messages(int $ticketId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, sender_type, sender_id, message, attachment_url, created_at
             FROM ticket_messages
             WHERE ticket_id = :tid
             ORDER BY id ASC'
        );
        $stmt->bindValue(':tid', $ticketId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function createTicket(int $userId, array $data): int
    {
        $stmt = Database::connection()->prepare(
            "INSERT INTO support_tickets
                (user_id, subject, category, priority, status, created_at, updated_at)
             VALUES (:uid, :subject, :category, :priority, 'open', NOW(), NOW())"
        );
        $stmt->bindValue(':uid',      $userId,              PDO::PARAM_INT);
        $stmt->bindValue(':subject',  $data['subject']);
        $stmt->bindValue(':category', $data['category']);
        $stmt->bindValue(':priority', $data['priority'] ?? 'normal');
        $stmt->execute();
        return (int)Database::connection()->lastInsertId();
    }

    public function addMessage(int $ticketId, int $senderId, string $senderType, string $message, ?string $attachmentUrl = null): int
    {
        $pdo  = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO ticket_messages
                (ticket_id, sender_type, sender_id, message, attachment_url, created_at)
             VALUES (:tid, :type, :sid, :msg, :attach, NOW())'
        );
        $stmt->bindValue(':tid',    $ticketId,     PDO::PARAM_INT);
        $stmt->bindValue(':type',   $senderType);
        $stmt->bindValue(':sid',    $senderId,     PDO::PARAM_INT);
        $stmt->bindValue(':msg',    $message);
        $stmt->bindValue(':attach', $attachmentUrl);
        $stmt->execute();
        $id = (int)$pdo->lastInsertId();
        // bump updated_at on ticket
        $pdo->prepare('UPDATE support_tickets SET updated_at = NOW() WHERE id = :tid')
            ->execute([':tid' => $ticketId]);
        return $id;
    }

    public function closeTicket(int $userId, int $ticketId): void
    {
        $stmt = Database::connection()->prepare(
            "UPDATE support_tickets
             SET status = 'closed', closed_at = IF(closed_at IS NULL, NOW(), closed_at), updated_at = NOW()
             WHERE id = :tid AND user_id = :uid
               AND status NOT IN ('resolved','closed')"
        );
        $stmt->bindValue(':tid', $ticketId, PDO::PARAM_INT);
        $stmt->bindValue(':uid', $userId,   PDO::PARAM_INT);
        $stmt->execute();
    }

    public function addAttachment(array $data): int
    {
        $pdo  = Database::connection();
        $stmt = $pdo->prepare(
            "INSERT INTO ticket_attachments
                (ticket_id, message_id, uploader_type, uploader_id,
                 original_name, stored_name, file_url, mime_type, file_size, created_at)
             VALUES (:tid, :mid, :utype, :uid, :oname, :sname, :url, :mime, :size, NOW())"
        );
        $stmt->bindValue(':tid',   $data['ticket_id'],    PDO::PARAM_INT);
        $stmt->bindValue(':mid',   $data['message_id'] ?? null,
            ($data['message_id'] ?? null) !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':utype', $data['uploader_type']);
        $stmt->bindValue(':uid',   $data['uploader_id'],  PDO::PARAM_INT);
        $stmt->bindValue(':oname', $data['original_name']);
        $stmt->bindValue(':sname', $data['stored_name']);
        $stmt->bindValue(':url',   $data['file_url']);
        $stmt->bindValue(':mime',  $data['mime_type']);
        $stmt->bindValue(':size',  $data['file_size'],    PDO::PARAM_INT);
        $stmt->execute();
        return (int)$pdo->lastInsertId();
    }

    public function saveRating(int $userId, int $ticketId, int $rating, ?string $comment): void
    {
        $stmt = Database::connection()->prepare(
            "INSERT INTO ticket_csat_ratings (ticket_id, user_id, rating, comment, created_at)
             VALUES (:tid, :uid, :rating, :comment, NOW())
             ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment)"
        );
        $stmt->bindValue(':tid',     $ticketId, PDO::PARAM_INT);
        $stmt->bindValue(':uid',     $userId,   PDO::PARAM_INT);
        $stmt->bindValue(':rating',  $rating,   PDO::PARAM_INT);
        $stmt->bindValue(':comment', $comment);
        $stmt->execute();
    }

    public function categories(): array
    {
        return Database::connection()->query(
            "SELECT id, name, slug, icon, color, description
             FROM ticket_categories
             WHERE is_active = 1
             ORDER BY sort_order ASC, name ASC"
        )->fetchAll() ?: [];
    }
}
