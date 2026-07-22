<?php
declare(strict_types=1);
namespace App\Repositories;

use App\Libraries\Database;
use PDO;

final class PortfolioRepository
{
    /** Wallet balances per currency with current value. */
    public function walletBalances(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT c.code, c.name, c.type,
                    COALESCE(w.available_balance, 0) AS available,
                    COALESCE(w.locked_balance, 0)    AS locked,
                    COALESCE(w.available_balance + w.locked_balance, 0) AS total
             FROM currencies c
             LEFT JOIN wallets w ON w.currency_id = c.id AND w.user_id = :uid
             WHERE c.is_active = 1
               AND (w.available_balance > 0 OR w.locked_balance > 0)
             ORDER BY c.type, c.code"
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /** Total deposit / withdrawal summary. */
    public function depositWithdrawalSummary(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN type = 'deposit' AND status = 'completed' THEN amount ELSE 0 END), 0)    AS total_deposited,
                COALESCE(SUM(CASE WHEN type = 'withdrawal' AND status = 'completed' THEN amount ELSE 0 END), 0) AS total_withdrawn
             FROM wallet_transactions
             WHERE user_id = :uid"
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ?: ['total_deposited' => 0, 'total_withdrawn' => 0];
    }

    /** Trade volume and fee summary. */
    public function tradingSummary(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT
                COUNT(*) AS total_trades,
                COALESCE(SUM(t.quote_amount), 0) AS total_volume,
                COALESCE(SUM(CASE WHEN t.buyer_id = :uid THEN t.buyer_fee ELSE t.seller_fee END), 0) AS total_fees,
                COUNT(DISTINCT t.trading_pair_id) AS unique_pairs
             FROM trades t
             WHERE t.buyer_id = :uid2 OR t.seller_id = :uid3"
        );
        $stmt->bindValue(':uid',  $userId, PDO::PARAM_INT);
        $stmt->bindValue(':uid2', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':uid3', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ?: [];
    }

    /** Position PnL summary. */
    public function positionPnlSummary(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT
                COUNT(*) AS total_positions,
                COALESCE(SUM(realized_pnl), 0) AS total_realized_pnl,
                COALESCE(SUM(CASE WHEN status = 'open' THEN unrealized_pnl ELSE 0 END), 0) AS total_unrealized_pnl,
                SUM(CASE WHEN realized_pnl > 0 AND status != 'open' THEN 1 ELSE 0 END) AS winning_trades,
                SUM(CASE WHEN realized_pnl < 0 AND status != 'open' THEN 1 ELSE 0 END) AS losing_trades,
                SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) AS open_positions
             FROM positions
             WHERE user_id = :uid"
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ?: [];
    }

    /** Order summary. */
    public function orderSummary(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT
                COUNT(*) AS total_orders,
                SUM(CASE WHEN status IN ('open','partially_filled') THEN 1 ELSE 0 END) AS open_orders,
                SUM(CASE WHEN status = 'filled' THEN 1 ELSE 0 END) AS filled_orders,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_orders
             FROM orders WHERE user_id = :uid"
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ?: [];
    }

    /** Daily PnL from closed positions for the last N days. */
    public function dailyPnl(int $userId, int $days = 30): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT DATE(closed_at) AS day, SUM(realized_pnl) AS pnl, COUNT(*) AS positions
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

    /** Daily trading volume for the last N days. */
    public function dailyTradingVolume(int $userId, int $days = 30): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT DATE(t.executed_at) AS day,
                    COUNT(*) AS trades,
                    SUM(t.quote_amount) AS volume
             FROM trades t
             WHERE (t.buyer_id = :uid OR t.seller_id = :uid2)
               AND t.executed_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
             GROUP BY DATE(t.executed_at)
             ORDER BY day ASC'
        );
        $stmt->bindValue(':uid',  $userId, PDO::PARAM_INT);
        $stmt->bindValue(':uid2', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':days', $days,   PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /** Volume breakdown by trading pair. */
    public function volumeByPair(int $userId, int $limit = 10): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT tp.symbol,
                    COUNT(*) AS trades,
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

    /** Monthly summary combining trades + positions. */
    public function monthlySummary(int $userId, int $months = 12): array
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

    /** Best and worst individual positions. */
    public function bestWorstPositions(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT p.id, p.position_side AS side, p.entry_price, p.quantity, p.leverage,
                    p.realized_pnl, p.opened_at, p.closed_at,
                    tp.symbol AS pair_symbol
             FROM positions p
             INNER JOIN trading_pairs tp ON tp.id = p.trading_pair_id
             WHERE p.user_id = :uid AND p.status != 'open'
             ORDER BY p.realized_pnl DESC
             LIMIT 5"
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $best = $stmt->fetchAll() ?: [];

        $stmt2 = Database::connection()->prepare(
            "SELECT p.id, p.position_side AS side, p.entry_price, p.quantity, p.leverage,
                    p.realized_pnl, p.opened_at, p.closed_at,
                    tp.symbol AS pair_symbol
             FROM positions p
             INNER JOIN trading_pairs tp ON tp.id = p.trading_pair_id
             WHERE p.user_id = :uid AND p.status != 'open'
             ORDER BY p.realized_pnl ASC
             LIMIT 5"
        );
        $stmt2->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt2->execute();
        $worst = $stmt2->fetchAll() ?: [];

        return ['best' => $best, 'worst' => $worst];
    }

    /** Activity heatmap: trades per day of week and hour. */
    public function activityHeatmap(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT DAYOFWEEK(t.executed_at) - 1 AS dow,
                    HOUR(t.executed_at) AS hour,
                    COUNT(*) AS trade_count
             FROM trades t
             WHERE (t.buyer_id = :uid OR t.seller_id = :uid2)
               AND t.executed_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
             GROUP BY DAYOFWEEK(t.executed_at), HOUR(t.executed_at)
             ORDER BY dow, hour'
        );
        $stmt->bindValue(':uid',  $userId, PDO::PARAM_INT);
        $stmt->bindValue(':uid2', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /** Drawdown calculation: maximum losing streak. */
    public function streakStats(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT realized_pnl
             FROM positions
             WHERE user_id = :uid AND status != 'open'
             ORDER BY closed_at ASC"
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        $maxWin = 0; $maxLoss = 0; $curWin = 0; $curLoss = 0;
        foreach ($rows as $pnl) {
            $pnl = (float)$pnl;
            if ($pnl > 0) {
                $curWin++;
                $curLoss = 0;
                $maxWin  = max($maxWin, $curWin);
            } else {
                $curLoss++;
                $curWin  = 0;
                $maxLoss = max($maxLoss, $curLoss);
            }
        }
        return ['max_win_streak' => $maxWin, 'max_loss_streak' => $maxLoss];
    }
}
