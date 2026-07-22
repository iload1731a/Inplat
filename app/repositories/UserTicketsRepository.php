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
        $stmt = Database::connection()->prepare(
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
        $id = (int)Database::connection()->lastInsertId();
        // bump updated_at on ticket
        $stmt2 = Database::connection()->prepare(
            'UPDATE support_tickets SET updated_at = NOW() WHERE id = :tid'
        );
        $stmt2->bindValue(':tid', $ticketId, PDO::PARAM_INT);
        $stmt2->execute();
        return $id;
    }
}
