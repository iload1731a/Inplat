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
            "SELECT p.id, p.side, p.status, p.entry_price, p.current_price, p.quantity,
                    p.leverage, p.margin_used, p.realized_pnl, p.unrealized_pnl, p.opened_at,
                    tp.symbol AS pair_symbol
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
            "SELECT p.id, p.side, p.status, p.entry_price, p.current_price, p.quantity,
                    p.leverage, p.realized_pnl, p.opened_at, p.closed_at,
                    tp.symbol AS pair_symbol
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

    public function stats(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT
                COUNT(*) AS total_positions,
                SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) AS open_positions,
                SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) AS closed_positions,
                SUM(CASE WHEN status = 'liquidated' THEN 1 ELSE 0 END) AS liquidated_positions,
                COALESCE(SUM(realized_pnl), 0) AS total_realized_pnl,
                COALESCE(SUM(unrealized_pnl), 0) AS total_unrealized_pnl,
                SUM(CASE WHEN realized_pnl > 0 THEN 1 ELSE 0 END) AS winning_positions
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
            'SELECT DATE(closed_at) AS day, SUM(realized_pnl) AS pnl
             FROM positions
             WHERE user_id = :uid AND closed_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
             GROUP BY DATE(closed_at)
             ORDER BY day ASC'
        );
        $stmt->bindValue(':uid',  $userId, PDO::PARAM_INT);
        $stmt->bindValue(':days', $days,   PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }
}
