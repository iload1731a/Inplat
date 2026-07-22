<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Libraries\Database;
use PDO;

final class AdminKycRepository
{
    // =========================================================================
    // QUEUE & FILTERS
    // =========================================================================

    public function listKycQueue(array $filters = []): array
    {
        $sql = "SELECT kd.id, kd.user_id, u.username, u.email, u.kyc_status, u.kyc_level,
                       kd.document_type, kd.document_number, kd.file_url, kd.issue_country,
                       kd.issue_date, kd.expiry_date,
                       kd.status, kd.created_at AS submitted_at,
                       kd.reviewed_at, kd.review_notes,
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

        $dateFrom = trim((string)($filters['date_from'] ?? ''));
        if ($dateFrom !== '') {
            $sql .= ' AND DATE(kd.created_at) >= :date_from';
            $params['date_from'] = $dateFrom;
        }

        $dateTo = trim((string)($filters['date_to'] ?? ''));
        if ($dateTo !== '') {
            $sql .= ' AND DATE(kd.created_at) <= :date_to';
            $params['date_to'] = $dateTo;
        }

        $country = trim((string)($filters['country'] ?? ''));
        if ($country !== '') {
            $sql .= ' AND kd.issue_country = :country';
            $params['country'] = $country;
        }

        $userId = (int)($filters['user_id'] ?? 0);
        if ($userId > 0) {
            $sql .= ' AND kd.user_id = :user_id';
            $params['user_id'] = $userId;
        }

        $sql .= ' ORDER BY kd.created_at DESC';

        $limit  = max(10, min(200, (int)($filters['limit'] ?? 50)));
        $offset = max(0, (int)($filters['offset'] ?? 0));
        $sql .= " LIMIT {$limit} OFFSET {$offset}";

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    public function countKycQueue(array $filters = []): int
    {
        $sql    = 'SELECT COUNT(*) FROM kyc_documents kd INNER JOIN users u ON u.id = kd.user_id WHERE 1=1';
        $params = [];

        $status = trim((string)($filters['status'] ?? ''));
        if ($status !== '') { $sql .= ' AND kd.status = :status'; $params['status'] = $status; }

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $sql .= ' AND (u.username LIKE :search OR u.email LIKE :search OR kd.document_number LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        $docType = trim((string)($filters['document_type'] ?? ''));
        if ($docType !== '') { $sql .= ' AND kd.document_type = :document_type'; $params['document_type'] = $docType; }

        $dateFrom = trim((string)($filters['date_from'] ?? ''));
        if ($dateFrom !== '') { $sql .= ' AND DATE(kd.created_at) >= :date_from'; $params['date_from'] = $dateFrom; }

        $dateTo = trim((string)($filters['date_to'] ?? ''));
        if ($dateTo !== '') { $sql .= ' AND DATE(kd.created_at) <= :date_to'; $params['date_to'] = $dateTo; }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
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

    public function getUserProfile(int $userId): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT u.id, u.username, u.email, u.first_name, u.last_name,
                    u.kyc_status, u.kyc_level, u.created_at AS registered_at,
                    u.is_active,
                    kra.risk_score, kra.risk_level, kra.last_assessed_at, kra.notes AS risk_notes
             FROM users u
             LEFT JOIN kyc_risk_assessments kra ON kra.user_id = u.id
             WHERE u.id = :id LIMIT 1"
        );
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    // =========================================================================
    // REVIEW ACTIONS
    // =========================================================================

    public function reviewKycDocument(int $docId, int $adminId, string $status, string $notes): int
    {
        $stmt = Database::connection()->prepare(
            "UPDATE kyc_documents
                SET status        = :status,
                    review_notes  = :notes,
                    reviewed_by   = :admin_id,
                    reviewed_at   = NOW(),
                    updated_at    = NOW()
             WHERE id = :id"
        );
        $stmt->bindValue(':status',   $status);
        $stmt->bindValue(':notes',    $notes);
        $stmt->bindValue(':admin_id', $adminId, PDO::PARAM_INT);
        $stmt->bindValue(':id',       $docId,   PDO::PARAM_INT);
        $stmt->execute();

        $doc = $this->findKycDocumentById($docId);
        return (int)($doc['user_id'] ?? 0);
    }

    public function updateUserKycStatus(int $userId, string $kycStatus, int $kycLevel): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE users SET kyc_status = :kyc_status, kyc_level = :kyc_level, updated_at = NOW() WHERE id = :id'
        );
        $stmt->bindValue(':kyc_status', $kycStatus);
        $stmt->bindValue(':kyc_level',  $kycLevel, PDO::PARAM_INT);
        $stmt->bindValue(':id',         $userId,   PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * Bulk update a set of document IDs to a given status.
     * Returns array of ['doc_id' => ..., 'user_id' => ..., 'old_status' => ...] for post-processing.
     */
    public function bulkUpdateStatus(array $docIds, int $adminId, string $status, string $notes): array
    {
        if ($docIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($docIds), '?'));
        // Fetch current docs first
        $fetch = Database::connection()->prepare(
            "SELECT id, user_id, status FROM kyc_documents WHERE id IN ({$placeholders}) AND status = 'pending'"
        );
        $fetch->execute(array_values($docIds));
        $docs = $fetch->fetchAll() ?: [];

        if ($docs === []) {
            return [];
        }

        $ids = array_column($docs, 'id');
        $ph2 = implode(',', array_fill(0, count($ids), '?'));

        $upd = Database::connection()->prepare(
            "UPDATE kyc_documents SET status = ?, review_notes = ?, reviewed_by = ?, reviewed_at = NOW(), updated_at = NOW() WHERE id IN ({$ph2})"
        );
        $upd->execute(array_merge([$status, $notes, $adminId], $ids));

        return $docs;
    }

    /**
     * Re-request specific document types from a user by setting their pending docs to rejected.
     */
    public function reRequestDocument(int $userId, string $docType, int $adminId, string $notes): void
    {
        $stmt = Database::connection()->prepare(
            "UPDATE kyc_documents
                SET status = 'rejected', review_notes = :notes, reviewed_by = :admin_id, reviewed_at = NOW(), updated_at = NOW()
             WHERE user_id = :uid AND document_type = :type AND status = 'pending'"
        );
        $stmt->bindValue(':notes',    $notes);
        $stmt->bindValue(':admin_id', $adminId, PDO::PARAM_INT);
        $stmt->bindValue(':uid',      $userId,  PDO::PARAM_INT);
        $stmt->bindValue(':type',     $docType);
        $stmt->execute();
    }

    // =========================================================================
    // STATISTICS
    // =========================================================================

    public function getKycStats(): array
    {
        $stmt = Database::connection()->query(
            "SELECT
                COUNT(*) AS total_documents,
                SUM(CASE WHEN status = 'pending'  THEN 1 ELSE 0 END) AS pending_review,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected,
                SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) AS submitted_today,
                SUM(CASE WHEN DATE(reviewed_at) = CURDATE() THEN 1 ELSE 0 END) AS reviewed_today
             FROM kyc_documents"
        );
        return $stmt->fetch() ?: [];
    }

    public function getUserKycStats(): array
    {
        $stmt = Database::connection()->query(
            "SELECT
                COUNT(*) AS total_users,
                SUM(CASE WHEN kyc_status = 'approved'   THEN 1 ELSE 0 END) AS verified_users,
                SUM(CASE WHEN kyc_status = 'pending'    THEN 1 ELSE 0 END) AS pending_users,
                SUM(CASE WHEN kyc_status = 'rejected'   THEN 1 ELSE 0 END) AS rejected_users,
                SUM(CASE WHEN kyc_status = 'unverified' THEN 1 ELSE 0 END) AS unverified_users,
                SUM(CASE WHEN kyc_level  >= 2           THEN 1 ELSE 0 END) AS level2_plus,
                SUM(CASE WHEN kyc_level  >= 3           THEN 1 ELSE 0 END) AS level3_plus
             FROM users"
        );
        return $stmt->fetch() ?: [];
    }

    public function getDailySubmissions(int $days = 30): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT DATE(created_at) AS day,
                    COUNT(*) AS total,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected,
                    SUM(CASE WHEN status = 'pending'  THEN 1 ELSE 0 END) AS pending
             FROM kyc_documents
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
             GROUP BY DATE(created_at)
             ORDER BY day ASC"
        );
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function getDocumentTypeBreakdown(): array
    {
        $stmt = Database::connection()->query(
            "SELECT document_type,
                    COUNT(*) AS total,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected,
                    SUM(CASE WHEN status = 'pending'  THEN 1 ELSE 0 END) AS pending
             FROM kyc_documents
             GROUP BY document_type
             ORDER BY total DESC"
        );
        return $stmt->fetchAll() ?: [];
    }

    public function getAverageReviewTime(): float
    {
        $stmt = Database::connection()->query(
            "SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, reviewed_at)) AS avg_hours
             FROM kyc_documents
             WHERE reviewed_at IS NOT NULL"
        );
        return (float)($stmt->fetchColumn() ?? 0);
    }

    // =========================================================================
    // AUDIT LOG
    // =========================================================================

    public function insertAuditLog(
        int    $userId,
        ?int   $documentId,
        string $actorType,
        ?int   $actorId,
        string $action,
        ?string $oldStatus,
        ?string $newStatus,
        ?string $notes,
        string  $ipAddress = ''
    ): void {
        $stmt = Database::connection()->prepare(
            'INSERT INTO kyc_audit_log
                (user_id, document_id, actor_type, actor_id, action, old_status, new_status, notes, ip_address, created_at)
             VALUES (:uid, :doc_id, :actor_type, :actor_id, :action, :old_status, :new_status, :notes, :ip, NOW())'
        );
        $stmt->bindValue(':uid',        $userId,     PDO::PARAM_INT);
        $stmt->bindValue(':doc_id',     $documentId, $documentId !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':actor_type', $actorType);
        $stmt->bindValue(':actor_id',   $actorId,    $actorId !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':action',     $action);
        $stmt->bindValue(':old_status', $oldStatus);
        $stmt->bindValue(':new_status', $newStatus);
        $stmt->bindValue(':notes',      $notes);
        $stmt->bindValue(':ip',         $ipAddress);
        $stmt->execute();
    }

    public function getAuditLog(int $userId, int $limit = 50): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT kal.*, kd.document_type,
                    CASE kal.actor_type
                        WHEN 'admin' THEN COALESCE(au.full_name, au.username)
                        WHEN 'user'  THEN u.username
                        ELSE 'System'
                    END AS actor_name
             FROM kyc_audit_log kal
             LEFT JOIN kyc_documents kd ON kd.id = kal.document_id
             LEFT JOIN admin_users au ON au.id = kal.actor_id AND kal.actor_type = 'admin'
             LEFT JOIN users u        ON u.id  = kal.actor_id AND kal.actor_type = 'user'
             WHERE kal.user_id = :uid
             ORDER BY kal.created_at DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit,  PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function getRecentAuditLog(int $limit = 50): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT kal.*, u.username, kd.document_type,
                    CASE kal.actor_type
                        WHEN 'admin' THEN COALESCE(au.full_name, au.username)
                        WHEN 'user'  THEN u.username
                        ELSE 'System'
                    END AS actor_name
             FROM kyc_audit_log kal
             INNER JOIN users u ON u.id = kal.user_id
             LEFT JOIN kyc_documents kd ON kd.id = kal.document_id
             LEFT JOIN admin_users au ON au.id = kal.actor_id AND kal.actor_type = 'admin'
             ORDER BY kal.created_at DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    // =========================================================================
    // RISK ASSESSMENT
    // =========================================================================

    public function getRiskAssessment(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM kyc_risk_assessments WHERE user_id = :uid LIMIT 1'
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch() ?: ['risk_score' => 0, 'risk_level' => 'low', 'risk_factors' => null, 'notes' => ''];
    }

    public function upsertRiskAssessment(int $userId, int $adminId, int $score, string $level, array $factors, string $notes): void
    {
        $factorsJson = json_encode($factors, JSON_UNESCAPED_UNICODE);
        $stmt = Database::connection()->prepare(
            "INSERT INTO kyc_risk_assessments (user_id, risk_score, risk_level, risk_factors, last_assessed_at, assessed_by, notes)
             VALUES (:uid, :score, :level, :factors, NOW(), :admin_id, :notes)
             ON DUPLICATE KEY UPDATE
                risk_score = VALUES(risk_score), risk_level = VALUES(risk_level),
                risk_factors = VALUES(risk_factors), last_assessed_at = NOW(),
                assessed_by = VALUES(assessed_by), notes = VALUES(notes), updated_at = NOW()"
        );
        $stmt->bindValue(':uid',      $userId,      PDO::PARAM_INT);
        $stmt->bindValue(':score',    $score,        PDO::PARAM_INT);
        $stmt->bindValue(':level',    $level);
        $stmt->bindValue(':factors',  $factorsJson);
        $stmt->bindValue(':admin_id', $adminId,      PDO::PARAM_INT);
        $stmt->bindValue(':notes',    $notes);
        $stmt->execute();
    }

    public function getHighRiskUsers(int $limit = 50): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT kra.*, u.username, u.email, u.kyc_status, u.kyc_level
             FROM kyc_risk_assessments kra
             INNER JOIN users u ON u.id = kra.user_id
             WHERE kra.risk_level IN ('high','critical')
             ORDER BY kra.risk_score DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    // =========================================================================
    // COMPLIANCE / EXPORT
    // =========================================================================

    public function getComplianceReport(string $dateFrom, string $dateTo): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT
                COUNT(*) AS total_submissions,
                SUM(CASE WHEN kd.status = 'approved' THEN 1 ELSE 0 END) AS approved,
                SUM(CASE WHEN kd.status = 'rejected' THEN 1 ELSE 0 END) AS rejected,
                SUM(CASE WHEN kd.status = 'pending'  THEN 1 ELSE 0 END) AS pending,
                COUNT(DISTINCT kd.user_id) AS unique_users,
                AVG(TIMESTAMPDIFF(HOUR, kd.created_at, kd.reviewed_at)) AS avg_review_hours
             FROM kyc_documents kd
             WHERE DATE(kd.created_at) BETWEEN :df AND :dt"
        );
        $stmt->bindValue(':df', $dateFrom);
        $stmt->bindValue(':dt', $dateTo);
        $stmt->execute();
        return $stmt->fetch() ?: [];
    }

    public function getExportData(array $filters = []): array
    {
        $sql = "SELECT kd.id, u.username, u.email, u.kyc_status, u.kyc_level,
                       kd.document_type, kd.document_number, kd.issue_country,
                       kd.issue_date, kd.expiry_date,
                       kd.status, kd.review_notes,
                       kd.created_at AS submitted_at, kd.reviewed_at,
                       COALESCE(au.username, '-') AS reviewed_by
                FROM kyc_documents kd
                INNER JOIN users u ON u.id = kd.user_id
                LEFT JOIN admin_users au ON au.id = kd.reviewed_by
                WHERE 1=1";

        $params = [];

        $status = trim((string)($filters['status'] ?? ''));
        if ($status !== '') { $sql .= ' AND kd.status = :status'; $params['status'] = $status; }

        $dateFrom = trim((string)($filters['date_from'] ?? ''));
        if ($dateFrom !== '') { $sql .= ' AND DATE(kd.created_at) >= :date_from'; $params['date_from'] = $dateFrom; }

        $dateTo = trim((string)($filters['date_to'] ?? ''));
        if ($dateTo !== '') { $sql .= ' AND DATE(kd.created_at) <= :date_to'; $params['date_to'] = $dateTo; }

        $sql .= ' ORDER BY kd.created_at DESC LIMIT 10000';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    // =========================================================================
    // REQUIREMENTS
    // =========================================================================

    public function getAllRequirements(): array
    {
        $stmt = Database::connection()->query(
            'SELECT * FROM kyc_requirements ORDER BY kyc_level ASC, sort_order ASC'
        );
        return $stmt->fetchAll() ?: [];
    }

    public function updateRequirement(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE kyc_requirements
                SET is_required = :required, is_enabled = :enabled,
                    display_name = :name, description = :desc, updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->bindValue(':required', (int)($data['is_required'] ?? 1), PDO::PARAM_INT);
        $stmt->bindValue(':enabled',  (int)($data['is_enabled']  ?? 1), PDO::PARAM_INT);
        $stmt->bindValue(':name',     (string)($data['display_name'] ?? ''));
        $stmt->bindValue(':desc',     (string)($data['description']  ?? ''));
        $stmt->bindValue(':id',       $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    // =========================================================================
    // NOTIFICATIONS
    // =========================================================================

    public function insertNotification(int $userId, string $type, string $title, string $message, string $actionUrl = ''): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO notifications (user_id, type, title, message, is_read, action_url, created_at)
             VALUES (:uid, :type, :title, :message, 0, :url, NOW())'
        );
        $stmt->bindValue(':uid',     $userId, PDO::PARAM_INT);
        $stmt->bindValue(':type',    $type);
        $stmt->bindValue(':title',   $title);
        $stmt->bindValue(':message', $message);
        $stmt->bindValue(':url',     $actionUrl);
        $stmt->execute();
    }
}
