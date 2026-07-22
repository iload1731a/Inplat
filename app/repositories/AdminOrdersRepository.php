<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Libraries\Database;
use PDO;

final class AdminOrdersRepository
{
    public function listOrders(array $filters = []): array
    {
        $sql = "SELECT o.id, o.uuid, o.client_order_id, o.user_id, u.username, u.email,
                       tp.symbol, o.order_type, o.side, o.status, o.time_in_force,
                       o.quantity, o.filled_quantity, o.remaining_quantity,
                       o.price, o.stop_price, o.average_fill_price,
                       o.quote_amount, o.filled_quote_amount,
                       o.fee_amount, o.fee_currency_id, fc.code AS fee_currency,
                       o.is_reduce_only, o.post_only, o.leverage,
                       o.created_at, o.updated_at, o.cancelled_at, o.filled_at
                FROM orders o
                INNER JOIN users u ON u.id = o.user_id
                INNER JOIN trading_pairs tp ON tp.id = o.pair_id
                LEFT JOIN currencies fc ON fc.id = o.fee_currency_id
                WHERE 1=1";

        $params = [];

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $sql .= ' AND (u.username LIKE :search OR u.email LIKE :search OR o.uuid LIKE :search OR tp.symbol LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        $status = trim((string)($filters['status'] ?? ''));
        if ($status !== '') {
            $sql .= ' AND o.status = :status';
            $params['status'] = $status;
        }

        $side = trim((string)($filters['side'] ?? ''));
        if ($side !== '') {
            $sql .= ' AND o.side = :side';
            $params['side'] = $side;
        }

        $orderType = trim((string)($filters['order_type'] ?? ''));
        if ($orderType !== '') {
            $sql .= ' AND o.order_type = :order_type';
            $params['order_type'] = $orderType;
        }

        $userId = (int)($filters['user_id'] ?? 0);
        if ($userId > 0) {
            $sql .= ' AND o.user_id = :user_id';
            $params['user_id'] = $userId;
        }

        $dateFrom = trim((string)($filters['date_from'] ?? ''));
        if ($dateFrom !== '') {
            $sql .= ' AND DATE(o.created_at) >= :date_from';
            $params['date_from'] = $dateFrom;
        }

        $dateTo = trim((string)($filters['date_to'] ?? ''));
        if ($dateTo !== '') {
            $sql .= ' AND DATE(o.created_at) <= :date_to';
            $params['date_to'] = $dateTo;
        }

        $sql .= ' ORDER BY o.id DESC LIMIT 200';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    public function findOrderById(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT o.*, u.username, u.email, tp.symbol, bc.code AS base_code, qc.code AS quote_code,
                    fc.code AS fee_currency
             FROM orders o
             INNER JOIN users u ON u.id = o.user_id
             INNER JOIN trading_pairs tp ON tp.id = o.pair_id
             INNER JOIN currencies bc ON bc.id = tp.base_currency_id
             INNER JOIN currencies qc ON qc.id = tp.quote_currency_id
             LEFT JOIN currencies fc ON fc.id = o.fee_currency_id
             WHERE o.id = :id LIMIT 1"
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function cancelOrder(int $orderId, string $cancelReason): void
    {
        $stmt = Database::connection()->prepare(
            "UPDATE orders SET status = 'cancelled', cancelled_at = NOW(), cancel_reason = :reason, updated_at = NOW()
             WHERE id = :id AND status IN ('pending','open','partially_filled')"
        );
        $stmt->bindValue(':reason', $cancelReason);
        $stmt->bindValue(':id', $orderId, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function getOrderTrades(int $orderId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT t.id, t.price, t.quantity, t.quote_amount, t.maker_fee, t.taker_fee, t.created_at,
                    bu.username AS buyer_username, su.username AS seller_username
             FROM trades t
             LEFT JOIN users bu ON bu.id = t.buyer_user_id
             LEFT JOIN users su ON su.id = t.seller_user_id
             WHERE t.maker_order_id = :oid OR t.taker_order_id = :oid2
             ORDER BY t.id DESC LIMIT 50"
        );
        $stmt->bindValue(':oid', $orderId, PDO::PARAM_INT);
        $stmt->bindValue(':oid2', $orderId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function getOrderStats(): array
    {
        $stmt = Database::connection()->query(
            "SELECT
                COUNT(*) AS total_orders,
                SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) AS open_orders,
                SUM(CASE WHEN status = 'filled' THEN 1 ELSE 0 END) AS filled_orders,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_orders,
                SUM(CASE WHEN status = 'partially_filled' THEN 1 ELSE 0 END) AS partial_orders,
                SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) AS orders_today
             FROM orders"
        );
        return $stmt->fetch() ?: [];
    }

    public function bulkCancelOrders(array $orderIds, string $reason): int
    {
        if (empty($orderIds)) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
        $stmt = Database::connection()->prepare(
            "UPDATE orders SET status = 'cancelled', cancelled_at = NOW(), cancel_reason = ?, updated_at = NOW()
             WHERE id IN ($placeholders) AND status IN ('pending','open','partially_filled')"
        );
        $stmt->execute(array_merge([$reason], $orderIds));
        return $stmt->rowCount();
    }
}
