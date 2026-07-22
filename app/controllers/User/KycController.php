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

final class KycController extends BaseController
{
    public function index(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $userId = (int)(Session::get('auth.user_id') ?? 0);

        try {
            $data = (new UserKycService())->data($userId);
        } catch (Throwable) {
            $data = ['kycStatus' => [], 'documents' => []];
        }

        $this->userView('user/kyc/index', array_merge($data, [
            'title'       => 'KYC Verification',
            'userSection' => 'kyc',
        ]));
    }

    public function submit(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 422);
        }

        $userId = (int)(Session::get('auth.user_id') ?? 0);
        $file   = $_FILES['document_file'] ?? null;

        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            Response::json(['ok' => false, 'message' => 'Document file is required'], 422);
        }

        try {
            $id = (new UserKycService())->submitDocument($userId, $request->all(), $file);
            Response::json(['ok' => true, 'message' => 'Document submitted for review', 'id' => $id]);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
