<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Libraries\Database;
use PDO;

final class PlatformRepository
{
    public function adminOperationsSnapshot(): array
    {
        $pdo = Database::connection();

        return [
            'user_status' => $pdo->query("SELECT status, COUNT(*) AS total FROM users GROUP BY status ORDER BY total DESC")->fetchAll() ?: [],
            'pending_kyc' => $pdo->query("SELECT kd.id, u.username, kd.document_type, kd.status, kd.created_at FROM kyc_documents kd INNER JOIN users u ON u.id = kd.user_id WHERE kd.status = 'pending' ORDER BY kd.id DESC LIMIT 10")->fetchAll() ?: [],
            'latest_tickets' => $pdo->query("SELECT ticket_number, subject, priority, status, created_at FROM support_tickets ORDER BY id DESC LIMIT 10")->fetchAll() ?: [],
            'latest_deposits' => $pdo->query("SELECT id, amount, status, created_at FROM deposits ORDER BY id DESC LIMIT 10")->fetchAll() ?: [],
            'latest_withdrawals' => $pdo->query("SELECT id, amount, status, requested_at FROM withdrawals ORDER BY id DESC LIMIT 10")->fetchAll() ?: [],
            'settings' => $pdo->query('SELECT setting_key, setting_value FROM system_settings ORDER BY setting_key ASC LIMIT 20')->fetchAll() ?: [],
        ];
    }

    public function userTradingSnapshot(int $userId): array
    {
        $pdo = Database::connection();

        $pairs = $pdo->query('SELECT tp.symbol, tp.market_type, COALESCE(pt.last_price, 0) AS last_price, COALESCE(pt.change_24h_percent, 0) AS change_24h_percent, COALESCE(pt.volume_24h, 0) AS volume_24h FROM trading_pairs tp LEFT JOIN price_tickers pt ON pt.trading_pair_id = tp.id WHERE tp.is_visible = 1 ORDER BY tp.display_order ASC, tp.id ASC LIMIT 20')->fetchAll() ?: [];

        $ordersStmt = $pdo->prepare("SELECT o.id, tp.symbol, o.side, o.price, o.quantity, o.status, o.created_at FROM orders o INNER JOIN trading_pairs tp ON tp.id = o.trading_pair_id WHERE o.user_id = :user_id ORDER BY o.id DESC LIMIT 10");
        $ordersStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $ordersStmt->execute();

        $positionsStmt = $pdo->prepare("SELECT p.id, tp.symbol, p.position_side, p.leverage, p.quantity, p.unrealized_pnl, p.status FROM positions p INNER JOIN trading_pairs tp ON tp.id = p.trading_pair_id WHERE p.user_id = :user_id ORDER BY p.id DESC LIMIT 10");
        $positionsStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $positionsStmt->execute();

        $walletStmt = $pdo->prepare('SELECT c.code, w.wallet_type, w.available_balance, w.locked_balance FROM wallets w INNER JOIN currencies c ON c.id = w.currency_id WHERE w.user_id = :user_id ORDER BY w.id DESC LIMIT 10');
        $walletStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $walletStmt->execute();

        $notificationsStmt = $pdo->prepare('SELECT type, title, channel, is_read, created_at FROM notifications WHERE user_id = :user_id ORDER BY id DESC LIMIT 10');
        $notificationsStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $notificationsStmt->execute();

        $ticketsStmt = $pdo->prepare('SELECT ticket_number, subject, priority, status, created_at FROM support_tickets WHERE user_id = :user_id ORDER BY id DESC LIMIT 10');
        $ticketsStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $ticketsStmt->execute();

        $apiKeysStmt = $pdo->prepare('SELECT label, permissions, is_active, created_at, last_used_at FROM api_keys WHERE user_id = :user_id ORDER BY id DESC LIMIT 10');
        $apiKeysStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $apiKeysStmt->execute();

        return [
            'pairs' => $pairs,
            'openOrders' => $ordersStmt->fetchAll() ?: [],
            'positions' => $positionsStmt->fetchAll() ?: [],
            'wallets' => $walletStmt->fetchAll() ?: [],
            'notifications' => $notificationsStmt->fetchAll() ?: [],
            'tickets' => $ticketsStmt->fetchAll() ?: [],
            'apiKeys' => $apiKeysStmt->fetchAll() ?: [],
        ];
    }
}
