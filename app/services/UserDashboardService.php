<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\UserDashboardRepository;

final class UserDashboardService
{
    private readonly UserDashboardRepository $dashboard;

    public function __construct(?UserDashboardRepository $dashboard = null)
    {
        $this->dashboard = $dashboard ?? new UserDashboardRepository();
    }

    public function data(int $userId): array
    {
        return [
            'overview' => $this->dashboard->overview($userId),
            'walletAllocation' => $this->dashboard->walletAllocation($userId),
            'recentOrders' => $this->dashboard->recentOrders($userId),
            'recentTrades' => $this->dashboard->recentTrades($userId),
            'pnlSeries' => $this->dashboard->pnlSeries($userId),
        ];
    }
}
