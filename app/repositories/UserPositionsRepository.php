<?php
declare(strict_types=1);
namespace App\Repositories;

use App\Libraries\Database;
use PDO;

final class UserPositionsRepository
{
    public function openPositions(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT p.id, p.position_side AS side, p.status, p.entry_price, p.quantity,
                    p.leverage, p.liquidation_price, p.margin_used,
                    p.realized_pnl, p.unrealized_pnl, p.opened_at,
                    tp.symbol AS pair_symbol, tp.market_type
             FROM positions p
             INNER JOIN trading_pairs tp ON tp.id = p.trading_pair_id
             WHERE p.user_id = :uid AND p.status = 'open'
             ORDER BY p.id DESC"
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function closedPositions(int $userId, int $limit = 200): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT p.id, p.position_side AS side, p.status, p.entry_price, p.quantity,
                    p.leverage, p.margin_used, p.realized_pnl, p.unrealized_pnl,
                    p.opened_at, p.closed_at,
                    tp.symbol AS pair_symbol, tp.market_type
             FROM positions p
             INNER JOIN trading_pairs tp ON tp.id = p.trading_pair_id
             WHERE p.user_id = :uid AND p.status != 'open'
             ORDER BY p.id DESC LIMIT :lim"
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function filteredHistory(int $userId, array $filters = [], int $limit = 200): array
    {
        $sql = "SELECT p.id, p.position_side AS side, p.status, p.entry_price, p.quantity,
                       p.leverage, p.margin_used, p.realized_pnl, p.unrealized_pnl,
                       p.opened_at, p.closed_at, p.liquidation_price,
                       tp.symbol AS pair_symbol, tp.market_type
                FROM positions p
                INNER JOIN trading_pairs tp ON tp.id = p.trading_pair_id
                WHERE p.user_id = :uid";
        $params = [':uid' => $userId];

        $status = trim((string)($filters['status'] ?? ''));
        if ($status !== '') {
            $sql .= ' AND p.status = :status';
            $params[':status'] = $status;
        }

        $side = trim((string)($filters['side'] ?? ''));
        if ($side !== '') {
            $sql .= ' AND p.position_side = :side';
            $params[':side'] = $side;
        }

        $pair = trim((string)($filters['pair'] ?? ''));
        if ($pair !== '') {
            $sql .= ' AND tp.symbol LIKE :pair';
            $params[':pair'] = '%' . $pair . '%';
        }

        $dateFrom = trim((string)($filters['date_from'] ?? ''));
        if ($dateFrom !== '') {
            $sql .= ' AND DATE(p.opened_at) >= :date_from';
            $params[':date_from'] = $dateFrom;
        }

        $dateTo = trim((string)($filters['date_to'] ?? ''));
        if ($dateTo !== '') {
            $sql .= ' AND DATE(p.opened_at) <= :date_to';
            $params[':date_to'] = $dateTo;
        }

        $sql .= ' ORDER BY p.id DESC LIMIT :lim';
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

    public function findPositionById(int $userId, int $positionId): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT p.id, p.position_side AS side, p.status, p.entry_price, p.quantity,
                    p.leverage, p.liquidation_price, p.margin_used,
                    p.realized_pnl, p.unrealized_pnl, p.opened_at, p.closed_at,
                    tp.symbol AS pair_symbol, tp.market_type, tp.max_leverage,
                    bc.code AS base_currency, qc.code AS quote_currency
             FROM positions p
             INNER JOIN trading_pairs tp ON tp.id = p.trading_pair_id
             INNER JOIN currencies bc ON bc.id = tp.base_currency_id
             INNER JOIN currencies qc ON qc.id = tp.quote_currency_id
             WHERE p.id = :pid AND p.user_id = :uid
             LIMIT 1"
        );
        $stmt->bindValue(':pid', $positionId, PDO::PARAM_INT);
        $stmt->bindValue(':uid', $userId,     PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function closePosition(int $userId, int $positionId): bool
    {
        $stmt = Database::connection()->prepare(
            "UPDATE positions SET status = 'closed', closed_at = NOW()
             WHERE id = :pid AND user_id = :uid AND status = 'open'"
        );
        $stmt->bindValue(':pid', $positionId, PDO::PARAM_INT);
        $stmt->bindValue(':uid', $userId,     PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    public function addMargin(int $userId, int $positionId, float $amount): bool
    {
        $stmt = Database::connection()->prepare(
            "UPDATE positions
             SET margin_used = margin_used + :amount
             WHERE id = :pid AND user_id = :uid AND status = 'open'"
        );
        $stmt->bindValue(':amount', $amount);
        $stmt->bindValue(':pid',    $positionId, PDO::PARAM_INT);
        $stmt->bindValue(':uid',    $userId,     PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    public function stats(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT
                COUNT(*) AS total_positions,
                SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) AS open_positions,
                SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) AS closed_positions,
                SUM(CASE WHEN status = 'liquidated' THEN 1 ELSE 0 END) AS liquidated_positions,
                COALESCE(SUM(realized_pnl), 0) AS total_realized_pnl,
                COALESCE(SUM(CASE WHEN status = 'open' THEN unrealized_pnl ELSE 0 END), 0) AS total_unrealized_pnl,
                COALESCE(SUM(margin_used), 0) AS total_margin_used,
                SUM(CASE WHEN realized_pnl > 0 AND status != 'open' THEN 1 ELSE 0 END) AS winning_positions,
                SUM(CASE WHEN realized_pnl < 0 AND status != 'open' THEN 1 ELSE 0 END) AS losing_positions,
                COALESCE(MAX(realized_pnl), 0) AS best_trade_pnl,
                COALESCE(MIN(CASE WHEN status != 'open' THEN realized_pnl END), 0) AS worst_trade_pnl
             FROM positions WHERE user_id = :uid"
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ?: [];
    }

    public function pnlSeries(int $userId, int $days = 30): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT DATE(closed_at) AS day, SUM(realized_pnl) AS pnl, COUNT(*) AS trades
             FROM positions
             WHERE user_id = :uid AND status != \'open\'
               AND closed_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
             GROUP BY DATE(closed_at)
             ORDER BY day ASC'
        );
        $stmt->bindValue(':uid',  $userId, PDO::PARAM_INT);
        $stmt->bindValue(':days', $days,   PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function pnlByPair(int $userId, int $limit = 10): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT tp.symbol,
                    COUNT(*) AS position_count,
                    SUM(realized_pnl) AS total_pnl,
                    SUM(CASE WHEN realized_pnl > 0 THEN 1 ELSE 0 END) AS wins,
                    SUM(CASE WHEN realized_pnl < 0 THEN 1 ELSE 0 END) AS losses
             FROM positions p
             INNER JOIN trading_pairs tp ON tp.id = p.trading_pair_id
             WHERE p.user_id = :uid AND p.status != 'open'
             GROUP BY p.trading_pair_id, tp.symbol
             ORDER BY ABS(SUM(realized_pnl)) DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function monthlyPnl(int $userId, int $months = 12): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT DATE_FORMAT(closed_at, '%Y-%m') AS month,
                    SUM(realized_pnl) AS pnl,
                    COUNT(*) AS positions,
                    SUM(CASE WHEN realized_pnl > 0 THEN 1 ELSE 0 END) AS wins
             FROM positions
             WHERE user_id = :uid AND status != 'open'
               AND closed_at >= DATE_SUB(NOW(), INTERVAL :months MONTH)
             GROUP BY DATE_FORMAT(closed_at, '%Y-%m')
             ORDER BY month ASC"
        );
        $stmt->bindValue(':uid',    $userId, PDO::PARAM_INT);
        $stmt->bindValue(':months', $months, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function liquidationHistory(int $userId, int $limit = 50): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT l.id, l.liquidation_price, l.quantity_liquidated, l.loss_amount,
                    l.insurance_fund_covered, l.created_at,
                    tp.symbol AS pair_symbol
             FROM liquidations l
             INNER JOIN positions p ON p.id = l.position_id
             INNER JOIN trading_pairs tp ON tp.id = p.trading_pair_id
             WHERE l.user_id = :uid
             ORDER BY l.id DESC LIMIT :lim"
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }
}
