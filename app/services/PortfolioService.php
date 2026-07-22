<?php
declare(strict_types=1);
namespace App\Services;

use App\Repositories\PortfolioRepository;

final class PortfolioService
{
    private readonly PortfolioRepository $repo;

    public function __construct(?PortfolioRepository $repo = null)
    {
        $this->repo = $repo ?? new PortfolioRepository();
    }

    public function dashboardData(int $userId): array
    {
        $walletBalances  = $this->repo->walletBalances($userId);
        $tradingSummary  = $this->repo->tradingSummary($userId);
        $positionPnl     = $this->repo->positionPnlSummary($userId);
        $orderSummary    = $this->repo->orderSummary($userId);
        $dailyPnl        = $this->repo->dailyPnl($userId, 30);
        $dailyVolume     = $this->repo->dailyTradingVolume($userId, 30);
        $volumeByPair    = $this->repo->volumeByPair($userId, 8);
        $dwSummary       = $this->repo->depositWithdrawalSummary($userId);

        $totalPositions  = (int)($positionPnl['total_positions'] ?? 0);
        $closedPositions = $totalPositions - (int)($positionPnl['open_positions'] ?? 0);
        $winRate = $closedPositions > 0
            ? round((int)($positionPnl['winning_trades'] ?? 0) / $closedPositions * 100, 1)
            : 0.0;

        $totalPnl = (float)($positionPnl['total_realized_pnl'] ?? 0)
                  + (float)($positionPnl['total_unrealized_pnl'] ?? 0);

        return [
            'walletBalances' => $walletBalances,
            'tradingSummary' => $tradingSummary,
            'positionPnl'    => $positionPnl,
            'orderSummary'   => $orderSummary,
            'dailyPnl'       => $dailyPnl,
            'dailyVolume'    => $dailyVolume,
            'volumeByPair'   => $volumeByPair,
            'dwSummary'      => $dwSummary,
            'winRate'        => $winRate,
            'totalPnl'       => $totalPnl,
        ];
    }

    public function analyticsData(int $userId): array
    {
        $bestWorst      = $this->repo->bestWorstPositions($userId);
        $monthlySummary = $this->repo->monthlySummary($userId, 12);
        $activityHeatmap = $this->repo->activityHeatmap($userId);
        $streakStats    = $this->repo->streakStats($userId);
        $positionPnl    = $this->repo->positionPnlSummary($userId);
        $tradingSummary = $this->repo->tradingSummary($userId);

        $closedCount = (int)($positionPnl['total_positions'] ?? 0)
                     - (int)($positionPnl['open_positions'] ?? 0);
        $winRate = $closedCount > 0
            ? round((int)($positionPnl['winning_trades'] ?? 0) / $closedCount * 100, 1)
            : 0.0;

        $profitFactor = 0.0;
        $grossLoss = (float)($positionPnl['gross_loss'] ?? 0);
        if ($grossLoss > 0) {
            $profitFactor = round((float)($positionPnl['gross_profit'] ?? 0) / $grossLoss, 2);
        }

        return [
            'bestPositions'   => $bestWorst['best'],
            'worstPositions'  => $bestWorst['worst'],
            'monthlySummary'  => $monthlySummary,
            'activityHeatmap' => $activityHeatmap,
            'streakStats'     => $streakStats,
            'positionPnl'     => $positionPnl,
            'tradingSummary'  => $tradingSummary,
            'winRate'         => $winRate,
            'profitFactor'    => $profitFactor,
        ];
    }
}
