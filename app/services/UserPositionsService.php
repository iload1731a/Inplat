<?php
declare(strict_types=1);
namespace App\Services;

use App\Repositories\UserPositionsRepository;

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

    public function closePosition(int $userId, int $positionId): void
    {
        if (!$this->repo->closePosition($userId, $positionId)) {
            throw new \RuntimeException('Position not found or already closed');
        }
    }
}
