<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\StakingRepository;

final class StakingService
{
    private readonly StakingRepository $repository;

    public function __construct(?StakingRepository $repository = null)
    {
        $this->repository = $repository ?? new StakingRepository();
    }

    public function snapshot(int $userId): array
    {
        return [
            'summary' => $this->repository->getUserStakingSummary($userId),
            'pools' => $this->repository->listActivePools(),
            'userStakes' => $this->repository->getUserStakes($userId),
            'recentRewards' => $this->repository->getUserStakingRewards($userId),
        ];
    }
}
