<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Libraries\Database;

final class AdminDashboardRepository
{
    private static array $tableExistsCache = [];
    private static array $columnExistsCache = [];

    public function overview(): array
    {
        $pdo = Database::connection();
        $since24h = date('Y-m-d H:i:s', time() - 86400);
        $tradeVolumeStmt = $pdo->prepare('SELECT COALESCE(SUM(quantity), 0) FROM trades WHERE executed_at >= :since');
        $tradeVolumeStmt->bindValue(':since', $since24h);
        $tradeVolumeStmt->execute();
        $depositsStmt = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM deposits WHERE created_at >= :since');
        $depositsStmt->bindValue(':since', $since24h);
        $depositsStmt->execute();
        $withdrawalsStmt = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM withdrawals WHERE requested_at >= :since');
        $withdrawalsStmt->bindValue(':since', $since24h);
        $withdrawalsStmt->execute();
        $feesStmt = $pdo->prepare('SELECT COALESCE(SUM(fee_amount), 0) FROM fee_revenue_ledger WHERE created_at >= :since');
        $feesStmt->bindValue(':since', $since24h);
        $feesStmt->execute();
        $openMaintenance = 0;
        if ($this->tableExists('maintenance_windows')) {
            $activeMaintenanceStmt = $pdo->prepare('SELECT COUNT(*) FROM maintenance_windows WHERE is_active = 1 AND :now BETWEEN starts_at AND ends_at');
            $activeMaintenanceStmt->bindValue(':now', date('Y-m-d H:i:s'));
            $activeMaintenanceStmt->execute();
            $openMaintenance = (int)$activeMaintenanceStmt->fetchColumn();
        }

        return [
            'users' => (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
            'orders' => (int)$pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn(),
            'trades' => (int)$pdo->query('SELECT COUNT(*) FROM trades')->fetchColumn(),
            'revenue_total' => (float)$pdo->query('SELECT COALESCE(SUM(fee_amount), 0) FROM fee_revenue_ledger')->fetchColumn(),
            'deposits_pending' => (int)$pdo->query("SELECT COUNT(*) FROM deposits WHERE status = 'pending'")->fetchColumn(),
            'withdrawals_pending' => (int)$pdo->query("SELECT COUNT(*) FROM withdrawals WHERE status = 'pending'")->fetchColumn(),
            'deposits_24h' => (float)$depositsStmt->fetchColumn(),
            'withdrawals_24h' => (float)$withdrawalsStmt->fetchColumn(),
            'trade_volume_24h' => (float)$tradeVolumeStmt->fetchColumn(),
            'fee_revenue_24h' => (float)$feesStmt->fetchColumn(),
            'unread_notifications' => (int)$pdo->query('SELECT COUNT(*) FROM notifications WHERE is_read = 0')->fetchColumn(),
            'open_tickets' => (int)$pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status IN ('open','pending_admin','awaiting_user')")->fetchColumn(),
            'open_maintenance' => $openMaintenance,
        ];
    }

    public function recentTrades(int $limit = 8): array
    {
        $pairColumn = $this->tradesHasTradingPairId() ? 'trading_pair_id' : 'pair_id';

        $sql = 'SELECT t.id, t.quantity, t.price, t.executed_at, tp.symbol AS pair_symbol
                FROM trades t
                LEFT JOIN trading_pairs tp ON tp.id = t.' . $pairColumn . '
                ORDER BY t.id DESC
                LIMIT :limit';

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public function recentLogins(int $limit = 10): array
    {
        if (!$this->tableExists('login_history')) {
            return [];
        }

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
        if (!$this->tableExists('admin_activity_logs') || !$this->tableExists('admin_users')) {
            return [];
        }

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

    public function candlestickSeries(int $limit = 20, string $intervalCode = '1h'): array
    {
        if (!$this->tableExists('candlesticks')) {
            return [];
        }

        $safeLimit = max(5, $limit);
        $allowedIntervals = ['1m', '5m', '15m', '30m', '1h', '4h', '1d', '1w', '1M'];
        $safeInterval = in_array($intervalCode, $allowedIntervals, true) ? $intervalCode : '1h';
        $sql = 'SELECT c.open_time, c.open_price, c.high_price, c.low_price, c.close_price, tp.symbol AS pair_symbol
                FROM candlesticks c
                INNER JOIN trading_pairs tp ON tp.id = c.trading_pair_id
                WHERE c.interval_code = :interval_code
                ORDER BY c.open_time DESC
                LIMIT :limit';
        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':interval_code', $safeInterval);
        $stmt->bindValue(':limit', $safeLimit, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll() ?: [];

        return array_reverse($rows);
    }

    private function tableExists(string $table): bool
    {
        if (array_key_exists($table, self::$tableExistsCache)) {
            return self::$tableExistsCache[$table];
        }

        $stmt = Database::connection()->prepare('SHOW TABLES LIKE :table');
        $stmt->bindValue(':table', $table);
        $stmt->execute();

        self::$tableExistsCache[$table] = $stmt->fetchColumn() !== false;
        return self::$tableExistsCache[$table];
    }

    private function tradesHasTradingPairId(): bool
    {
        $cacheKey = 'trades:trading_pair_id';
        if (array_key_exists($cacheKey, self::$columnExistsCache)) {
            return self::$columnExistsCache[$cacheKey];
        }

        $stmt = Database::connection()->prepare('SHOW COLUMNS FROM `trades` LIKE :column');
        $stmt->bindValue(':column', 'trading_pair_id');
        $stmt->execute();
        self::$columnExistsCache[$cacheKey] = $stmt->fetchColumn() !== false;

        return self::$columnExistsCache[$cacheKey];
    }
}
