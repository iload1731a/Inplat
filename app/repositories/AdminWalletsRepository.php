<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Libraries\Database;
use PDO;

final class AdminWalletsRepository
{
    public function listWallets(array $filters = []): array
    {
        $sql = "SELECT w.id, w.user_id, u.username, u.email,
                       c.code AS currency_code, c.name AS currency_name, c.type AS currency_type,
                       w.wallet_type, w.available_balance, w.locked_balance,
                       w.total_deposited, w.total_withdrawn, w.is_frozen, w.freeze_reason,
                       w.created_at, w.updated_at
                FROM wallets w
                INNER JOIN users u ON u.id = w.user_id
                INNER JOIN currencies c ON c.id = w.currency_id
                WHERE 1=1";
        $params = [];

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $sql .= ' AND (u.username LIKE :search OR u.email LIKE :search OR c.code LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        $currencyCode = trim((string)($filters['currency'] ?? ''));
        if ($currencyCode !== '') {
            $sql .= ' AND c.code = :currency';
            $params['currency'] = $currencyCode;
        }

        $frozen = $filters['is_frozen'] ?? '';
        if ($frozen !== '') {
            $sql .= ' AND w.is_frozen = :is_frozen';
            $params['is_frozen'] = (int)$frozen;
        }

        $sql .= ' ORDER BY w.id DESC LIMIT 200';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    public function findWalletById(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT w.*, u.username, u.email, c.code AS currency_code, c.name AS currency_name
             FROM wallets w
             INNER JOIN users u ON u.id = w.user_id
             INNER JOIN currencies c ON c.id = w.currency_id
             WHERE w.id = :id LIMIT 1"
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function freezeWallet(int $walletId, string $reason): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE wallets SET is_frozen = 1, freeze_reason = :reason, updated_at = NOW() WHERE id = :id'
        );
        $stmt->bindValue(':reason', $reason);
        $stmt->bindValue(':id', $walletId, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function unfreezeWallet(int $walletId): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE wallets SET is_frozen = 0, freeze_reason = NULL, updated_at = NOW() WHERE id = :id'
        );
        $stmt->bindValue(':id', $walletId, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function getLedger(int $walletId, int $limit = 50): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT le.id, le.entry_type, le.amount, le.running_balance, le.reference_type,
                    le.reference_id, le.description, le.created_at
             FROM ledger_entries le
             WHERE le.wallet_id = :wallet_id
             ORDER BY le.id DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':wallet_id', $walletId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function getWalletStats(): array
    {
        $stmt = Database::connection()->query(
            "SELECT
                COUNT(*) AS total_wallets,
                SUM(CASE WHEN is_frozen = 1 THEN 1 ELSE 0 END) AS frozen_wallets,
                COUNT(DISTINCT user_id) AS users_with_wallets
             FROM wallets"
        );
        return $stmt->fetch() ?: [];
    }

    public function getTopWallets(int $limit = 20): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT w.id, u.username, c.code AS currency_code,
                    w.available_balance, w.locked_balance, w.is_frozen
             FROM wallets w
             INNER JOIN users u ON u.id = w.user_id
             INNER JOIN currencies c ON c.id = w.currency_id
             ORDER BY (w.available_balance + w.locked_balance) DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }
}
