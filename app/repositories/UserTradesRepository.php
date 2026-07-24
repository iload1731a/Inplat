<?php
declare(strict_types=1);
namespace App\Repositories;

use App\Libraries\Database;
use PDO;

final class UserTradesRepository
{
    public function trades(int $userId, int $limit = 500): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT t.id, t.trade_uuid, t.price, t.quantity, t.quote_amount, t.maker_side,
                    t.executed_at,
                    tp.symbol AS pair_symbol,
                    CASE WHEN t.buyer_id = :uid THEN \'buy\' ELSE \'sell\' END AS side,
                    CASE WHEN t.buyer_id = :uid2 THEN t.buyer_fee ELSE t.seller_fee END AS fee
             FROM trades t
             INNER JOIN trading_pairs tp ON tp.id = t.trading_pair_id
             WHERE t.buyer_id = :uid3 OR t.seller_id = :uid4
             ORDER BY t.id DESC LIMIT :lim'
        );
        $stmt->bindValue(':uid',  $userId, PDO::PARAM_INT);
        $stmt->bindValue(':uid2', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':uid3', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':uid4', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':lim',  max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function filteredTrades(int $userId, array $filters = [], int $limit = 500): array
    {
        $sql = 'SELECT t.id, t.trade_uuid, t.price, t.quantity, t.quote_amount, t.maker_side,
                       t.executed_at,
                       tp.symbol AS pair_symbol,
                       CASE WHEN t.buyer_id = :uid THEN \'buy\' ELSE \'sell\' END AS side,
                       CASE WHEN t.buyer_id = :uid2 THEN t.buyer_fee ELSE t.seller_fee END AS fee
                FROM trades t
                INNER JOIN trading_pairs tp ON tp.id = t.trading_pair_id
                WHERE (t.buyer_id = :uid3 OR t.seller_id = :uid4)';
        $params = [':uid' => $userId, ':uid2' => $userId, ':uid3' => $userId, ':uid4' => $userId];

        $pair = trim((string)($filters['pair'] ?? ''));
        if ($pair !== '') {
            $sql .= ' AND tp.symbol LIKE :pair';
            $params[':pair'] = '%' . $pair . '%';
        }

        $side = trim((string)($filters['side'] ?? ''));
        if ($side === 'buy') {
            $sql .= ' AND t.buyer_id = :suid';
            $params[':suid'] = $userId;
        } elseif ($side === 'sell') {
            $sql .= ' AND t.seller_id = :suid';
            $params[':suid'] = $userId;
        }

        $dateFrom = trim((string)($filters['date_from'] ?? ''));
        if ($dateFrom !== '') {
            $sql .= ' AND DATE(t.executed_at) >= :date_from';
            $params[':date_from'] = $dateFrom;
        }

        $dateTo = trim((string)($filters['date_to'] ?? ''));
        if ($dateTo !== '') {
            $sql .= ' AND DATE(t.executed_at) <= :date_to';
            $params[':date_to'] = $dateTo;
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

    public function stats(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT
                COUNT(*) AS total_trades,
                COALESCE(SUM(t.quote_amount), 0) AS total_volume,
                COALESCE(SUM(CASE WHEN t.buyer_id = :uid THEN t.buyer_fee ELSE t.seller_fee END), 0) AS total_fees,
                COALESCE(MAX(t.quote_amount), 0) AS largest_trade,
                COALESCE(AVG(t.quote_amount), 0) AS avg_trade_value,
                SUM(CASE WHEN DATE(t.executed_at) = CURDATE() THEN 1 ELSE 0 END) AS trades_today,
                SUM(CASE WHEN t.executed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS trades_7d
             FROM trades t
             WHERE t.buyer_id = :uid2 OR t.seller_id = :uid3'
        );
        $stmt->bindValue(':uid',  $userId, PDO::PARAM_INT);
        $stmt->bindValue(':uid2', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':uid3', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ?: [];
    }

    public function volumeByPair(int $userId, int $limit = 10): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT tp.symbol,
                    COUNT(*) AS trade_count,
                    SUM(t.quote_amount) AS volume,
                    SUM(CASE WHEN t.buyer_id = :uid THEN t.buyer_fee ELSE t.seller_fee END) AS fees
             FROM trades t
             INNER JOIN trading_pairs tp ON tp.id = t.trading_pair_id
             WHERE t.buyer_id = :uid2 OR t.seller_id = :uid3
             GROUP BY t.trading_pair_id, tp.symbol
             ORDER BY volume DESC LIMIT :lim'
        );
        $stmt->bindValue(':uid',  $userId, PDO::PARAM_INT);
        $stmt->bindValue(':uid2', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':uid3', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':lim',  max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function dailyVolume(int $userId, int $days = 30): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT DATE(t.executed_at) AS day,
                    COUNT(*) AS trade_count,
                    SUM(t.quote_amount) AS volume,
                    SUM(CASE WHEN t.buyer_id = :uid THEN t.buyer_fee ELSE t.seller_fee END) AS fees
             FROM trades t
             WHERE (t.buyer_id = :uid2 OR t.seller_id = :uid3)
               AND t.executed_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
             GROUP BY DATE(t.executed_at)
             ORDER BY day ASC'
        );
        $stmt->bindValue(':uid',  $userId, PDO::PARAM_INT);
        $stmt->bindValue(':uid2', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':uid3', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':days', $days,   PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function monthlyStats(int $userId, int $months = 12): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT DATE_FORMAT(t.executed_at, '%Y-%m') AS month,
                    COUNT(*) AS trade_count,
                    SUM(t.quote_amount) AS volume,
                    SUM(CASE WHEN t.buyer_id = :uid THEN t.buyer_fee ELSE t.seller_fee END) AS fees
             FROM trades t
             WHERE (t.buyer_id = :uid2 OR t.seller_id = :uid3)
               AND t.executed_at >= DATE_SUB(NOW(), INTERVAL :months MONTH)
             GROUP BY DATE_FORMAT(t.executed_at, '%Y-%m')
             ORDER BY month ASC"
        );
        $stmt->bindValue(':uid',    $userId, PDO::PARAM_INT);
        $stmt->bindValue(':uid2',   $userId, PDO::PARAM_INT);
        $stmt->bindValue(':uid3',   $userId, PDO::PARAM_INT);
        $stmt->bindValue(':months', $months, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }
}
