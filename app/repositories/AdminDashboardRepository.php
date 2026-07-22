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
            'revenue_total' => (float)$pdo->query('SELECT COALESCE(SUM(fee_amount), 0) FROM fee_revenue_ledger')->fetchColumn(),
            'deposits_pending' => (int)$pdo->query("SELECT COUNT(*) FROM deposits WHERE status = 'pending'")->fetchColumn(),
            'withdrawals_pending' => (int)$pdo->query("SELECT COUNT(*) FROM withdrawals WHERE status = 'pending'")->fetchColumn(),
            'deposits_24h' => (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM deposits WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)")->fetchColumn(),
            'withdrawals_24h' => (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM withdrawals WHERE requested_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)")->fetchColumn(),
            'trade_volume_24h' => (float)$pdo->query('SELECT COALESCE(SUM(quantity), 0) FROM trades WHERE executed_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)')->fetchColumn(),
            'fee_revenue_24h' => (float)$pdo->query('SELECT COALESCE(SUM(fee_amount), 0) FROM fee_revenue_ledger WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)')->fetchColumn(),
            'unread_notifications' => (int)$pdo->query('SELECT COUNT(*) FROM notifications WHERE is_read = 0')->fetchColumn(),
            'open_tickets' => (int)$pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status IN ('open','pending_admin','awaiting_user')")->fetchColumn(),
            'open_maintenance' => (int)$pdo->query('SELECT COUNT(*) FROM maintenance_windows WHERE is_active = 1 AND NOW() BETWEEN starts_at AND ends_at')->fetchColumn(),
        ];
    }

    public function recentTrades(int $limit = 8): array
    {
        $sql = 'SELECT t.id, t.quantity, t.price, t.executed_at, tp.symbol AS pair_symbol
                FROM trades t
                LEFT JOIN trading_pairs tp ON tp.id = t.trading_pair_id
                ORDER BY t.id DESC
                LIMIT :limit';

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public function recentLogins(int $limit = 10): array
    {
        $sql = 'SELECT lh.created_at, lh.status, lh.ip_address, u.username
                FROM login_history lh
                INNER JOIN users u ON u.id = lh.user_id
                ORDER BY lh.id DESC
                LIMIT :limit';

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public function activityTimeline(int $limit = 10): array
    {
        $sql = 'SELECT aal.action, aal.entity_type, aal.entity_id, aal.created_at, au.username
                FROM admin_activity_logs aal
                INNER JOIN admin_users au ON au.id = aal.admin_id
                ORDER BY aal.id DESC
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
        $sql = "SELECT DATE(executed_at) AS day, COALESCE(SUM(quantity), 0) AS volume
                FROM trades
                WHERE executed_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
                GROUP BY DATE(executed_at)
                ORDER BY day ASC";
        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':days', $safeDays, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public function userGrowthSeries(int $days = 7): array
    {
        $safeDays = max(1, $days);
        $sql = "SELECT DATE(created_at) AS day, COUNT(*) AS total
                FROM users
                WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
                GROUP BY DATE(created_at)
                ORDER BY day ASC";
        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':days', $safeDays, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public function orderStatusSeries(): array
    {
        $sql = 'SELECT status, COUNT(*) AS total
                FROM orders
                GROUP BY status
                ORDER BY total DESC';
        return Database::connection()->query($sql)->fetchAll() ?: [];
    }

    public function revenueSeries(int $days = 7): array
    {
        $safeDays = max(1, $days);
        $sql = "SELECT DATE(created_at) AS day, COALESCE(SUM(fee_amount), 0) AS total
                FROM fee_revenue_ledger
                WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
                GROUP BY DATE(created_at)
                ORDER BY day ASC";
        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':days', $safeDays, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public function candlestickSeries(int $limit = 20): array
    {
        $safeLimit = max(5, $limit);
        $sql = 'SELECT c.open_time, c.open_price, c.high_price, c.low_price, c.close_price, tp.symbol AS pair_symbol
                FROM candlesticks c
                INNER JOIN trading_pairs tp ON tp.id = c.trading_pair_id
                WHERE c.interval_code = :interval_code
                ORDER BY c.open_time DESC
                LIMIT :limit';
        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':interval_code', '1h');
        $stmt->bindValue(':limit', $safeLimit, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll() ?: [];

        return array_reverse($rows);
    }
}
