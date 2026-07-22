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
            "SELECT o.id, o.side, o.order_type, o.status, o.quantity, o.price, o.stop_price,
                    o.filled_quantity, o.average_fill_price, o.time_in_force, o.expires_at, o.created_at,
                    tp.symbol AS pair_symbol
             FROM orders o
             INNER JOIN trading_pairs tp ON tp.id = o.trading_pair_id
             WHERE o.user_id = :uid AND o.status IN ('open','partially_filled')
             ORDER BY o.id DESC LIMIT :lim"
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function orderHistory(int $userId, int $limit = 500): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT o.id, o.side, o.order_type, o.status, o.quantity, o.price,
                    o.filled_quantity, o.average_fill_price, o.created_at, o.updated_at,
                    tp.symbol AS pair_symbol
             FROM orders o
             INNER JOIN trading_pairs tp ON tp.id = o.trading_pair_id
             WHERE o.user_id = :uid
             ORDER BY o.id DESC LIMIT :lim"
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function cancelOrder(int $userId, int $orderId): bool
    {
        $stmt = Database::connection()->prepare(
            "UPDATE orders SET status = 'cancelled', updated_at = NOW()
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
                SUM(CASE WHEN side = 'buy' THEN 1 ELSE 0 END) AS buy_orders,
                SUM(CASE WHEN side = 'sell' THEN 1 ELSE 0 END) AS sell_orders
             FROM orders WHERE user_id = :uid"
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ?: [];
    }
}
