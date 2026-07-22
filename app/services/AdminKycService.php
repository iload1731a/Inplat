<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\RequestContext;
use App\Repositories\AdminKycRepository;
use App\Repositories\AdminManagementRepository;
use InvalidArgumentException;

final class AdminKycService
{
    public function __construct(
        private readonly AdminKycRepository        $kycRepo  = new AdminKycRepository(),
        private readonly AdminManagementRepository $mgmtRepo = new AdminManagementRepository(),
    ) {}

    // =========================================================================
    // INDEX / QUEUE
    // =========================================================================

    public function kycIndex(array $filters, int $page = 1): array
    {
        $perPage = 50;
        $offset  = ($page - 1) * $perPage;

        $filterFull = array_merge($filters, ['limit' => $perPage, 'offset' => $offset]);

        $queue    = $this->kycRepo->listKycQueue($filterFull);
        $total    = $this->kycRepo->countKycQueue($filters);
        $docStats = $this->kycRepo->getKycStats();
        $usrStats = $this->kycRepo->getUserKycStats();
        $daily    = $this->kycRepo->getDailySubmissions(30);
        $typeBreakdown = $this->kycRepo->getDocumentTypeBreakdown();
        $avgReviewHrs  = $this->kycRepo->getAverageReviewTime();

        return [
            'queue'         => $queue,
            'total'         => $total,
            'page'          => $page,
            'perPage'       => $perPage,
            'totalPages'    => (int)ceil($total / $perPage),
            'kycStats'      => $docStats,
            'userStats'     => $usrStats,
            'daily'         => $daily,
            'typeBreakdown' => $typeBreakdown,
            'avgReviewHrs'  => round($avgReviewHrs, 1),
            'filters'       => $filters,
        ];
    }

    // =========================================================================
    // DOCUMENT DETAIL
    // =========================================================================

    public function documentDetail(int $docId): array
    {
        $doc = $this->kycRepo->findKycDocumentById($docId);
        if ($doc === null) {
            throw new InvalidArgumentException('Document not found.');
        }
        $auditLog = $this->kycRepo->getAuditLog((int)$doc['user_id']);
        return ['doc' => $doc, 'auditLog' => $auditLog];
    }

    // =========================================================================
    // USER KYC PROFILE
    // =========================================================================

    public function userKycProfile(int $userId): array
    {
        $profile  = $this->kycRepo->getUserProfile($userId);
        if ($profile === null) {
            throw new InvalidArgumentException('User not found.');
        }
        $documents = $this->kycRepo->getUserKycDocuments($userId);
        $auditLog  = $this->kycRepo->getAuditLog($userId);
        $risk      = $this->kycRepo->getRiskAssessment($userId);
        return compact('profile', 'documents', 'auditLog', 'risk');
    }

    // =========================================================================
    // REVIEW (SINGLE)
    // =========================================================================

    public function reviewDocument(int $adminId, int $docId, string $status, string $notes, int $kycLevel): int
    {
        if (!in_array($status, ['approved', 'rejected'], true)) {
            throw new InvalidArgumentException('Invalid KYC review status.');
        }

        $doc = $this->kycRepo->findKycDocumentById($docId);
        if ($doc === null) {
            throw new InvalidArgumentException('KYC document not found.');
        }

        $oldStatus = (string)($doc['status'] ?? '');
        if ($oldStatus !== 'pending') {
            throw new InvalidArgumentException('Document has already been reviewed.');
        }

        $userId = $this->kycRepo->reviewKycDocument($docId, $adminId, $status, trim($notes));

        if ($status === 'approved') {
            $currentLevel = (int)($doc['kyc_level'] ?? 0);
            $newLevel = max($currentLevel, $kycLevel > 0 ? $kycLevel : $currentLevel + 1);
            $this->kycRepo->updateUserKycStatus($userId, 'approved', $newLevel);
        }

        // Audit
        $this->kycRepo->insertAuditLog($userId, $docId, 'admin', $adminId, $status, $oldStatus, $status, '', RequestContext::ipAddress());

        // Notification to user
        $notifTitle = $status === 'approved' ? 'KYC Document Approved' : 'KYC Document Rejected';
        $notifMsg   = $status === 'approved'
            ? 'Your ' . str_replace('_', ' ', (string)($doc['document_type'] ?? '')) . ' has been approved.'
            : 'Your ' . str_replace('_', ' ', (string)($doc['document_type'] ?? '')) . ' was rejected. Reason: ' . trim($notes ?: 'See review notes.');
        $this->kycRepo->insertNotification($userId, 'kyc', $notifTitle, $notifMsg, '/user/kyc');

        $this->mgmtRepo->logAdminAction(
            $adminId, 'review_kyc_document', 'kyc_documents', (string)$docId,
            null, ['status' => $status, 'user_id' => $userId],
            RequestContext::ipAddress()
        );

        return $userId;
    }

    // =========================================================================
    // BULK OPERATIONS
    // =========================================================================

    public function bulkApprove(int $adminId, array $docIds, int $kycLevel, string $notes): array
    {
        $results = $this->kycRepo->bulkUpdateStatus($docIds, $adminId, 'approved', $notes);
        foreach ($results as $row) {
            $uid = (int)$row['user_id'];
            $this->kycRepo->insertAuditLog($uid, (int)$row['id'], 'admin', $adminId, 'approved', 'pending', 'approved', '', RequestContext::ipAddress());
            $this->kycRepo->updateUserKycStatus($uid, 'approved', max(1, $kycLevel));
            $this->kycRepo->insertNotification($uid, 'kyc', 'KYC Document Approved', 'Your identity document has been approved.', '/user/kyc');
        }
        return $results;
    }

    public function bulkReject(int $adminId, array $docIds, string $notes): array
    {
        $results = $this->kycRepo->bulkUpdateStatus($docIds, $adminId, 'rejected', $notes);
        foreach ($results as $row) {
            $uid = (int)$row['user_id'];
            $this->kycRepo->insertAuditLog($uid, (int)$row['id'], 'admin', $adminId, 'rejected', 'pending', 'rejected', '', RequestContext::ipAddress());
            $this->kycRepo->insertNotification($uid, 'kyc', 'KYC Document Rejected', 'Your identity document was rejected. Reason: ' . ($notes ?: 'See review notes.'), '/user/kyc');
        }
        return $results;
    }

    // =========================================================================
    // RE-REQUEST
    // =========================================================================

    public function reRequestDocument(int $adminId, int $userId, string $docType, string $notes): void
    {
        $this->kycRepo->reRequestDocument($userId, $docType, $adminId, $notes);
        $this->kycRepo->insertAuditLog($userId, null, 'admin', $adminId, 're_request', null, null, $notes, RequestContext::ipAddress());
        $friendly = ucwords(str_replace('_', ' ', $docType));
        $this->kycRepo->insertNotification($userId, 'kyc', 'KYC Re-Submission Required', "Please re-upload your {$friendly}. Reason: " . ($notes ?: 'Please resubmit.'), '/user/kyc');
        $this->kycRepo->updateUserKycStatus($userId, 'pending', 0);
    }

    // =========================================================================
    // RISK ASSESSMENT
    // =========================================================================

    public function saveRiskAssessment(int $adminId, int $userId, int $score, string $notes, array $factors): void
    {
        $level = match(true) {
            $score >= 75 => 'critical',
            $score >= 50 => 'high',
            $score >= 25 => 'medium',
            default       => 'low',
        };
        $this->kycRepo->upsertRiskAssessment($userId, $adminId, $score, $level, $factors, $notes);
        $this->kycRepo->insertAuditLog($userId, null, 'admin', $adminId, 'risk_assessment', null, $level, $notes, RequestContext::ipAddress());
    }

    // =========================================================================
    // COMPLIANCE / REPORTS
    // =========================================================================

    public function complianceReport(string $dateFrom, string $dateTo): array
    {
        $summary      = $this->kycRepo->getComplianceReport($dateFrom, $dateTo);
        $daily        = $this->kycRepo->getDailySubmissions(90);
        $typeBreakdown = $this->kycRepo->getDocumentTypeBreakdown();
        $highRisk     = $this->kycRepo->getHighRiskUsers(20);
        return compact('summary', 'daily', 'typeBreakdown', 'highRisk', 'dateFrom', 'dateTo');
    }

    // =========================================================================
    // EXPORT
    // =========================================================================

    public function exportCsv(array $filters): never
    {
        $rows = $this->kycRepo->getExportData($filters);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="kyc_export_' . date('Ymd_His') . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID','Username','Email','KYC Status','KYC Level','Doc Type','Doc Number','Country','Issue Date','Expiry Date','Status','Review Notes','Submitted At','Reviewed At','Reviewed By']);

        foreach ($rows as $r) {
            fputcsv($out, [
                $r['id'], $r['username'], $r['email'], $r['kyc_status'], $r['kyc_level'],
                $r['document_type'], $r['document_number'] ?? '', $r['issue_country'] ?? '',
                $r['issue_date'] ?? '', $r['expiry_date'] ?? '',
                $r['status'], $r['review_notes'] ?? '',
                $r['submitted_at'], $r['reviewed_at'] ?? '', $r['reviewed_by'],
            ]);
        }

        fclose($out);
        exit;
    }

    // =========================================================================
    // REQUIREMENTS MANAGEMENT
    // =========================================================================

    public function getRequirements(): array
    {
        return $this->kycRepo->getAllRequirements();
    }

    public function updateRequirement(int $adminId, int $requirementId, array $data): void
    {
        $this->kycRepo->updateRequirement($requirementId, $data);
        $this->mgmtRepo->logAdminAction($adminId, 'update_kyc_requirement', 'kyc_requirements', (string)$requirementId, null, $data, RequestContext::ipAddress());
    }

    // =========================================================================
    // AUDIT LOG
    // =========================================================================

    public function getRecentAuditLog(int $limit = 50): array
    {
        return $this->kycRepo->getRecentAuditLog($limit);
    }
}
