<?php

declare(strict_types=1);

namespace App\Controllers\User;

use App\Controllers\BaseController;
use App\Libraries\Request;
use App\Libraries\Response;
use App\Libraries\Session;
use App\Middleware\AuthMiddleware;
use App\Services\PlatformService;

final class PlatformController extends BaseController
{
    public function index(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();

        $userId = (int)(Session::get('auth.user_id') ?? 0);
        $snapshot = (new PlatformService())->userTradingSnapshot($userId);

        $this->view('user/platform', [
            'title' => 'Trading Workspace',
            'username' => (string)(Session::get('auth.username') ?? 'Trader'),
            ...$snapshot,
        ]);
    }

    public function snapshot(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();

        $userId = (int)(Session::get('auth.user_id') ?? 0);
        Response::json(['ok' => true, 'data' => (new PlatformService())->userTradingSnapshot($userId)]);
    }
}
