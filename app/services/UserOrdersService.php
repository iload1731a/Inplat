<?php
declare(strict_types=1);
namespace App\Services;

use App\Repositories\UserOrdersRepository;
use InvalidArgumentException;

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

    public function orderHistory(int $userId, array $filters = []): array
    {
        return $this->repo->filteredHistory($userId, $filters);
    }

    public function stats(int $userId): array
    {
        return $this->repo->stats($userId);
    }

    public function orderDetail(int $userId, int $orderId): array
    {
        $order = $this->repo->findOrderById($userId, $orderId);
        if ($order === null) {
            throw new InvalidArgumentException('Order not found.');
        }
        $trades = $this->repo->orderTrades($orderId, $userId);
        return ['order' => $order, 'trades' => $trades];
    }

    public function cancel(int $userId, int $orderId): void
    {
        if (!$this->repo->cancelOrder($userId, $orderId)) {
            throw new \RuntimeException('Order not found or cannot be cancelled');
        }
    }

    public function ordersByPair(int $userId): array
    {
        return $this->repo->ordersByPair($userId);
    }

    public function monthlyStats(int $userId): array
    {
        return $this->repo->monthlyOrderStats($userId);
    }

    public function exportData(int $userId, array $filters = []): array
    {
        return $this->repo->exportFiltered($userId, $filters);
    }
}
