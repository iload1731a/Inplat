<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AdminDashboardRepository;

final class AdminDashboardService
{
    public function __construct(private readonly AdminDashboardRepository $dashboard = new AdminDashboardRepository())
    {
    }

    public function data(): array
    {
        return [
            'overview' => $this->dashboard->overview(),
            'recentTrades' => $this->dashboard->recentTrades(),
            'recentDeposits' => $this->dashboard->recentDeposits(),
            'recentWithdrawals' => $this->dashboard->recentWithdrawals(),
            'recentLogins' => $this->dashboard->recentLogins(),
            'activityTimeline' => $this->dashboard->activityTimeline(),
            'tradeVolumeSeries' => $this->dashboard->tradeVolumeSeries(),
            'userGrowthSeries' => $this->dashboard->userGrowthSeries(),
            'revenueSeries' => $this->dashboard->revenueSeries(),
            'orderStatusSeries' => $this->dashboard->orderStatusSeries(),
            'candlestickSeries' => $this->dashboard->candlestickSeries(),
        ];
    }
}
