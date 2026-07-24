<?php
declare(strict_types=1);
namespace App\Repositories;

use App\Libraries\Database;
use PDO;

final class AdminPositionsRepository
{
    public function listPositions(array $filters = [], int $limit = 300): array
    {
        $sql = "SELECT p.id, p.position_side AS side, p.status, p.entry_price,
                       p.quantity, p.leverage, p.liquidation_price, p.margin_used,
                       p.unrealized_pnl, p.realized_pnl, p.opened_at, p.closed_at,
                       tp.symbol, tp.market_type,
                       u.username, u.email
                FROM positions p
                INNER JOIN trading_pairs tp ON tp.id = p.trading_pair_id
                INNER JOIN users u ON u.id = p.user_id
                WHERE 1=1";
        $params = [];

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $sql .= ' AND (u.username LIKE :s OR u.email LIKE :s2 OR tp.symbol LIKE :s3)';
            $params[':s']  = '%' . $search . '%';
            $params[':s2'] = '%' . $search . '%';
            $params[':s3'] = '%' . $search . '%';
        }

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

        $symbol = trim((string)($filters['symbol'] ?? ''));
        if ($symbol !== '') {
            $sql .= ' AND tp.symbol = :sym';
            $params[':sym'] = $symbol;
        }

        $userId = (int)($filters['user_id'] ?? 0);
        if ($userId > 0) {
            $sql .= ' AND p.user_id = :uid';
            $params[':uid'] = $userId;
        }

        $dateFrom = trim((string)($filters['date_from'] ?? ''));
        if ($dateFrom !== '') {
            $sql .= ' AND DATE(p.opened_at) >= :df';
            $params[':df'] = $dateFrom;
        }

        $dateTo = trim((string)($filters['date_to'] ?? ''));
        if ($dateTo !== '') {
            $sql .= ' AND DATE(p.opened_at) <= :dt';
            $params[':dt'] = $dateTo;
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

    public function globalStats(): array
    {
        $stmt = Database::connection()->query(
            "SELECT
                COUNT(*) AS total_positions,
                SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) AS open_positions,
                SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) AS closed_positions,
                SUM(CASE WHEN status = 'liquidated' THEN 1 ELSE 0 END) AS liquidated_positions,
                COALESCE(SUM(CASE WHEN status = 'open' THEN unrealized_pnl ELSE 0 END), 0) AS total_unrealized_pnl,
                COALESCE(SUM(margin_used), 0) AS total_margin,
                SUM(CASE WHEN DATE(opened_at) = CURDATE() THEN 1 ELSE 0 END) AS opened_today
             FROM positions"
        );
        return $stmt->fetch() ?: [];
    }

    public function riskExposure(): array
    {
        $stmt = Database::connection()->query(
            "SELECT tp.symbol, p.position_side AS side,
                    COUNT(*) AS position_count,
                    SUM(p.margin_used) AS total_margin,
                    SUM(p.quantity * p.entry_price) AS notional_value,
                    SUM(p.unrealized_pnl) AS total_unrealized_pnl
             FROM positions p
             INNER JOIN trading_pairs tp ON tp.id = p.trading_pair_id
             WHERE p.status = 'open'
             GROUP BY p.trading_pair_id, tp.symbol, p.position_side
             ORDER BY notional_value DESC
             LIMIT 20"
        );
        return $stmt->fetchAll() ?: [];
    }

    public function atRiskPositions(): array
    {
        $stmt = Database::connection()->query(
            "SELECT p.id, p.position_side AS side, p.leverage,
                    p.entry_price, p.liquidation_price, p.quantity,
                    p.margin_used, p.unrealized_pnl,
                    tp.symbol, u.username, u.email
             FROM positions p
             INNER JOIN trading_pairs tp ON tp.id = p.trading_pair_id
             INNER JOIN users u ON u.id = p.user_id
             WHERE p.status = 'open'
               AND p.liquidation_price IS NOT NULL
             ORDER BY p.margin_used DESC
             LIMIT 50"
        );
        return $stmt->fetchAll() ?: [];
    }

    public function forceClosePosition(int $positionId): bool
    {
        $stmt = Database::connection()->prepare(
            "UPDATE positions SET status = 'closed', closed_at = NOW()
             WHERE id = :id AND status = 'open'"
        );
        $stmt->bindValue(':id', $positionId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    public function liquidationHistory(int $limit = 50): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT l.id, l.liquidation_price, l.quantity_liquidated, l.loss_amount,
                    l.insurance_fund_covered, l.created_at,
                    tp.symbol, u.username
             FROM liquidations l
             INNER JOIN positions p ON p.id = l.position_id
             INNER JOIN trading_pairs tp ON tp.id = p.trading_pair_id
             INNER JOIN users u ON u.id = l.user_id
             ORDER BY l.id DESC LIMIT :lim"
        );
        $stmt->bindValue(':lim', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function exportCsv(array $filters = []): array
    {
        return $this->listPositions($filters, 10000);
    }
}
