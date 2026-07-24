<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\RequestContext;
use App\Repositories\AdminOrdersRepository;
use App\Repositories\AdminManagementRepository;
use InvalidArgumentException;

final class AdminOrdersService
{
    public function __construct(
        private readonly AdminOrdersRepository     $ordersRepo = new AdminOrdersRepository(),
        private readonly AdminManagementRepository $mgmtRepo   = new AdminManagementRepository(),
    ) {}

    public function ordersIndex(array $filters): array
    {
        return [
            'orders'     => $this->ordersRepo->listOrders($filters),
            'orderStats' => $this->ordersRepo->getOrderStats(),
            'filters'    => $filters,
        ];
    }

    public function orderDetail(int $orderId): array
    {
        $order = $this->ordersRepo->findOrderById($orderId);
        if ($order === null) {
            throw new InvalidArgumentException('Order not found.');
        }
        return [
            'order'  => $order,
            'trades' => $this->ordersRepo->getOrderTrades($orderId),
        ];
    }

    public function cancelOrder(int $adminId, int $orderId, string $reason): void
    {
        $order = $this->ordersRepo->findOrderById($orderId);
        if ($order === null) {
            throw new InvalidArgumentException('Order not found.');
        }

        if (!in_array((string)($order['status'] ?? ''), ['pending', 'open', 'partially_filled'], true)) {
            throw new InvalidArgumentException('Order cannot be cancelled (status: ' . ($order['status'] ?? 'unknown') . ').');
        }

        if ($reason === '') {
            $reason = 'Cancelled by admin';
        }

        $this->ordersRepo->cancelOrder($orderId, $reason);
        $this->mgmtRepo->logAdminAction($adminId, 'cancel_order', 'orders', (string)$orderId, null, ['reason' => $reason, 'status' => $order['status']], RequestContext::ipAddress());
    }

    public function bulkCancelOrders(int $adminId, array $orderIds, string $reason): int
    {
        if (empty($orderIds)) {
            throw new InvalidArgumentException('No orders selected.');
        }

        if ($reason === '') {
            $reason = 'Bulk cancel by admin';
        }

        $count = $this->ordersRepo->bulkCancelOrders($orderIds, $reason);
        $this->mgmtRepo->logAdminAction($adminId, 'bulk_cancel_orders', 'orders', '0', null, ['count' => $count, 'reason' => $reason], RequestContext::ipAddress());
        return $count;
    }

    public function reports(): array
    {
        return [
            'orderStats'      => $this->ordersRepo->getOrderStats(),
            'dailyVol'        => $this->ordersRepo->dailyOrderVolume(30),
            'byPair'          => $this->ordersRepo->ordersByPair(15),
            'byType'          => $this->ordersRepo->ordersByType(),
            'byStatus'        => $this->ordersRepo->ordersByStatus(),
        ];
    }

    public function exportCsv(array $filters): array
    {
        return $this->ordersRepo->exportCsv($filters);
    }
}
