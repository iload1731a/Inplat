<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ConvertRepository;

final class ConvertService
{
    private readonly ConvertRepository $repository;

    public function __construct(?ConvertRepository $repository = null)
    {
        $this->repository = $repository ?? new ConvertRepository();
    }

    public function snapshot(int $userId): array
    {
        return [
            'summary' => $this->repository->getUserConvertSummary($userId),
            'currencies' => $this->repository->getAvailableCurrencies(),
            'recentQuotes' => $this->repository->getUserRecentQuotes($userId),
            'recentTransactions' => $this->repository->getUserConvertTransactions($userId),
        ];
    }
}
