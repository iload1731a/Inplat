<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Libraries\Database;
use PDO;

final class UserDashboardRepository
{
    private const SIDE_BUY = 'buy';
    private const SIDE_SELL = 'sell';

    public function overview(int $userId): array
    {
        $pdo = Database::connection();

        $walletTotalStmt = $pdo->prepare('SELECT COALESCE(SUM(available_balance + locked_balance), 0) FROM wallets WHERE user_id = :user_id');
        $walletTotalStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $walletTotalStmt->execute();

        $openOrdersStmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = :user_id AND status IN ('open', 'partially_filled')");
        $openOrdersStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $openOrdersStmt->execute();

        $openPositionsStmt = $pdo->prepare("SELECT COUNT(*) FROM positions WHERE user_id = :user_id AND status = 'open'");
        $openPositionsStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $openPositionsStmt->execute();

        $tradeCountStmt = $pdo->prepare('SELECT COUNT(*) FROM trades WHERE buyer_id = :buyer_id OR seller_id = :seller_id');
        $tradeCountStmt->bindValue(':buyer_id', $userId, PDO::PARAM_INT);
        $tradeCountStmt->bindValue(':seller_id', $userId, PDO::PARAM_INT);
        $tradeCountStmt->execute();

        $watchlistStmt = $pdo->prepare('SELECT COUNT(*) FROM watchlists WHERE user_id = :user_id');
        $watchlistStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $watchlistStmt->execute();

        $pnlStmt = $pdo->prepare('SELECT COALESCE(SUM(realized_pnl + unrealized_pnl), 0) FROM positions WHERE user_id = :user_id');
        $pnlStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $pnlStmt->execute();

        return [
            'wallet_total_balance' => (float)$walletTotalStmt->fetchColumn(),
            'open_orders' => (int)$openOrdersStmt->fetchColumn(),
            'open_positions' => (int)$openPositionsStmt->fetchColumn(),
            'total_trades' => (int)$tradeCountStmt->fetchColumn(),
            'watchlist_items' => (int)$watchlistStmt->fetchColumn(),
            'total_pnl' => (float)$pnlStmt->fetchColumn(),
        ];
    }

    public function walletAllocation(int $userId, int $limit = 6): array
    {
        $safeLimit = $this->sanitizePositiveInt($limit);
        $sql = 'SELECT c.code, (w.available_balance + w.locked_balance) AS total_balance
                FROM wallets w
                INNER JOIN currencies c ON c.id = w.currency_id
                WHERE w.user_id = :user_id
                ORDER BY total_balance DESC
                LIMIT :limit';

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $safeLimit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public function recentOrders(int $userId, int $limit = 8): array
    {
        $safeLimit = $this->sanitizePositiveInt($limit);
        $sql = 'SELECT o.id, o.side, o.quantity, o.price, o.status, o.created_at, tp.symbol AS pair_symbol
                FROM orders o
                INNER JOIN trading_pairs tp ON tp.id = o.trading_pair_id
                WHERE o.user_id = :user_id
                ORDER BY o.id DESC
                LIMIT :limit';

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $safeLimit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public function recentTrades(int $userId, int $limit = 8): array
    {
        $safeLimit = $this->sanitizePositiveInt($limit);
        $sql = "SELECT t.id,
                       tp.symbol AS pair_symbol,
                       t.price,
                       t.quantity,
                       t.executed_at,
                       CASE
                           WHEN t.buyer_id = :buyer_id_case THEN :side_buy
                           ELSE :side_sell
                       END AS side
                FROM trades t
                INNER JOIN trading_pairs tp ON tp.id = t.trading_pair_id
                WHERE t.buyer_id = :buyer_id_filter OR t.seller_id = :seller_id_filter
                ORDER BY t.id DESC
                LIMIT :limit";

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':buyer_id_case', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':buyer_id_filter', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':seller_id_filter', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':side_buy', self::SIDE_BUY);
        $stmt->bindValue(':side_sell', self::SIDE_SELL);
        $stmt->bindValue(':limit', $safeLimit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public function pnlSeries(int $userId, int $days = 7): array
    {
        $safeDays = $this->sanitizePositiveInt($days);
        $fromDate = (new \DateTimeImmutable('today'))->modify('-' . ($safeDays - 1) . ' days')->format('Y-m-d H:i:s');
        $sql = "SELECT DATE(closed_at) AS day, COALESCE(SUM(realized_pnl), 0) AS pnl
                FROM positions
                WHERE user_id = :user_id
                  AND closed_at IS NOT NULL
                  AND closed_at >= :from_date
                GROUP BY DATE(closed_at)
                ORDER BY day ASC";

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':from_date', $fromDate);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    private function sanitizePositiveInt(int $value): int
    {
        return max(1, $value);
    }
}
