<?php
declare(strict_types=1);
namespace App\Services;

use App\Libraries\RequestContext;
use App\Repositories\UserKycRepository;
use InvalidArgumentException;
use RuntimeException;

final class UserKycService
{
    private readonly UserKycRepository $repo;

    private const ALLOWED_TYPES = [
        'passport', 'national_id', 'drivers_license',
        'proof_of_address', 'selfie', 'corporate_doc', 'other',
    ];

    private const ALLOWED_MIMES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf',
    ];

    public function __construct(?UserKycRepository $repo = null)
    {
        $this->repo = $repo ?? new UserKycRepository();
    }

    public function data(int $userId): array
    {
        $kycStatus    = $this->repo->kycStatus($userId);
        $documents    = $this->repo->documents($userId);
        $requirements = $this->repo->getRequirements();
        $auditLog     = $this->repo->getUserAuditLog($userId);

        // Build per-type completion map
        $approvedTypes = array_column(
            array_filter($documents, static fn($d) => ($d['status'] ?? '') === 'approved'),
            'document_type'
        );

        return [
            'kycStatus'    => $kycStatus,
            'documents'    => $documents,
            'requirements' => $requirements,
            'approvedTypes'=> array_unique($approvedTypes),
            'auditLog'     => $auditLog,
        ];
    }

    public function documentDetail(int $userId, int $docId): array
    {
        $doc = $this->repo->documentById($docId, $userId);
        if ($doc === null) {
            throw new InvalidArgumentException('Document not found.');
        }
        return $doc;
    }

    public function submitDocument(int $userId, array $input, array $file): int
    {
        $docType = trim((string)($input['document_type'] ?? ''));
        if (!in_array($docType, self::ALLOWED_TYPES, true)) {
            throw new InvalidArgumentException('Invalid document type');
        }
        if ($this->repo->pendingCountForType($userId, $docType) > 0) {
            throw new InvalidArgumentException('You already have a pending ' . str_replace('_', ' ', $docType) . ' under review');
        }

        $mimeType = mime_content_type($file['tmp_name'] ?? '');
        if ($mimeType === false) {
            $mimeType = (string)($file['type'] ?? '');
        }
        if (!in_array($mimeType, self::ALLOWED_MIMES, true)) {
            throw new InvalidArgumentException('Invalid file type. Allowed: JPG, PNG, GIF, WEBP, PDF');
        }
        if (($file['size'] ?? 0) > 10 * 1024 * 1024) {
            throw new InvalidArgumentException('File must be smaller than 10MB');
        }

        $uploadDir = app_path('public/uploads/kyc/');
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $ext      = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
        $filename = 'kyc_' . $userId . '_' . $docType . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $target   = $uploadDir . $filename;

        if (!move_uploaded_file((string)($file['tmp_name'] ?? ''), $target)) {
            throw new RuntimeException('Failed to save document');
        }

        $fileUrl      = '/uploads/kyc/' . $filename;
        $docNumber    = trim((string)($input['document_number']  ?? '')) ?: null;
        $issueCountry = strtoupper(trim((string)($input['issue_country'] ?? ''))) ?: null;
        $issueDate    = trim((string)($input['issue_date']    ?? '')) ?: null;
        $expiryDate   = trim((string)($input['expiry_date']   ?? '')) ?: null;

        $docId = $this->repo->addDocument($userId, $docType, $docNumber, $fileUrl, $issueCountry, $issueDate, $expiryDate);

        $this->repo->insertAuditLog($userId, $docId, 'user', $userId, 'submit', null, 'pending', RequestContext::ipAddress());

        return $docId;
    }

    public function deletePendingDocument(int $userId, int $docId): void
    {
        $fileUrl = $this->repo->deletePendingDocument($docId, $userId);
        if ($fileUrl === null) {
            throw new InvalidArgumentException('Document not found or cannot be deleted (only pending documents can be removed).');
        }

        $this->repo->insertAuditLog($userId, $docId, 'user', $userId, 'delete', 'pending', null, RequestContext::ipAddress());

        // Remove physical file
        if ($fileUrl !== '') {
            $physicalPath = app_path('public' . $fileUrl);
            if (is_file($physicalPath) && !unlink($physicalPath)) {
                error_log('KYC: failed to delete file: ' . $physicalPath);
            }
        }
    }
}
