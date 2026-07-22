<?php
declare(strict_types=1);
namespace App\Services;

use App\Repositories\UserPositionsRepository;
use InvalidArgumentException;

final class UserPositionsService
{
    private readonly UserPositionsRepository $repo;

    public function __construct(?UserPositionsRepository $repo = null)
    {
        $this->repo = $repo ?? new UserPositionsRepository();
    }

    public function data(int $userId): array
    {
        return [
            'openPositions'   => $this->repo->openPositions($userId),
            'closedPositions' => $this->repo->closedPositions($userId, 50),
            'stats'           => $this->repo->stats($userId),
            'pnlSeries'       => $this->repo->pnlSeries($userId),
        ];
    }

    public function filteredHistory(int $userId, array $filters = []): array
    {
        return $this->repo->filteredHistory($userId, $filters, 300);
    }

    public function positionDetail(int $userId, int $positionId): array
    {
        $position = $this->repo->findPositionById($userId, $positionId);
        if ($position === null) {
            throw new InvalidArgumentException('Position not found.');
        }
        return ['position' => $position];
    }

    public function closePosition(int $userId, int $positionId): void
    {
        if (!$this->repo->closePosition($userId, $positionId)) {
            throw new \RuntimeException('Position not found or already closed');
        }
    }

    public function addMargin(int $userId, int $positionId, float $amount): void
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Margin amount must be positive.');
        }
        if (!$this->repo->addMargin($userId, $positionId, $amount)) {
            throw new \RuntimeException('Position not found or already closed');
        }
    }

    public function analytics(int $userId): array
    {
        $stats      = $this->repo->stats($userId);
        $pnlSeries  = $this->repo->pnlSeries($userId, 30);
        $pnlByPair  = $this->repo->pnlByPair($userId, 10);
        $monthlyPnl = $this->repo->monthlyPnl($userId, 12);
        $liquidations = $this->repo->liquidationHistory($userId, 20);

        $totalClosed = (int)($stats['closed_positions'] ?? 0) + (int)($stats['liquidated_positions'] ?? 0);
        $winRate     = $totalClosed > 0
            ? round((int)($stats['winning_positions'] ?? 0) / $totalClosed * 100, 1)
            : 0.0;

        return [
            'stats'        => $stats,
            'pnlSeries'    => $pnlSeries,
            'pnlByPair'    => $pnlByPair,
            'monthlyPnl'   => $monthlyPnl,
            'liquidations' => $liquidations,
            'winRate'      => $winRate,
        ];
    }
}
