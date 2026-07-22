<?php
declare(strict_types=1);
namespace App\Controllers\User;

use App\Controllers\BaseController;
use App\Libraries\Csrf;
use App\Libraries\Request;
use App\Libraries\Response;
use App\Libraries\Session;
use App\Middleware\AuthMiddleware;
use App\Services\UserKycService;
use Throwable;

/**
 * User KYC Controller
 *
 * Routes:
 *   GET  /user/kyc                — KYC dashboard (status + level progress + document list)
 *   POST /user/kyc/submit         — Submit a new document
 *   POST /user/kyc/delete         — Delete a pending document
 *   GET  /user/kyc/document       — View single document detail
 *   GET  /user/kyc/status         — AJAX: current KYC status JSON
 */
final class KycController extends BaseController
{
    private function svc(): UserKycService
    {
        return new UserKycService();
    }

    private function uid(): int
    {
        return (int)(Session::get('auth.user_id') ?? 0);
    }

    // =========================================================================
    // DASHBOARD
    // =========================================================================

    public function index(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $userId = $this->uid();

        try {
            $data = $this->svc()->data($userId);
        } catch (Throwable) {
            $data = ['kycStatus' => [], 'documents' => [], 'requirements' => [], 'approvedTypes' => [], 'auditLog' => []];
        }

        $this->userView('user/kyc/index', array_merge($data, [
            'title'       => 'KYC Verification',
            'userSection' => 'kyc',
        ]));
    }

    // =========================================================================
    // DOCUMENT DETAIL
    // =========================================================================

    public function document(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $userId = $this->uid();
        $docId  = (int)$request->input('id', 0);

        try {
            $doc = $this->svc()->documentDetail($userId, $docId);
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
            Response::redirect('/user/kyc');
        }

        $this->userView('user/kyc/document', [
            'title'       => 'Document Detail',
            'userSection' => 'kyc',
            'doc'         => $doc,
        ]);
    }

    // =========================================================================
    // SUBMIT
    // =========================================================================

    public function submit(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 422);
        }

        $userId = $this->uid();
        $file   = $_FILES['document_file'] ?? null;

        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            Response::json(['ok' => false, 'message' => 'Document file is required'], 422);
        }

        try {
            $id = $this->svc()->submitDocument($userId, $request->all(), $file);
            Response::json(['ok' => true, 'message' => 'Document submitted for review', 'id' => $id]);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // =========================================================================
    // DELETE PENDING DOCUMENT
    // =========================================================================

    public function delete(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 422);
        }

        $userId = $this->uid();
        $docId  = (int)$request->input('document_id', 0);

        try {
            $this->svc()->deletePendingDocument($userId, $docId);
            Response::json(['ok' => true, 'message' => 'Document removed successfully.']);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // =========================================================================
    // STATUS (AJAX)
    // =========================================================================

    public function status(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $userId = $this->uid();

        try {
            $data = $this->svc()->data($userId);
            Response::json([
                'ok'        => true,
                'kycStatus' => $data['kycStatus'],
                'documents' => $data['documents'],
            ]);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
