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
            'SELECT t.id, t.price, t.quantity, t.executed_at,
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

    public function stats(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT
                COUNT(*) AS total_trades,
                COALESCE(SUM(t.price * t.quantity), 0) AS total_volume,
                COALESCE(SUM(CASE WHEN t.buyer_id = :uid THEN t.buyer_fee ELSE t.seller_fee END), 0) AS total_fees,
                COALESCE(MAX(t.price * t.quantity), 0) AS largest_trade
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
            'SELECT tp.symbol, SUM(t.price * t.quantity) AS volume
             FROM trades t
             INNER JOIN trading_pairs tp ON tp.id = t.trading_pair_id
             WHERE t.buyer_id = :uid OR t.seller_id = :uid2
             GROUP BY tp.id, tp.symbol
             ORDER BY volume DESC LIMIT :lim'
        );
        $stmt->bindValue(':uid',  $userId, PDO::PARAM_INT);
        $stmt->bindValue(':uid2', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':lim',  max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }
}
