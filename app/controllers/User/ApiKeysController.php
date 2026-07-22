<?php
declare(strict_types=1);
namespace App\Controllers\User;

use App\Controllers\BaseController;
use App\Libraries\Csrf;
use App\Libraries\Request;
use App\Libraries\Response;
use App\Libraries\Session;
use App\Middleware\AuthMiddleware;
use App\Services\UserApiKeysService;
use Throwable;

final class ApiKeysController extends BaseController
{
    public function index(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $userId = (int)(Session::get('auth.user_id') ?? 0);

        try {
            $apiKeys = (new UserApiKeysService())->keys($userId);
        } catch (Throwable) {
            $apiKeys = [];
        }

        $this->userView('user/apikeys/index', [
            'title'       => 'API Keys',
            'userSection' => 'apikeys',
            'apiKeys'     => $apiKeys,
        ]);
    }

    public function create(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 422);
        }

        $userId = (int)(Session::get('auth.user_id') ?? 0);

        try {
            $result = (new UserApiKeysService())->create($userId, $request->all());
            Response::json([
                'ok'         => true,
                'message'    => 'API key created. Save the secret key — it will not be shown again.',
                'api_key'    => $result['api_key'],
                'api_secret' => $result['api_secret'],
            ]);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function revoke(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 422);
        }

        $userId = (int)(Session::get('auth.user_id') ?? 0);
        $keyId  = (int)$request->input('key_id');

        try {
            (new UserApiKeysService())->revoke($userId, $keyId);
            Response::json(['ok' => true, 'message' => 'API key revoked']);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
