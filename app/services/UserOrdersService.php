<?php
declare(strict_types=1);
namespace App\Services;

use App\Repositories\UserOrdersRepository;

final class UserOrdersService
{
    private readonly UserOrdersRepository $repo;

    public function __construct(?UserOrdersRepository $repo = null)
    {
        $this->repo = $repo ?? new UserOrdersRepository();
    }

    public function openOrders(int $userId): array
    {
        return $this->repo->openOrders($userId);
    }

    public function orderHistory(int $userId): array
    {
        return $this->repo->orderHistory($userId);
    }

    public function stats(int $userId): array
    {
        return $this->repo->stats($userId);
    }

    public function cancel(int $userId, int $orderId): void
    {
        if (!$this->repo->cancelOrder($userId, $orderId)) {
            throw new \RuntimeException('Order not found or cannot be cancelled');
        }
    }
}
