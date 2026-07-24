<?php
declare(strict_types=1);
namespace App\Repositories;

use App\Libraries\Database;
use PDO;

final class UserOrdersRepository
{
    public function openOrders(int $userId, int $limit = 200): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT o.id, o.order_uuid, o.side, ot.name AS order_type, o.status,
                    o.quantity, o.price, o.stop_price, o.filled_quantity,
                    o.average_fill_price, o.time_in_force, o.leverage, o.created_at,
                    tp.symbol AS pair_symbol, tp.market_type
             FROM orders o
             INNER JOIN trading_pairs tp ON tp.id = o.trading_pair_id
             INNER JOIN order_types ot ON ot.id = o.order_type_id
             WHERE o.user_id = :uid AND o.status IN ('open','partially_filled')
             ORDER BY o.id DESC LIMIT :lim"
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function filteredHistory(int $userId, array $filters = [], int $limit = 500): array
    {
        $sql = "SELECT o.id, o.order_uuid, o.side, ot.name AS order_type, o.status,
                       o.quantity, o.price, o.filled_quantity, o.average_fill_price,
                       o.leverage, o.source, o.created_at, o.updated_at, o.cancelled_at,
                       tp.symbol AS pair_symbol, tp.market_type
                FROM orders o
                INNER JOIN trading_pairs tp ON tp.id = o.trading_pair_id
                INNER JOIN order_types ot ON ot.id = o.order_type_id
                WHERE o.user_id = :uid";
        $params = [':uid' => $userId];

        $pair = trim((string)($filters['pair'] ?? ''));
        if ($pair !== '') {
            $sql .= ' AND tp.symbol LIKE :pair';
            $params[':pair'] = '%' . $pair . '%';
        }

        $status = trim((string)($filters['status'] ?? ''));
        if ($status !== '') {
            $sql .= ' AND o.status = :status';
            $params[':status'] = $status;
        }

        $side = trim((string)($filters['side'] ?? ''));
        if ($side !== '') {
            $sql .= ' AND o.side = :side';
            $params[':side'] = $side;
        }

        $orderType = trim((string)($filters['order_type'] ?? ''));
        if ($orderType !== '') {
            $sql .= ' AND ot.name = :order_type';
            $params[':order_type'] = $orderType;
        }

        $dateFrom = trim((string)($filters['date_from'] ?? ''));
        if ($dateFrom !== '') {
            $sql .= ' AND DATE(o.created_at) >= :date_from';
            $params[':date_from'] = $dateFrom;
        }

        $dateTo = trim((string)($filters['date_to'] ?? ''));
        if ($dateTo !== '') {
            $sql .= ' AND DATE(o.created_at) <= :date_to';
            $params[':date_to'] = $dateTo;
        }

        $sql .= ' ORDER BY o.id DESC LIMIT :lim';
        $params[':lim'] = max(1, $limit);

        $stmt = Database::connection()->prepare($sql);
        foreach ($params as $key => $value) {
            if (is_int($value)) {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value);
            }
        }
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function orderHistory(int $userId, int $limit = 500): array
    {
        return $this->filteredHistory($userId, [], $limit);
    }

    public function findOrderById(int $userId, int $orderId): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT o.id, o.order_uuid, o.side, ot.name AS order_type, o.status,
                    o.quantity, o.price, o.stop_price, o.filled_quantity, o.remaining_quantity,
                    o.average_fill_price, o.time_in_force, o.leverage, o.is_reduce_only,
                    o.client_order_id, o.rejection_reason, o.source,
                    o.created_at, o.updated_at, o.cancelled_at,
                    tp.symbol AS pair_symbol, tp.market_type,
                    bc.code AS base_currency, qc.code AS quote_currency
             FROM orders o
             INNER JOIN trading_pairs tp ON tp.id = o.trading_pair_id
             INNER JOIN order_types ot ON ot.id = o.order_type_id
             INNER JOIN currencies bc ON bc.id = tp.base_currency_id
             INNER JOIN currencies qc ON qc.id = tp.quote_currency_id
             WHERE o.id = :oid AND o.user_id = :uid
             LIMIT 1"
        );
        $stmt->bindValue(':oid', $orderId, PDO::PARAM_INT);
        $stmt->bindValue(':uid', $userId,  PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function orderTrades(int $orderId, int $userId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT t.id, t.price, t.quantity, t.quote_amount, t.maker_side,
                    CASE WHEN t.buyer_id = :uid THEN t.buyer_fee ELSE t.seller_fee END AS fee,
                    CASE WHEN t.buyer_id = :uid2 THEN 'buy' ELSE 'sell' END AS side,
                    t.executed_at
             FROM trades t
             WHERE (t.buy_order_id = :oid OR t.sell_order_id = :oid2)
               AND (t.buyer_id = :uid3 OR t.seller_id = :uid4)
             ORDER BY t.id ASC"
        );
        $stmt->bindValue(':oid',  $orderId, PDO::PARAM_INT);
        $stmt->bindValue(':oid2', $orderId, PDO::PARAM_INT);
        $stmt->bindValue(':uid',  $userId,  PDO::PARAM_INT);
        $stmt->bindValue(':uid2', $userId,  PDO::PARAM_INT);
        $stmt->bindValue(':uid3', $userId,  PDO::PARAM_INT);
        $stmt->bindValue(':uid4', $userId,  PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function cancelOrder(int $userId, int $orderId): bool
    {
        $stmt = Database::connection()->prepare(
            "UPDATE orders SET status = 'cancelled', cancelled_at = NOW(), updated_at = NOW()
             WHERE id = :oid AND user_id = :uid AND status IN ('open','partially_filled')"
        );
        $stmt->bindValue(':oid', $orderId, PDO::PARAM_INT);
        $stmt->bindValue(':uid', $userId,  PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    public function stats(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT
                COUNT(*) AS total_orders,
                SUM(CASE WHEN status IN ('open','partially_filled') THEN 1 ELSE 0 END) AS open_orders,
                SUM(CASE WHEN status = 'filled' THEN 1 ELSE 0 END) AS filled_orders,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_orders,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected_orders,
                SUM(CASE WHEN side = 'buy' THEN 1 ELSE 0 END) AS buy_orders,
                SUM(CASE WHEN side = 'sell' THEN 1 ELSE 0 END) AS sell_orders,
                SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) AS orders_today,
                SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS orders_7d
             FROM orders WHERE user_id = :uid"
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ?: [];
    }

    public function ordersByPair(int $userId, int $limit = 10): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT tp.symbol, COUNT(*) AS order_count,
                    SUM(CASE WHEN o.status = 'filled' THEN 1 ELSE 0 END) AS filled_count
             FROM orders o
             INNER JOIN trading_pairs tp ON tp.id = o.trading_pair_id
             WHERE o.user_id = :uid
             GROUP BY o.trading_pair_id, tp.symbol
             ORDER BY order_count DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function monthlyOrderStats(int $userId, int $months = 6): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month,
                    COUNT(*) AS total,
                    SUM(CASE WHEN status = 'filled' THEN 1 ELSE 0 END) AS filled,
                    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled
             FROM orders
             WHERE user_id = :uid AND created_at >= DATE_SUB(NOW(), INTERVAL :months MONTH)
             GROUP BY DATE_FORMAT(created_at, '%Y-%m')
             ORDER BY month ASC"
        );
        $stmt->bindValue(':uid',    $userId, PDO::PARAM_INT);
        $stmt->bindValue(':months', $months, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function exportFiltered(int $userId, array $filters = []): array
    {
        return $this->filteredHistory($userId, $filters, 5000);
    }
}
