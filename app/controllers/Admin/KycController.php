<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Libraries\Request;
use App\Libraries\Response;
use App\Services\AdminKycService;
use Throwable;

/**
 * Admin KYC Controller
 *
 * Routes:
 *   GET  /admin/kyc                   — Dashboard: KPI cards, 30-day chart, queue with filters
 *   POST /admin/kyc/review            — Single document approve/reject
 *   GET  /admin/kyc/detail            — Document detail view with audit log
 *   GET  /admin/kyc/user              — User KYC profile (all docs + risk + audit)
 *   POST /admin/kyc/bulk-approve      — Bulk approve selected documents
 *   POST /admin/kyc/bulk-reject       — Bulk reject selected documents
 *   POST /admin/kyc/re-request        — Re-request document type from user
 *   POST /admin/kyc/risk              — Save risk assessment for a user
 *   GET  /admin/kyc/compliance        — Compliance report with date filter
 *   GET  /admin/kyc/export            — CSV export of KYC documents
 *   GET  /admin/kyc/requirements      — Manage KYC document requirements
 *   POST /admin/kyc/requirements/update — Update a requirement
 *   GET  /admin/kyc/audit             — Recent audit log view
 */
final class KycController extends AdminBaseController
{
    private function svc(): AdminKycService
    {
        return new AdminKycService();
    }

    // =========================================================================
    // DASHBOARD / INDEX
    // =========================================================================

    public function index(Request $request): void
    {
        $this->bootAdmin();

        $filters = [
            'status'        => trim((string)$request->input('status', '')),
            'search'        => trim((string)$request->input('search', '')),
            'document_type' => trim((string)$request->input('document_type', '')),
            'date_from'     => trim((string)$request->input('date_from', '')),
            'date_to'       => trim((string)$request->input('date_to', '')),
            'country'       => trim((string)$request->input('country', '')),
        ];
        $page = max(1, (int)$request->input('page', 1));

        try {
            $data = $this->svc()->kycIndex($filters, $page);
        } catch (Throwable $e) {
            $data = ['queue' => [], 'kycStats' => [], 'userStats' => [], 'daily' => [], 'typeBreakdown' => [], 'filters' => $filters, 'page' => 1, 'totalPages' => 1, 'total' => 0, 'perPage' => 50, 'avgReviewHrs' => 0];
        }

        $this->view('admin/kyc/index', array_merge($data, [
            'title'        => 'Admin · KYC Verification',
            'username'     => $this->adminUsername(),
            'adminSection' => 'kyc',
        ]));
    }

    // =========================================================================
    // DOCUMENT DETAIL
    // =========================================================================

    public function detail(Request $request): void
    {
        $this->bootAdmin();
        $docId = (int)$request->input('id', 0);

        try {
            $data = $this->svc()->documentDetail($docId);
        } catch (Throwable $e) {
            $this->view('admin/kyc/detail', [
                'title'        => 'Document Detail',
                'username'     => $this->adminUsername(),
                'adminSection' => 'kyc',
                'error'        => $e->getMessage(),
                'doc'          => null,
                'auditLog'     => [],
            ]);
            return;
        }

        $this->view('admin/kyc/detail', array_merge($data, [
            'title'        => 'KYC Document Detail',
            'username'     => $this->adminUsername(),
            'adminSection' => 'kyc',
        ]));
    }

    // =========================================================================
    // USER KYC PROFILE
    // =========================================================================

    public function userProfile(Request $request): void
    {
        $this->bootAdmin();
        $userId = (int)$request->input('user_id', 0);

        try {
            $data = $this->svc()->userKycProfile($userId);
        } catch (Throwable $e) {
            $this->view('admin/kyc/user-profile', [
                'title'        => 'User KYC Profile',
                'username'     => $this->adminUsername(),
                'adminSection' => 'kyc',
                'error'        => $e->getMessage(),
                'profile'      => null,
                'documents'    => [],
                'auditLog'     => [],
                'risk'         => [],
            ]);
            return;
        }

        $this->view('admin/kyc/user-profile', array_merge($data, [
            'title'        => 'User KYC Profile — ' . ($data['profile']['username'] ?? ''),
            'username'     => $this->adminUsername(),
            'adminSection' => 'kyc',
        ]));
    }

    // =========================================================================
    // REVIEW (SINGLE)
    // =========================================================================

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

    // =========================================================================
    // BULK APPROVE
    // =========================================================================

    public function bulkApprove(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);

        $docIds   = array_map('intval', (array)$request->input('doc_ids', []));
        $kycLevel = (int)$request->input('kyc_level', 1);
        $notes    = trim((string)$request->input('notes', ''));

        if ($docIds === []) {
            Response::json(['ok' => false, 'message' => 'No documents selected.'], 422);
        }

        try {
            $results = $this->svc()->bulkApprove($this->adminId(), $docIds, $kycLevel, $notes);
            Response::json(['ok' => true, 'message' => count($results) . ' document(s) approved.']);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // BULK REJECT
    // =========================================================================

    public function bulkReject(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);

        $docIds = array_map('intval', (array)$request->input('doc_ids', []));
        $notes  = trim((string)$request->input('notes', ''));

        if ($docIds === []) {
            Response::json(['ok' => false, 'message' => 'No documents selected.'], 422);
        }

        try {
            $results = $this->svc()->bulkReject($this->adminId(), $docIds, $notes);
            Response::json(['ok' => true, 'message' => count($results) . ' document(s) rejected.']);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // RE-REQUEST
    // =========================================================================

    public function reRequest(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);

        $userId  = (int)$request->input('user_id', 0);
        $docType = trim((string)$request->input('document_type', ''));
        $notes   = trim((string)$request->input('notes', ''));

        try {
            $this->svc()->reRequestDocument($this->adminId(), $userId, $docType, $notes);
            Response::json(['ok' => true, 'message' => 'Re-request sent to user.']);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // =========================================================================
    // RISK ASSESSMENT
    // =========================================================================

    public function risk(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);

        $userId  = (int)$request->input('user_id', 0);
        $score   = min(100, max(0, (int)$request->input('risk_score', 0)));
        $notes   = trim((string)$request->input('notes', ''));
        $factors = (array)$request->input('factors', []);

        try {
            $this->svc()->saveRiskAssessment($this->adminId(), $userId, $score, $notes, $factors);
            Response::json(['ok' => true, 'message' => 'Risk assessment saved.']);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // =========================================================================
    // COMPLIANCE REPORT
    // =========================================================================

    public function compliance(Request $request): void
    {
        $this->bootAdmin();

        $dateFrom = trim((string)$request->input('date_from', date('Y-m-01')));
        $dateTo   = trim((string)$request->input('date_to',   date('Y-m-d')));

        try {
            $data = $this->svc()->complianceReport($dateFrom, $dateTo);
        } catch (Throwable $e) {
            $data = ['summary' => [], 'daily' => [], 'typeBreakdown' => [], 'highRisk' => [], 'dateFrom' => $dateFrom, 'dateTo' => $dateTo];
        }

        $this->view('admin/kyc/compliance', array_merge($data, [
            'title'        => 'KYC Compliance Report',
            'username'     => $this->adminUsername(),
            'adminSection' => 'kyc',
        ]));
    }

    // =========================================================================
    // EXPORT
    // =========================================================================

    public function export(Request $request): void
    {
        $this->bootAdmin();

        $filters = [
            'status'    => trim((string)$request->input('status', '')),
            'date_from' => trim((string)$request->input('date_from', '')),
            'date_to'   => trim((string)$request->input('date_to', '')),
        ];

        $this->svc()->exportCsv($filters);
    }

    // =========================================================================
    // REQUIREMENTS MANAGEMENT
    // =========================================================================

    public function requirements(Request $request): void
    {
        $this->bootAdmin();

        $reqs = $this->svc()->getRequirements();

        $this->view('admin/kyc/requirements', [
            'title'        => 'KYC Requirements',
            'username'     => $this->adminUsername(),
            'adminSection' => 'kyc',
            'requirements' => $reqs,
        ]);
    }

    public function requirementUpdate(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);

        $id   = (int)$request->input('id', 0);
        $data = [
            'is_required'  => (int)$request->input('is_required', 1),
            'is_enabled'   => (int)$request->input('is_enabled', 1),
            'display_name' => trim((string)$request->input('display_name', '')),
            'description'  => trim((string)$request->input('description', '')),
        ];

        try {
            $this->svc()->updateRequirement($this->adminId(), $id, $data);
            Response::json(['ok' => true, 'message' => 'Requirement updated.']);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // =========================================================================
    // AUDIT LOG
    // =========================================================================

    public function auditLog(Request $request): void
    {
        $this->bootAdmin();

        try {
            $log = $this->svc()->getRecentAuditLog(100);
        } catch (Throwable) {
            $log = [];
        }

        $this->view('admin/kyc/audit', [
            'title'        => 'KYC Audit Log',
            'username'     => $this->adminUsername(),
            'adminSection' => 'kyc',
            'log'          => $log,
        ]);
    }
}
