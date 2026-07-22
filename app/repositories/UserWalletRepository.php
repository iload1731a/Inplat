<?php
declare(strict_types=1);
namespace App\Repositories;

use App\Libraries\Database;
use PDO;

final class UserWalletRepository
{
    public function wallets(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT w.id, w.available_balance, w.locked_balance,
                    c.code, c.name AS currency_name, c.type AS currency_type, c.logo_url
             FROM wallets w
             INNER JOIN currencies c ON c.id = w.currency_id
             WHERE w.user_id = :uid AND c.is_active = 1
             ORDER BY c.type DESC, c.code ASC'
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function activeCurrencies(): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, code, name, type, logo_url FROM currencies WHERE is_active = 1 ORDER BY type DESC, code ASC'
        );
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function walletByUserAndCurrency(int $userId, int $currencyId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT w.id, w.available_balance, w.locked_balance,
                    c.code, c.name AS currency_name
             FROM wallets w
             INNER JOIN currencies c ON c.id = w.currency_id
             WHERE w.user_id = :uid AND w.currency_id = :cid LIMIT 1'
        );
        $stmt->bindValue(':uid', $userId,     PDO::PARAM_INT);
        $stmt->bindValue(':cid', $currencyId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function createDeposit(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO deposits
                (user_id, wallet_id, currency_id, amount, fee, net_amount,
                 method, reference, status, payment_proof_url, created_at)
             VALUES
                (:uid, :wid, :cid, :amount, :fee, :net_amount,
                 :method, :reference, :status, :proof, NOW())'
        );
        $stmt->bindValue(':uid',        $data['user_id'],          PDO::PARAM_INT);
        $stmt->bindValue(':wid',        $data['wallet_id']  ?? null);
        $stmt->bindValue(':cid',        $data['currency_id'],       PDO::PARAM_INT);
        $stmt->bindValue(':amount',     $data['amount']);
        $stmt->bindValue(':fee',        $data['fee']        ?? '0.00000000');
        $stmt->bindValue(':net_amount', $data['net_amount'] ?? $data['amount']);
        $stmt->bindValue(':method',     $data['method']     ?? 'manual');
        $stmt->bindValue(':reference',  $data['reference']  ?? null);
        $stmt->bindValue(':status',     'pending');
        $stmt->bindValue(':proof',      $data['payment_proof_url'] ?? null);
        $stmt->execute();
        return (int)Database::connection()->lastInsertId();
    }

    public function createWithdrawal(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO withdrawals
                (user_id, wallet_id, currency_id, amount, fee, net_amount,
                 destination_address, destination_memo, method, status, created_at)
             VALUES
                (:uid, :wid, :cid, :amount, :fee, :net_amount,
                 :addr, :memo, :method, :status, NOW())'
        );
        $stmt->bindValue(':uid',    $data['user_id'],           PDO::PARAM_INT);
        $stmt->bindValue(':wid',    $data['wallet_id']  ?? null);
        $stmt->bindValue(':cid',    $data['currency_id'],       PDO::PARAM_INT);
        $stmt->bindValue(':amount', $data['amount']);
        $stmt->bindValue(':fee',    $data['fee']        ?? '0.00000000');
        $stmt->bindValue(':net_amount', $data['net_amount'] ?? $data['amount']);
        $stmt->bindValue(':addr',   $data['destination_address']);
        $stmt->bindValue(':memo',   $data['destination_memo']  ?? null);
        $stmt->bindValue(':method', $data['method']     ?? 'crypto');
        $stmt->bindValue(':status', 'pending');
        $stmt->execute();
        return (int)Database::connection()->lastInsertId();
    }

    public function deposits(int $userId, int $limit = 100): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT d.id, d.amount, d.fee, d.net_amount, d.method, d.reference,
                    d.status, d.payment_proof_url, d.created_at, d.confirmed_at,
                    c.code AS currency_code
             FROM deposits d
             INNER JOIN currencies c ON c.id = d.currency_id
             WHERE d.user_id = :uid
             ORDER BY d.id DESC LIMIT :lim'
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function withdrawals(int $userId, int $limit = 100): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT w.id, w.amount, w.fee, w.net_amount, w.destination_address,
                    w.destination_memo, w.method, w.status, w.created_at, w.processed_at,
                    c.code AS currency_code
             FROM withdrawals w
             INNER JOIN currencies c ON c.id = w.currency_id
             WHERE w.user_id = :uid
             ORDER BY w.id DESC LIMIT :lim'
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }
}
