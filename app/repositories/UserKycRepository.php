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
            'SELECT id, document_type, document_number, file_url, status, review_notes, created_at
             FROM kyc_documents
             WHERE user_id = :uid
             ORDER BY id DESC'
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function addDocument(int $userId, string $type, ?string $docNumber, string $fileUrl): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO kyc_documents
                (user_id, document_type, document_number, file_url, status, created_at)
             VALUES (:uid, :type, :doc_num, :file_url, :status, NOW())'
        );
        $stmt->bindValue(':uid',     $userId, PDO::PARAM_INT);
        $stmt->bindValue(':type',    $type);
        $stmt->bindValue(':doc_num', $docNumber);
        $stmt->bindValue(':file_url',$fileUrl);
        $stmt->bindValue(':status',  'pending');
        $stmt->execute();
        return (int)Database::connection()->lastInsertId();
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
}
