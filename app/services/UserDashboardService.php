<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\UserDashboardRepository;

final class UserDashboardService
{
    public function __construct(private readonly UserDashboardRepository $dashboard = new UserDashboardRepository())
    {
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
