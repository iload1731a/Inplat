<?php
declare(strict_types=1);
namespace App\Controllers\User;

use App\Controllers\BaseController;
use App\Libraries\Request;
use App\Libraries\Session;
use App\Middleware\AuthMiddleware;
use App\Services\PortfolioService;
use Throwable;

final class PortfolioController extends BaseController
{
    private function svc(): PortfolioService
    {
        return new PortfolioService();
    }

    public function index(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $userId = (int)(Session::get('auth.user_id') ?? 0);

        try {
            $data = $this->svc()->dashboardData($userId);
        } catch (Throwable) {
            $data = [
                'walletBalances' => [], 'tradingSummary' => [], 'positionPnl' => [],
                'orderSummary'   => [], 'dailyPnl'       => [], 'dailyVolume' => [],
                'volumeByPair'   => [], 'dwSummary'      => [], 'winRate'     => 0,
                'totalPnl'       => 0,
            ];
        }

        $this->userView('user/portfolio/index', array_merge($data, [
            'title'       => 'Portfolio',
            'userSection' => 'portfolio',
        ]));
    }

    public function analytics(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $userId = (int)(Session::get('auth.user_id') ?? 0);

        try {
            $data = $this->svc()->analyticsData($userId);
        } catch (Throwable) {
            $data = [
                'bestPositions' => [], 'worstPositions' => [], 'monthlySummary' => [],
                'activityHeatmap' => [], 'streakStats' => [], 'positionPnl' => [],
                'tradingSummary' => [], 'winRate' => 0, 'profitFactor' => 0,
            ];
        }

        $this->userView('user/portfolio/analytics', array_merge($data, [
            'title'       => 'Performance Analytics',
            'userSection' => 'portfolio',
            'breadcrumb'  => [
                ['label' => 'Portfolio', 'url' => '/user/portfolio'],
                ['label' => 'Analytics'],
            ],
        ]));
    }
}
