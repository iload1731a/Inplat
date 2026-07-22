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

    public function kycIndex(array $filters): array
    {
        return [
            'queue'    => $this->kycRepo->listKycQueue($filters),
            'kycStats' => $this->kycRepo->getKycStats(),
            'filters'  => $filters,
        ];
    }

    public function reviewDocument(int $adminId, int $docId, string $status, string $notes, int $kycLevel): int
    {
        if (!in_array($status, ['approved', 'rejected'], true)) {
            throw new InvalidArgumentException('Invalid KYC review status.');
        }

        $doc = $this->kycRepo->findKycDocumentById($docId);
        if ($doc === null) {
            throw new InvalidArgumentException('KYC document not found.');
        }

        if ((string)($doc['status'] ?? '') !== 'pending') {
            throw new InvalidArgumentException('Document has already been reviewed.');
        }

        $userId = $this->kycRepo->reviewKycDocument($docId, $adminId, $status, trim($notes));

        if ($status === 'approved') {
            $currentLevel = (int)($doc['kyc_level'] ?? 0);
            $newLevel = max($currentLevel, $kycLevel > 0 ? $kycLevel : $currentLevel + 1);
            $this->kycRepo->updateUserKycStatus($userId, 'approved', $newLevel);
        }

        $this->mgmtRepo->logAdminAction($adminId, 'review_kyc_document', 'kyc_documents', (string)$docId, null, ['status' => $status, 'user_id' => $userId], RequestContext::ipAddress());

        return $userId;
    }
}
