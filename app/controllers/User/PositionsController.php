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
    private function svc(): UserPositionsService
    {
        return new UserPositionsService();
    }

    public function index(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $userId = (int)(Session::get('auth.user_id') ?? 0);

        try {
            $data      = $this->svc()->data($userId);
            $analytics = $this->svc()->analytics($userId);
        } catch (Throwable) {
            $data      = ['openPositions' => [], 'closedPositions' => [], 'stats' => [], 'pnlSeries' => []];
            $analytics = ['stats' => [], 'pnlSeries' => [], 'pnlByPair' => [], 'monthlyPnl' => [], 'liquidations' => [], 'winRate' => 0];
        }

        $this->userView('user/positions/index', array_merge($data, [
            'title'        => 'Positions',
            'userSection'  => 'positions',
            'analytics'    => $analytics,
            'pnlByPair'    => $analytics['pnlByPair'],
            'monthlyPnl'   => $analytics['monthlyPnl'],
            'liquidations' => $analytics['liquidations'],
            'winRate'      => $analytics['winRate'],
        ]));
    }

    public function history(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $userId = (int)(Session::get('auth.user_id') ?? 0);

        $filters = [
            'status'    => trim((string)$request->input('status', '')),
            'side'      => trim((string)$request->input('side', '')),
            'pair'      => trim((string)$request->input('pair', '')),
            'date_from' => trim((string)$request->input('date_from', '')),
            'date_to'   => trim((string)$request->input('date_to', '')),
        ];

        try {
            $positions = $this->svc()->filteredHistory($userId, $filters);
            $stats     = $this->svc()->analytics($userId);
        } catch (Throwable) {
            $positions = [];
            $stats     = [];
        }

        $this->userView('user/positions/history', [
            'title'       => 'Position History',
            'userSection' => 'positions-history',
            'positions'   => $positions,
            'stats'       => $stats,
            'filters'     => $filters,
        ]);
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
            $this->svc()->closePosition($userId, $positionId);
            Response::json(['ok' => true, 'message' => 'Position closed']);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function addMargin(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 422);
        }

        $userId     = (int)(Session::get('auth.user_id') ?? 0);
        $positionId = (int)$request->input('position_id');
        $amount     = (float)$request->input('amount', 0);

        try {
            $this->svc()->addMargin($userId, $positionId, $amount);
            Response::json(['ok' => true, 'message' => 'Margin added successfully']);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function analytics(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $userId = (int)(Session::get('auth.user_id') ?? 0);

        try {
            $data = $this->svc()->analytics($userId);
        } catch (Throwable) {
            $data = ['stats' => [], 'pnlSeries' => [], 'pnlByPair' => [], 'monthlyPnl' => [], 'liquidations' => [], 'winRate' => 0];
        }

        $this->userView('user/positions/analytics', array_merge($data, [
            'title'       => 'Position Analytics',
            'userSection' => 'positions',
            'breadcrumb'  => [
                ['label' => 'Positions', 'url' => '/user/positions'],
                ['label' => 'Analytics'],
            ],
        ]));
    }
}
