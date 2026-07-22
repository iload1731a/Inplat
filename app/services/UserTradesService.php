<?php
declare(strict_types=1);
namespace App\Services;

use App\Repositories\UserTradesRepository;

final class UserTradesService
{
    private readonly UserTradesRepository $repo;

    public function __construct(?UserTradesRepository $repo = null)
    {
        $this->repo = $repo ?? new UserTradesRepository();
    }

    public function trades(int $userId, array $filters = []): array
    {
        if (empty($filters)) {
            return $this->repo->trades($userId);
        }
        return $this->repo->filteredTrades($userId, $filters);
    }

    public function stats(int $userId): array
    {
        return $this->repo->stats($userId);
    }

    public function volumeByPair(int $userId): array
    {
        return $this->repo->volumeByPair($userId);
    }

    public function dailyVolume(int $userId, int $days = 30): array
    {
        return $this->repo->dailyVolume($userId, $days);
    }

    public function monthlyStats(int $userId): array
    {
        return $this->repo->monthlyStats($userId);
    }

    public function exportData(int $userId, array $filters = []): array
    {
        return $this->repo->filteredTrades($userId, $filters, 5000);
    }
}
