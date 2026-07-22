<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\PlatformRepository;

final class PlatformService
{
    public function __construct(private readonly PlatformRepository $platform = new PlatformRepository())
    {
    }

    public function adminOperationsSnapshot(): array
    {
        return $this->platform->adminOperationsSnapshot();
    }

    public function userTradingSnapshot(int $userId): array
    {
        return $this->platform->userTradingSnapshot($userId);
    }
}
