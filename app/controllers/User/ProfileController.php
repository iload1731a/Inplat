<?php
declare(strict_types=1);
namespace App\Controllers\User;

use App\Controllers\BaseController;
use App\Libraries\Csrf;
use App\Libraries\Request;
use App\Libraries\Response;
use App\Libraries\Session;
use App\Middleware\AuthMiddleware;
use App\Services\UserProfileService;
use Throwable;

final class ProfileController extends BaseController
{
    public function index(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $userId = (int)(Session::get('auth.user_id') ?? 0);

        try {
            $profile = (new UserProfileService())->get($userId);
        } catch (Throwable) {
            $profile = [];
        }

        $this->userView('user/profile/index', [
            'title'       => 'My Profile',
            'userSection' => 'profile',
            'profile'     => $profile,
        ]);
    }

    public function update(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 422);
        }

        $userId = (int)(Session::get('auth.user_id') ?? 0);

        try {
            (new UserProfileService())->update($userId, $request->all());
            Response::json(['ok' => true, 'message' => 'Profile updated successfully']);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function uploadAvatar(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 422);
        }

        $userId = (int)(Session::get('auth.user_id') ?? 0);
        $file   = $_FILES['avatar'] ?? null;

        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            Response::json(['ok' => false, 'message' => 'No file uploaded'], 422);
        }

        try {
            $url = (new UserProfileService())->updateAvatar($userId, $file);
            Response::json(['ok' => true, 'message' => 'Avatar updated', 'url' => $url]);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
