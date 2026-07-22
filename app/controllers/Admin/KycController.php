<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Libraries\Request;
use App\Libraries\Response;
use App\Services\AdminKycService;
use Throwable;

final class KycController extends AdminBaseController
{
    private function svc(): AdminKycService
    {
        return new AdminKycService();
    }

    public function index(Request $request): void
    {
        $this->bootAdmin();

        $filters = [
            'status'        => trim((string)$request->input('status', '')),
            'search'        => trim((string)$request->input('search', '')),
            'document_type' => trim((string)$request->input('document_type', '')),
        ];

        $data = $this->svc()->kycIndex($filters);

        $this->view('admin/kyc/index', [
            'title'        => 'Admin · KYC Verification',
            'username'     => $this->adminUsername(),
            'adminSection' => 'kyc',
            ...$data,
        ]);
    }

    public function review(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);

        $docId    = (int)$request->input('document_id', 0);
        $status   = trim((string)$request->input('status', ''));
        $notes    = trim((string)$request->input('notes', ''));
        $kycLevel = (int)$request->input('kyc_level', 1);

        try {
            $userId = $this->svc()->reviewDocument($this->adminId(), $docId, $status, $notes, $kycLevel);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        Response::json(['ok' => true, 'message' => 'KYC document ' . $status . '.', 'redirect' => '/admin/kyc']);
    }
}
