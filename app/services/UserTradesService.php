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

    public function trades(int $userId): array
    {
        return $this->repo->trades($userId);
    }

    public function stats(int $userId): array
    {
        return $this->repo->stats($userId);
    }

    public function volumeByPair(int $userId): array
    {
        return $this->repo->volumeByPair($userId);
    }
}
