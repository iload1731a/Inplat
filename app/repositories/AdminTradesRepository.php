<?php
declare(strict_types=1);
namespace App\Repositories;

use App\Libraries\Database;
use PDO;

final class AdminTradesRepository
{
    public function listTrades(array $filters = [], int $limit = 300): array
    {
        $sql = "SELECT t.id, t.trade_uuid, t.price, t.quantity, t.quote_amount,
                       t.buyer_fee, t.seller_fee, t.maker_side, t.executed_at,
                       tp.symbol, tp.market_type,
                       bu.username AS buyer_username, bu.email AS buyer_email,
                       su.username AS seller_username, su.email AS seller_email
                FROM trades t
                INNER JOIN trading_pairs tp ON tp.id = t.trading_pair_id
                INNER JOIN users bu ON bu.id = t.buyer_id
                INNER JOIN users su ON su.id = t.seller_id
                WHERE 1=1";
        $params = [];

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $sql .= ' AND (bu.username LIKE :s OR su.username LIKE :s2 OR tp.symbol LIKE :s3 OR t.trade_uuid LIKE :s4)';
            $params[':s']  = '%' . $search . '%';
            $params[':s2'] = '%' . $search . '%';
            $params[':s3'] = '%' . $search . '%';
            $params[':s4'] = '%' . $search . '%';
        }

        $symbol = trim((string)($filters['symbol'] ?? ''));
        if ($symbol !== '') {
            $sql .= ' AND tp.symbol = :sym';
            $params[':sym'] = $symbol;
        }

        $userId = (int)($filters['user_id'] ?? 0);
        if ($userId > 0) {
            $sql .= ' AND (t.buyer_id = :uid OR t.seller_id = :uid2)';
            $params[':uid']  = $userId;
            $params[':uid2'] = $userId;
        }

        $dateFrom = trim((string)($filters['date_from'] ?? ''));
        if ($dateFrom !== '') {
            $sql .= ' AND DATE(t.executed_at) >= :df';
            $params[':df'] = $dateFrom;
        }

        $dateTo = trim((string)($filters['date_to'] ?? ''));
        if ($dateTo !== '') {
            $sql .= ' AND DATE(t.executed_at) <= :dt';
            $params[':dt'] = $dateTo;
        }

        $sql .= ' ORDER BY t.id DESC LIMIT :lim';
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

    public function globalStats(): array
    {
        $stmt = Database::connection()->query(
            "SELECT
                COUNT(*) AS total_trades,
                COALESCE(SUM(quote_amount), 0) AS total_volume,
                COALESCE(SUM(buyer_fee + seller_fee), 0) AS total_fees,
                COALESCE(AVG(quote_amount), 0) AS avg_trade_value,
                COUNT(DISTINCT trading_pair_id) AS active_pairs,
                SUM(CASE WHEN DATE(executed_at) = CURDATE() THEN 1 ELSE 0 END) AS trades_today,
                COALESCE(SUM(CASE WHEN DATE(executed_at) = CURDATE() THEN quote_amount ELSE 0 END), 0) AS volume_today
             FROM trades"
        );
        return $stmt->fetch() ?: [];
    }

    public function dailyVolume(int $days = 30): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT DATE(executed_at) AS day,
                    COUNT(*) AS trade_count,
                    SUM(quote_amount) AS volume,
                    SUM(buyer_fee + seller_fee) AS fees
             FROM trades
             WHERE executed_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
             GROUP BY DATE(executed_at)
             ORDER BY day ASC'
        );
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function volumeByPair(int $limit = 15): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT tp.symbol, tp.market_type,
                    COUNT(*) AS trade_count,
                    SUM(t.quote_amount) AS volume,
                    SUM(t.buyer_fee + t.seller_fee) AS fees
             FROM trades t
             INNER JOIN trading_pairs tp ON tp.id = t.trading_pair_id
             GROUP BY t.trading_pair_id, tp.symbol, tp.market_type
             ORDER BY volume DESC LIMIT :lim'
        );
        $stmt->bindValue(':lim', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function topTraders(int $limit = 10): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT u.username, u.email,
                    COUNT(*) AS trade_count,
                    SUM(t.quote_amount) AS volume
             FROM trades t
             INNER JOIN users u ON u.id = t.buyer_id
             GROUP BY t.buyer_id, u.username, u.email
             UNION ALL
             SELECT u2.username, u2.email,
                    COUNT(*) AS trade_count,
                    SUM(t2.quote_amount) AS volume
             FROM trades t2
             INNER JOIN users u2 ON u2.id = t2.seller_id
             GROUP BY t2.seller_id, u2.username, u2.email'
        );
        $stmt->execute();
        $raw = $stmt->fetchAll() ?: [];

        // Aggregate by username
        $agg = [];
        foreach ($raw as $row) {
            $key = (string)$row['username'];
            if (!isset($agg[$key])) {
                $agg[$key] = ['username' => $row['username'], 'email' => $row['email'], 'trade_count' => 0, 'volume' => 0.0];
            }
            $agg[$key]['trade_count'] += (int)$row['trade_count'];
            $agg[$key]['volume']      += (float)$row['volume'];
        }
        usort($agg, static fn($a, $b) => $b['volume'] <=> $a['volume']);
        return array_slice(array_values($agg), 0, $limit);
    }

    public function feeRevenue(int $days = 30): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT DATE(executed_at) AS day,
                    SUM(buyer_fee + seller_fee) AS fees
             FROM trades
             WHERE executed_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
             GROUP BY DATE(executed_at)
             ORDER BY day ASC'
        );
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function exportCsv(array $filters = []): array
    {
        return $this->listTrades($filters, 10000);
    }
}
