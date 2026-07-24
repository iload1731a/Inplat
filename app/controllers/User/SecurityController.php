<?php
declare(strict_types=1);
namespace App\Controllers\User;

use App\Controllers\BaseController;
use App\Libraries\Csrf;
use App\Libraries\Request;
use App\Libraries\Response;
use App\Libraries\Session;
use App\Middleware\AuthMiddleware;
use App\Services\UserSecurityService;
use Throwable;

final class SecurityController extends BaseController
{
    public function index(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $userId = (int)(Session::get('auth.user_id') ?? 0);

        try {
            $data = (new UserSecurityService())->data($userId);
        } catch (Throwable) {
            $data = ['loginHistory' => [], 'activeSessions' => [], 'twoFactorData' => [], 'securitySettings' => []];
        }

        $this->userView('user/security/index', array_merge($data, [
            'title'       => 'Security Settings',
            'userSection' => 'security',
        ]));
    }

    public function changePassword(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 422);
        }

        $userId = (int)(Session::get('auth.user_id') ?? 0);

        try {
            (new UserSecurityService())->changePassword(
                $userId,
                (string)$request->input('current_password'),
                (string)$request->input('new_password'),
                (string)$request->input('confirm_password')
            );
            Response::json(['ok' => true, 'message' => 'Password changed successfully']);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function generate2faSecret(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();

        $secret = (new UserSecurityService())->generateTwoFactorSecret();
        Response::json(['ok' => true, 'data' => $secret]);
    }

    public function enable2fa(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 422);
        }

        $userId = (int)(Session::get('auth.user_id') ?? 0);

        try {
            (new UserSecurityService())->enableTwoFactor(
                $userId,
                (string)$request->input('secret'),
                (string)$request->input('code')
            );
            Response::json(['ok' => true, 'message' => '2FA enabled successfully']);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function disable2fa(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 422);
        }

        $userId = (int)(Session::get('auth.user_id') ?? 0);

        try {
            (new UserSecurityService())->disableTwoFactor(
                $userId,
                (string)$request->input('password')
            );
            Response::json(['ok' => true, 'message' => '2FA disabled']);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function revokeSession(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 422);
        }

        $userId    = (int)(Session::get('auth.user_id') ?? 0);
        $sessionId = (int)$request->input('session_id');

        (new UserSecurityService())->revokeSession($userId, $sessionId);
        Response::json(['ok' => true, 'message' => 'Session revoked']);
    }

    public function updateSecuritySettings(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 422);
        }

        $userId = (int)(Session::get('auth.user_id') ?? 0);

        try {
            (new UserSecurityService())->updateSecuritySettings($userId, $request->all());
            Response::json(['ok' => true, 'message' => 'Security settings updated']);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
