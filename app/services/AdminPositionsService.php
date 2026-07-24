<?php
declare(strict_types=1);
namespace App\Services;

use App\Repositories\AdminPositionsRepository;
use InvalidArgumentException;

final class AdminPositionsService
{
    public function __construct(
        private readonly AdminPositionsRepository $repo = new AdminPositionsRepository(),
    ) {}

    public function positionsIndex(array $filters): array
    {
        return [
            'positions'  => $this->repo->listPositions($filters),
            'posStats'   => $this->repo->globalStats(),
            'filters'    => $filters,
        ];
    }

    public function riskData(): array
    {
        return [
            'posStats'       => $this->repo->globalStats(),
            'riskExposure'   => $this->repo->riskExposure(),
            'atRisk'         => $this->repo->atRiskPositions(),
            'liquidations'   => $this->repo->liquidationHistory(30),
        ];
    }

    public function forceClose(int $positionId): void
    {
        if ($positionId <= 0) {
            throw new InvalidArgumentException('Invalid position ID.');
        }
        if (!$this->repo->forceClosePosition($positionId)) {
            throw new \RuntimeException('Position not found or already closed.');
        }
    }

    public function exportCsv(array $filters): array
    {
        return $this->repo->exportCsv($filters);
    }
}
