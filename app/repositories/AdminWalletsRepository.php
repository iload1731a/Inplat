<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Libraries\Database;
use PDO;

final class AdminWalletsRepository
{
    // -------------------------------------------------------------------------
    // Wallet listing
    // -------------------------------------------------------------------------

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

        $walletType = trim((string)($filters['wallet_type'] ?? ''));
        if ($walletType !== '') {
            $sql .= ' AND w.wallet_type = :wallet_type';
            $params['wallet_type'] = $walletType;
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

    // -------------------------------------------------------------------------
    // Ledger
    // -------------------------------------------------------------------------

    public function getLedger(int $walletId, int $limit = 100): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT le.id, le.reference_type, le.reference_id,
                    le.direction, le.amount, le.balance_after, le.notes, le.created_at,
                    au.username AS admin_username
             FROM ledger_entries le
             LEFT JOIN admin_users au ON au.id = le.created_by_admin
             WHERE le.wallet_id = :wallet_id
             ORDER BY le.id DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':wallet_id', $walletId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    // -------------------------------------------------------------------------
    // Stats & dashboard widgets
    // -------------------------------------------------------------------------

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
                    w.available_balance, w.locked_balance, w.is_frozen, w.wallet_type
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

    // -------------------------------------------------------------------------
    // Deposits queue
    // -------------------------------------------------------------------------

    public function listDeposits(array $filters = [], int $limit = 200): array
    {
        $sql = "SELECT d.id, d.user_id, d.wallet_id, d.amount,
                       d.tx_hash, d.from_address, d.to_address,
                       d.confirmations, d.status, d.flagged_reason,
                       d.created_at, d.credited_at,
                       u.username, u.email,
                       c.code AS currency_code, c.name AS currency_name
                FROM deposits d
                INNER JOIN users u       ON u.id = d.user_id
                INNER JOIN currencies c  ON c.id = d.currency_id
                WHERE 1=1";
        $params = [];

        $status = trim((string)($filters['status'] ?? ''));
        if ($status !== '') {
            $sql .= ' AND d.status = :status';
            $params['status'] = $status;
        }

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $sql .= ' AND (u.username LIKE :s OR u.email LIKE :s2 OR d.tx_hash LIKE :s3)';
            $params['s']  = '%' . $search . '%';
            $params['s2'] = '%' . $search . '%';
            $params['s3'] = '%' . $search . '%';
        }

        $sql .= ' ORDER BY d.id DESC LIMIT ' . max(1, $limit);

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    public function findDepositById(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT d.*, u.username, u.email, c.code AS currency_code, c.name AS currency_name
             FROM deposits d
             INNER JOIN users u      ON u.id = d.user_id
             INNER JOIN currencies c ON c.id = d.currency_id
             WHERE d.id = :id LIMIT 1"
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function updateDepositStatus(int $id, string $status, ?int $reviewedBy, ?string $flagReason): void
    {
        $creditedAt = $status === 'credited' ? 'NOW()' : 'NULL';
        $stmt = Database::connection()->prepare(
            "UPDATE deposits
             SET status = :status,
                 flagged_reason = :flag_reason,
                 reviewed_by = :reviewed_by,
                 credited_at = {$creditedAt}
             WHERE id = :id"
        );
        $stmt->bindValue(':status',      $status);
        $stmt->bindValue(':flag_reason', $flagReason);
        $stmt->bindValue(':reviewed_by', $reviewedBy, PDO::PARAM_INT);
        $stmt->bindValue(':id',          $id,         PDO::PARAM_INT);
        $stmt->execute();
    }

    public function depositStats(): array
    {
        $stmt = Database::connection()->query(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'pending'   THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN status = 'credited'  THEN 1 ELSE 0 END) AS credited,
                SUM(CASE WHEN status = 'flagged'   THEN 1 ELSE 0 END) AS flagged,
                SUM(CASE WHEN status = 'failed'    THEN 1 ELSE 0 END) AS failed,
                SUM(CASE WHEN status = 'credited'  THEN amount ELSE 0 END) AS total_credited_amount
             FROM deposits"
        );
        return $stmt->fetch() ?: [];
    }

    // -------------------------------------------------------------------------
    // Withdrawals queue
    // -------------------------------------------------------------------------

    public function listWithdrawals(array $filters = [], int $limit = 200): array
    {
        $sql = "SELECT w.id, w.user_id, w.wallet_id, w.amount, w.fee,
                       w.destination_address, w.destination_tag, w.tx_hash,
                       w.status, w.requires_manual_review,
                       w.rejection_reason, w.requested_at, w.processed_at,
                       u.username, u.email,
                       c.code AS currency_code, c.name AS currency_name
                FROM withdrawals w
                INNER JOIN users u       ON u.id = w.user_id
                INNER JOIN currencies c  ON c.id = w.currency_id
                WHERE 1=1";
        $params = [];

        $status = trim((string)($filters['status'] ?? ''));
        if ($status !== '') {
            $sql .= ' AND w.status = :status';
            $params['status'] = $status;
        }

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $sql .= ' AND (u.username LIKE :s OR u.email LIKE :s2 OR w.destination_address LIKE :s3 OR w.tx_hash LIKE :s4)';
            $params['s']  = '%' . $search . '%';
            $params['s2'] = '%' . $search . '%';
            $params['s3'] = '%' . $search . '%';
            $params['s4'] = '%' . $search . '%';
        }

        $sql .= ' ORDER BY w.id DESC LIMIT ' . max(1, $limit);

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    public function findWithdrawalById(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT w.*, u.username, u.email, c.code AS currency_code, c.name AS currency_name
             FROM withdrawals w
             INNER JOIN users u      ON u.id = w.user_id
             INNER JOIN currencies c ON c.id = w.currency_id
             WHERE w.id = :id LIMIT 1"
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function updateWithdrawalStatus(
        int     $id,
        string  $status,
        ?int    $reviewedBy,
        ?string $txHash,
        ?string $rejectionReason
    ): void {
        $processedAt = in_array($status, ['completed', 'rejected', 'cancelled'], true) ? 'NOW()' : 'NULL';
        $stmt = Database::connection()->prepare(
            "UPDATE withdrawals
             SET status           = :status,
                 reviewed_by      = :reviewed_by,
                 tx_hash          = :tx_hash,
                 rejection_reason = :rejection_reason,
                 processed_at     = {$processedAt}
             WHERE id = :id"
        );
        $stmt->bindValue(':status',           $status);
        $stmt->bindValue(':reviewed_by',      $reviewedBy, PDO::PARAM_INT);
        $stmt->bindValue(':tx_hash',          $txHash);
        $stmt->bindValue(':rejection_reason', $rejectionReason);
        $stmt->bindValue(':id',               $id,         PDO::PARAM_INT);
        $stmt->execute();
    }

    public function withdrawalStats(): array
    {
        $stmt = Database::connection()->query(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'pending'    THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN status = 'approved'   THEN 1 ELSE 0 END) AS approved,
                SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) AS processing,
                SUM(CASE WHEN status = 'completed'  THEN 1 ELSE 0 END) AS completed,
                SUM(CASE WHEN status = 'rejected'   THEN 1 ELSE 0 END) AS rejected,
                SUM(CASE WHEN status = 'completed'  THEN amount ELSE 0 END) AS total_completed_amount
             FROM withdrawals"
        );
        return $stmt->fetch() ?: [];
    }

    // -------------------------------------------------------------------------
    // Manual adjustment
    // -------------------------------------------------------------------------

    public function adjustmentHistory(int $limit = 100): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT le.id, le.wallet_id, le.direction, le.amount, le.balance_after,
                    le.notes, le.created_at,
                    u.username, c.code AS currency_code,
                    au.username AS admin_username
             FROM ledger_entries le
             INNER JOIN wallets w    ON w.id  = le.wallet_id
             INNER JOIN users u      ON u.id  = w.user_id
             INNER JOIN currencies c ON c.id  = w.currency_id
             LEFT  JOIN admin_users au ON au.id = le.created_by_admin
             WHERE le.reference_type = 'adjustment' AND le.created_by_admin IS NOT NULL
             ORDER BY le.id DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':lim', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    // -------------------------------------------------------------------------
    // Transaction monitoring rules
    // -------------------------------------------------------------------------

    public function listMonitoringRules(): array
    {
        $stmt = Database::connection()->query(
            "SELECT tmr.*, au.username AS created_by_username
             FROM transaction_monitoring_rules tmr
             LEFT JOIN admin_users au ON au.id = tmr.created_by
             ORDER BY tmr.id DESC"
        );
        return $stmt->fetchAll() ?: [];
    }

    public function findMonitoringRule(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM transaction_monitoring_rules WHERE id = :id LIMIT 1'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function createMonitoringRule(array $data, int $adminId): int
    {
        $pdo  = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO transaction_monitoring_rules
                (rule_name, rule_type, conditions, action, is_active, created_by, created_at, updated_at)
             VALUES (:name, :type, :conditions, :action, :active, :admin, NOW(), NOW())'
        );
        $stmt->bindValue(':name',       $data['rule_name']);
        $stmt->bindValue(':type',       $data['rule_type']);
        $stmt->bindValue(':conditions', $data['conditions']);
        $stmt->bindValue(':action',     $data['action']);
        $stmt->bindValue(':active',     1, PDO::PARAM_INT);
        $stmt->bindValue(':admin',      $adminId, PDO::PARAM_INT);
        $stmt->execute();
        return (int)$pdo->lastInsertId();
    }

    public function toggleMonitoringRule(int $id, int $isActive): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE transaction_monitoring_rules SET is_active = :active, updated_at = NOW() WHERE id = :id'
        );
        $stmt->bindValue(':active', $isActive, PDO::PARAM_INT);
        $stmt->bindValue(':id',     $id,       PDO::PARAM_INT);
        $stmt->execute();
    }

    public function deleteMonitoringRule(int $id): void
    {
        $stmt = Database::connection()->prepare(
            'DELETE FROM transaction_monitoring_rules WHERE id = :id'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }
}
