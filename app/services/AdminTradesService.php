<?php
declare(strict_types=1);
namespace App\Services;

use App\Repositories\AdminTradesRepository;

final class AdminTradesService
{
    public function __construct(
        private readonly AdminTradesRepository $repo = new AdminTradesRepository(),
    ) {}

    public function tradesIndex(array $filters): array
    {
        return [
            'trades'     => $this->repo->listTrades($filters),
            'tradeStats' => $this->repo->globalStats(),
            'filters'    => $filters,
        ];
    }

    public function reportsData(): array
    {
        return [
            'stats'       => $this->repo->globalStats(),
            'dailyVolume' => $this->repo->dailyVolume(30),
            'byPair'      => $this->repo->volumeByPair(15),
            'topTraders'  => $this->repo->topTraders(10),
            'feeRevenue'  => $this->repo->feeRevenue(30),
        ];
    }

    public function exportCsv(array $filters): array
    {
        return $this->repo->exportCsv($filters);
    }
}
