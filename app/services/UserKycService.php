<?php
declare(strict_types=1);
namespace App\Services;

use App\Repositories\UserKycRepository;

final class UserKycService
{
    private readonly UserKycRepository $repo;

    public function __construct(?UserKycRepository $repo = null)
    {
        $this->repo = $repo ?? new UserKycRepository();
    }

    public function data(int $userId): array
    {
        return [
            'kycStatus' => $this->repo->kycStatus($userId),
            'documents' => $this->repo->documents($userId),
        ];
    }

    public function submitDocument(int $userId, array $input, array $file): int
    {
        $allowedTypes = ['passport', 'national_id', 'drivers_license', 'proof_of_address', 'selfie', 'corporate_doc', 'other'];
        $docType = trim((string)($input['document_type'] ?? ''));
        if (!in_array($docType, $allowedTypes, true)) {
            throw new \InvalidArgumentException('Invalid document type');
        }
        if ($this->repo->pendingCountForType($userId, $docType) > 0) {
            throw new \InvalidArgumentException('You already have a pending ' . str_replace('_', ' ', $docType) . ' document under review');
        }

        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
        if (!in_array($file['type'], $allowedMimes, true)) {
            throw new \InvalidArgumentException('Invalid file type. Allowed: JPG, PNG, GIF, WEBP, PDF');
        }
        if ($file['size'] > 10 * 1024 * 1024) {
            throw new \InvalidArgumentException('File must be smaller than 10MB');
        }

        $uploadDir = app_path('public/uploads/kyc/');
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $ext      = pathinfo((string)$file['name'], PATHINFO_EXTENSION);
        $filename = 'kyc_' . $userId . '_' . $docType . '_' . time() . '.' . strtolower($ext);
        $target   = $uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $target)) {
            throw new \RuntimeException('Failed to save document');
        }

        $fileUrl   = '/uploads/kyc/' . $filename;
        $docNumber = trim((string)($input['document_number'] ?? '')) ?: null;

        return $this->repo->addDocument($userId, $docType, $docNumber, $fileUrl);
    }
}
