<?php
declare(strict_types=1);
namespace App\Repositories;

use App\Libraries\Database;
use PDO;

final class UserKycRepository
{
    public function kycStatus(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT kyc_status, kyc_level FROM users WHERE id = :uid LIMIT 1'
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ?: ['kyc_status' => 'unverified', 'kyc_level' => 0];
    }

    public function documents(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, document_type, document_number, file_url, issue_country,
                    issue_date, expiry_date, status, review_notes, created_at, reviewed_at
             FROM kyc_documents
             WHERE user_id = :uid
             ORDER BY id DESC'
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function documentById(int $id, int $userId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, document_type, document_number, file_url, issue_country,
                    issue_date, expiry_date, status, review_notes, created_at, reviewed_at
             FROM kyc_documents
             WHERE id = :id AND user_id = :uid
             LIMIT 1'
        );
        $stmt->bindValue(':id',  $id,     PDO::PARAM_INT);
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function addDocument(
        int     $userId,
        string  $type,
        ?string $docNumber,
        string  $fileUrl,
        ?string $issueCountry = null,
        ?string $issueDate    = null,
        ?string $expiryDate   = null
    ): int {
        $stmt = Database::connection()->prepare(
            'INSERT INTO kyc_documents
                (user_id, document_type, document_number, file_url,
                 issue_country, issue_date, expiry_date, status, created_at)
             VALUES (:uid, :type, :doc_num, :file_url,
                     :country, :issue_date, :expiry_date, :status, NOW())'
        );
        $stmt->bindValue(':uid',         $userId, PDO::PARAM_INT);
        $stmt->bindValue(':type',        $type);
        $stmt->bindValue(':doc_num',     $docNumber);
        $stmt->bindValue(':file_url',    $fileUrl);
        $stmt->bindValue(':country',     $issueCountry ?: null);
        $stmt->bindValue(':issue_date',  $issueDate    ?: null);
        $stmt->bindValue(':expiry_date', $expiryDate   ?: null);
        $stmt->bindValue(':status',      'pending');
        $stmt->execute();
        return (int)Database::connection()->lastInsertId();
    }

    public function deletePendingDocument(int $id, int $userId): ?string
    {
        $stmt = Database::connection()->prepare(
            "SELECT file_url FROM kyc_documents WHERE id = :id AND user_id = :uid AND status = 'pending' LIMIT 1"
        );
        $stmt->bindValue(':id',  $id,     PDO::PARAM_INT);
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $del = Database::connection()->prepare(
            "DELETE FROM kyc_documents WHERE id = :id AND user_id = :uid AND status = 'pending'"
        );
        $del->bindValue(':id',  $id,     PDO::PARAM_INT);
        $del->bindValue(':uid', $userId, PDO::PARAM_INT);
        $del->execute();

        return (string)($row['file_url'] ?? '');
    }

    public function pendingCountForType(int $userId, string $type): int
    {
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) FROM kyc_documents
             WHERE user_id = :uid AND document_type = :type AND status = 'pending'"
        );
        $stmt->bindValue(':uid',  $userId, PDO::PARAM_INT);
        $stmt->bindValue(':type', $type);
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    public function getRequirements(): array
    {
        $stmt = Database::connection()->query(
            'SELECT * FROM kyc_requirements WHERE is_enabled = 1 ORDER BY kyc_level ASC, sort_order ASC'
        );
        return $stmt->fetchAll() ?: [];
    }

    public function getUserAuditLog(int $userId, int $limit = 20): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT kal.action, kal.old_status, kal.new_status, kal.notes, kal.created_at, kd.document_type
             FROM kyc_audit_log kal
             LEFT JOIN kyc_documents kd ON kd.id = kal.document_id
             WHERE kal.user_id = :uid
             ORDER BY kal.created_at DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit,  PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function insertAuditLog(int $userId, ?int $documentId, string $actorType, ?int $actorId, string $action, ?string $oldStatus, ?string $newStatus, string $ip = ''): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO kyc_audit_log (user_id, document_id, actor_type, actor_id, action, old_status, new_status, ip_address, created_at)
             VALUES (:uid, :doc_id, :actor_type, :actor_id, :action, :old_status, :new_status, :ip, NOW())'
        );
        $stmt->bindValue(':uid',        $userId,     PDO::PARAM_INT);
        $stmt->bindValue(':doc_id',     $documentId, $documentId !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':actor_type', $actorType);
        $stmt->bindValue(':actor_id',   $actorId,    $actorId !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':action',     $action);
        $stmt->bindValue(':old_status', $oldStatus);
        $stmt->bindValue(':new_status', $newStatus);
        $stmt->bindValue(':ip',         $ip);
        $stmt->execute();
    }
}
