<?php
declare(strict_types=1);
namespace App\Repositories;

use App\Libraries\Database;
use PDO;

final class UserWalletRepository
{
    /** Whitelist address cooldown duration before a new address becomes active */
    public const WHITELIST_COOLDOWN = '+24 hours';

    // -------------------------------------------------------------------------
    // Wallets
    // -------------------------------------------------------------------------

    public function wallets(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT w.id, w.wallet_type, w.available_balance, w.locked_balance,
                    w.total_deposited, w.total_withdrawn, w.is_frozen,
                    c.id AS currency_id, c.code, c.name AS currency_name,
                    c.type AS currency_type, c.logo_url
             FROM wallets w
             INNER JOIN currencies c ON c.id = w.currency_id
             WHERE w.user_id = :uid AND c.is_active = 1
             ORDER BY c.type DESC, c.code ASC'
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function walletsByType(int $userId, string $walletType): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT w.id, w.wallet_type, w.available_balance, w.locked_balance,
                    c.id AS currency_id, c.code, c.name AS currency_name, c.logo_url
             FROM wallets w
             INNER JOIN currencies c ON c.id = w.currency_id
             WHERE w.user_id = :uid AND w.wallet_type = :type AND c.is_active = 1
             ORDER BY c.code ASC'
        );
        $stmt->bindValue(':uid',  $userId,     PDO::PARAM_INT);
        $stmt->bindValue(':type', $walletType);
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

    public function walletByUserAndCurrency(int $userId, int $currencyId, string $walletType = 'spot'): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT w.id, w.wallet_type, w.available_balance, w.locked_balance, w.is_frozen,
                    c.code, c.name AS currency_name, c.id AS currency_id
             FROM wallets w
             INNER JOIN currencies c ON c.id = w.currency_id
             WHERE w.user_id = :uid AND w.currency_id = :cid AND w.wallet_type = :type
             LIMIT 1'
        );
        $stmt->bindValue(':uid',  $userId,     PDO::PARAM_INT);
        $stmt->bindValue(':cid',  $currencyId, PDO::PARAM_INT);
        $stmt->bindValue(':type', $walletType);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    // -------------------------------------------------------------------------
    // Deposits — aligned with actual schema columns
    // -------------------------------------------------------------------------

    public function createDeposit(array $data): int
    {
        $pdo  = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO deposits
                (user_id, wallet_id, currency_id, amount, tx_hash, from_address, status, created_at)
             VALUES
                (:uid, :wid, :cid, :amount, :tx_hash, :from_address, :status, NOW())'
        );
        $stmt->bindValue(':uid',          $data['user_id'],     PDO::PARAM_INT);
        $stmt->bindValue(':wid',          $data['wallet_id'],   PDO::PARAM_INT);
        $stmt->bindValue(':cid',          $data['currency_id'], PDO::PARAM_INT);
        $stmt->bindValue(':amount',       $data['amount']);
        $stmt->bindValue(':tx_hash',      $data['tx_hash']      ?? null);
        $stmt->bindValue(':from_address', $data['from_address'] ?? null);
        $stmt->bindValue(':status',       'pending');
        $stmt->execute();
        return (int)$pdo->lastInsertId();
    }

    public function deposits(int $userId, int $limit = 100): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT d.id, d.amount, d.tx_hash, d.from_address, d.to_address,
                    d.confirmations, d.status, d.flagged_reason, d.created_at, d.credited_at,
                    c.code AS currency_code, c.name AS currency_name
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

    // -------------------------------------------------------------------------
    // Withdrawals — aligned with actual schema columns
    // -------------------------------------------------------------------------

    public function createWithdrawal(array $data): int
    {
        $pdo  = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO withdrawals
                (user_id, wallet_id, currency_id, amount, fee,
                 destination_address, destination_tag, status,
                 requires_manual_review, requested_at)
             VALUES
                (:uid, :wid, :cid, :amount, :fee,
                 :addr, :tag, :status, :manual_review, NOW())'
        );
        $stmt->bindValue(':uid',           $data['user_id'],     PDO::PARAM_INT);
        $stmt->bindValue(':wid',           $data['wallet_id'],   PDO::PARAM_INT);
        $stmt->bindValue(':cid',           $data['currency_id'], PDO::PARAM_INT);
        $stmt->bindValue(':amount',        $data['amount']);
        $stmt->bindValue(':fee',           $data['fee'] ?? '0');
        $stmt->bindValue(':addr',          $data['destination_address']);
        $stmt->bindValue(':tag',           $data['destination_tag'] ?? null);
        $stmt->bindValue(':status',        'pending');
        $stmt->bindValue(':manual_review', 1, PDO::PARAM_INT);
        $stmt->execute();
        return (int)$pdo->lastInsertId();
    }

    public function withdrawals(int $userId, int $limit = 100): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT w.id, w.amount, w.fee, w.destination_address, w.destination_tag,
                    w.tx_hash, w.status, w.rejection_reason, w.requested_at, w.processed_at,
                    c.code AS currency_code, c.name AS currency_name
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

    public function findWithdrawalById(int $id, int $userId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT w.*, c.code AS currency_code FROM withdrawals w
             INNER JOIN currencies c ON c.id = w.currency_id
             WHERE w.id = :id AND w.user_id = :uid LIMIT 1'
        );
        $stmt->bindValue(':id',  $id,     PDO::PARAM_INT);
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function cancelWithdrawal(int $id, int $userId): bool
    {
        $stmt = Database::connection()->prepare(
            "UPDATE withdrawals
             SET status = 'cancelled'
             WHERE id = :id AND user_id = :uid AND status = 'pending'"
        );
        $stmt->bindValue(':id',  $id,     PDO::PARAM_INT);
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    // -------------------------------------------------------------------------
    // Wallet Deposit Addresses
    // -------------------------------------------------------------------------

    public function walletAddresses(int $walletId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, address, tag_or_memo, is_active, created_at
             FROM wallet_addresses
             WHERE wallet_id = :wid AND is_active = 1
             ORDER BY id DESC'
        );
        $stmt->bindValue(':wid', $walletId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function addWalletAddress(int $walletId, string $address, ?string $tagOrMemo): int
    {
        $pdo  = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO wallet_addresses (wallet_id, address, tag_or_memo, is_active, created_at)
             VALUES (:wid, :addr, :tag, 1, NOW())'
        );
        $stmt->bindValue(':wid',  $walletId, PDO::PARAM_INT);
        $stmt->bindValue(':addr', $address);
        $stmt->bindValue(':tag',  $tagOrMemo);
        $stmt->execute();
        return (int)$pdo->lastInsertId();
    }

    // -------------------------------------------------------------------------
    // Withdrawal Whitelist Addresses
    // -------------------------------------------------------------------------

    public function whitelistAddresses(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT wwa.id, wwa.address, wwa.tag_or_memo, wwa.label,
                    wwa.status, wwa.cooldown_ends_at, wwa.created_at,
                    c.code AS currency_code, c.name AS currency_name
             FROM withdrawal_whitelist_addresses wwa
             INNER JOIN currencies c ON c.id = wwa.currency_id
             WHERE wwa.user_id = :uid AND wwa.status != :revoked
             ORDER BY wwa.id DESC'
        );
        $stmt->bindValue(':uid',     $userId, PDO::PARAM_INT);
        $stmt->bindValue(':revoked', 'revoked');
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function addWhitelistAddress(int $userId, array $data): int
    {
        $pdo  = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO withdrawal_whitelist_addresses
                (user_id, currency_id, address, tag_or_memo, label,
                 status, cooldown_ends_at, added_via_ip, created_at)
             VALUES
                (:uid, :cid, :addr, :tag, :label,
                 :status, :cooldown, :ip, NOW())'
        );
        $cooldown = date('Y-m-d H:i:s', strtotime(self::WHITELIST_COOLDOWN, time()));
        $stmt->bindValue(':uid',      $userId,              PDO::PARAM_INT);
        $stmt->bindValue(':cid',      (int)$data['currency_id'], PDO::PARAM_INT);
        $stmt->bindValue(':addr',     $data['address']);
        $stmt->bindValue(':tag',      $data['tag_or_memo']  ?? null);
        $stmt->bindValue(':label',    $data['label']        ?? null);
        $stmt->bindValue(':status',   'pending_cooldown');
        $stmt->bindValue(':cooldown', $cooldown);
        $stmt->bindValue(':ip',       $data['ip']           ?? null);
        $stmt->execute();
        return (int)$pdo->lastInsertId();
    }

    public function revokeWhitelistAddress(int $id, int $userId): bool
    {
        $stmt = Database::connection()->prepare(
            "UPDATE withdrawal_whitelist_addresses
             SET status = 'revoked', revoked_at = NOW()
             WHERE id = :id AND user_id = :uid"
        );
        $stmt->bindValue(':id',  $id,     PDO::PARAM_INT);
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    public function activeWhitelistAddress(int $userId, int $currencyId, string $address): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT id, address, tag_or_memo, label, status, cooldown_ends_at
             FROM withdrawal_whitelist_addresses
             WHERE user_id = :uid AND currency_id = :cid AND address = :addr
               AND status = 'active'
             LIMIT 1"
        );
        $stmt->bindValue(':uid',  $userId,     PDO::PARAM_INT);
        $stmt->bindValue(':cid',  $currencyId, PDO::PARAM_INT);
        $stmt->bindValue(':addr', $address);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }
}
