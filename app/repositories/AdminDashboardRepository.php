<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Libraries\Database;

final class AdminDashboardRepository
{
    public function overview(): array
    {
        $pdo = Database::connection();

        return [
            'users' => (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
            'orders' => (int)$pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn(),
            'trades' => (int)$pdo->query('SELECT COUNT(*) FROM trades')->fetchColumn(),
            'deposits_pending' => (int)$pdo->query("SELECT COUNT(*) FROM deposits WHERE status = 'pending'")->fetchColumn(),
            'withdrawals_pending' => (int)$pdo->query("SELECT COUNT(*) FROM withdrawals WHERE status = 'pending'")->fetchColumn(),
            'trade_volume_24h' => (float)$pdo->query('SELECT COALESCE(SUM(quantity), 0) FROM trades WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)')->fetchColumn(),
            'fee_revenue_24h' => (float)$pdo->query('SELECT COALESCE(SUM(fee_amount), 0) FROM fee_revenue_ledger WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)')->fetchColumn(),
        ];
    }

    public function recentTrades(int $limit = 8): array
    {
        $sql = 'SELECT t.id, t.quantity, t.price, t.created_at, tp.symbol AS pair_symbol
                FROM trades t
                LEFT JOIN trading_pairs tp ON tp.id = t.pair_id
                ORDER BY t.id DESC
                LIMIT :limit';

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public function recentDeposits(int $limit = 8): array
    {
        $sql = 'SELECT id, amount, status, created_at
                FROM deposits
                ORDER BY id DESC
                LIMIT :limit';

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public function recentWithdrawals(int $limit = 8): array
    {
        $sql = 'SELECT id, amount, status, created_at
                FROM withdrawals
                ORDER BY id DESC
                LIMIT :limit';

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public function tradeVolumeSeries(int $days = 7): array
    {
        $safeDays = max(1, $days);
        $sql = "SELECT DATE(created_at) AS day, COALESCE(SUM(quantity), 0) AS volume
                FROM trades
                WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL {$safeDays} DAY)
                GROUP BY DATE(created_at)
                ORDER BY day ASC";

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public function userGrowthSeries(int $days = 7): array
    {
        $safeDays = max(1, $days);
        $sql = "SELECT DATE(created_at) AS day, COUNT(*) AS total
                FROM users
                WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL {$safeDays} DAY)
                GROUP BY DATE(created_at)
                ORDER BY day ASC";

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }
}
