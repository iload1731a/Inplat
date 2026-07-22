<?php
declare(strict_types=1);
namespace App\Controllers\User;

use App\Controllers\BaseController;
use App\Libraries\Csrf;
use App\Libraries\Request;
use App\Libraries\Response;
use App\Libraries\Session;
use App\Middleware\AuthMiddleware;
use App\Services\UserPositionsService;
use Throwable;

final class PositionsController extends BaseController
{
    public function index(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $userId = (int)(Session::get('auth.user_id') ?? 0);

        try {
            $data = (new UserPositionsService())->data($userId);
        } catch (Throwable) {
            $data = ['openPositions' => [], 'closedPositions' => [], 'stats' => [], 'pnlSeries' => []];
        }

        $this->userView('user/positions/index', array_merge($data, [
            'title'       => 'Positions',
            'userSection' => 'positions',
        ]));
    }

    public function close(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 422);
        }

        $userId     = (int)(Session::get('auth.user_id') ?? 0);
        $positionId = (int)$request->input('position_id');

        try {
            (new UserPositionsService())->closePosition($userId, $positionId);
            Response::json(['ok' => true, 'message' => 'Position closed']);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
