<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Libraries\Database;
use PDO;

final class AdminKycRepository
{
    public function listKycQueue(array $filters = []): array
    {
        $sql = "SELECT kd.id, kd.user_id, u.username, u.email, u.kyc_status, u.kyc_level,
                       kd.document_type, kd.document_number, kd.status, kd.submitted_at,
                       kd.reviewed_at, kd.reviewer_notes,
                       COALESCE(au.full_name, au.username) AS reviewer_name
                FROM kyc_documents kd
                INNER JOIN users u ON u.id = kd.user_id
                LEFT JOIN admin_users au ON au.id = kd.reviewed_by
                WHERE 1=1";

        $params = [];

        $status = trim((string)($filters['status'] ?? ''));
        if ($status !== '') {
            $sql .= ' AND kd.status = :status';
            $params['status'] = $status;
        }

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $sql .= ' AND (u.username LIKE :search OR u.email LIKE :search OR kd.document_number LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        $docType = trim((string)($filters['document_type'] ?? ''));
        if ($docType !== '') {
            $sql .= ' AND kd.document_type = :document_type';
            $params['document_type'] = $docType;
        }

        $sql .= ' ORDER BY kd.submitted_at DESC LIMIT 100';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    public function findKycDocumentById(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT kd.*, u.username, u.email, u.kyc_status, u.kyc_level,
                    COALESCE(au.full_name, au.username) AS reviewer_name
             FROM kyc_documents kd
             INNER JOIN users u ON u.id = kd.user_id
             LEFT JOIN admin_users au ON au.id = kd.reviewed_by
             WHERE kd.id = :id LIMIT 1"
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function getUserKycDocuments(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT kd.*, COALESCE(au.full_name, au.username) AS reviewer_name
             FROM kyc_documents kd
             LEFT JOIN admin_users au ON au.id = kd.reviewed_by
             WHERE kd.user_id = :user_id
             ORDER BY kd.id DESC"
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function reviewKycDocument(int $docId, int $adminId, string $status, string $notes): int
    {
        $stmt = Database::connection()->prepare(
            "UPDATE kyc_documents SET status = :status, reviewer_notes = :notes,
                    reviewed_by = :admin_id, reviewed_at = NOW(), updated_at = NOW()
             WHERE id = :id"
        );
        $stmt->bindValue(':status', $status);
        $stmt->bindValue(':notes', $notes);
        $stmt->bindValue(':admin_id', $adminId, PDO::PARAM_INT);
        $stmt->bindValue(':id', $docId, PDO::PARAM_INT);
        $stmt->execute();

        $kycDoc = $this->findKycDocumentById($docId);
        return (int)($kycDoc['user_id'] ?? 0);
    }

    public function updateUserKycStatus(int $userId, string $kycStatus, int $kycLevel): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE users SET kyc_status = :kyc_status, kyc_level = :kyc_level, updated_at = NOW() WHERE id = :id'
        );
        $stmt->bindValue(':kyc_status', $kycStatus);
        $stmt->bindValue(':kyc_level', $kycLevel, PDO::PARAM_INT);
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function getKycStats(): array
    {
        $stmt = Database::connection()->query(
            "SELECT
                COUNT(*) AS total_documents,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_review,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected,
                SUM(CASE WHEN DATE(submitted_at) = CURDATE() THEN 1 ELSE 0 END) AS submitted_today
             FROM kyc_documents"
        );
        return $stmt->fetch() ?: [];
    }
}
