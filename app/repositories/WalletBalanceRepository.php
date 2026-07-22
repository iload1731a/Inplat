<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Libraries\Database;
use PDO;
use RuntimeException;

/**
 * Atomic balance engine – every credit/debit is wrapped in a PDO transaction
 * and writes a matching double-entry ledger_entries row.
 */
final class WalletBalanceRepository
{
    // -------------------------------------------------------------------------
    // Wallet lookup / creation
    // -------------------------------------------------------------------------

    public function findOrCreate(int $userId, int $currencyId, string $walletType = 'spot'): array
    {
        $allowed = ['spot', 'margin', 'futures', 'funding'];
        if (!in_array($walletType, $allowed, true)) {
            throw new \InvalidArgumentException("Invalid wallet type: {$walletType}");
        }

        $pdo  = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT w.id, w.available_balance, w.locked_balance, w.is_frozen, w.freeze_reason,
                    c.code, c.name AS currency_name
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

        if ($row !== false) {
            return $row;
        }

        // Auto-create wallet
        $ins = $pdo->prepare(
            'INSERT INTO wallets (user_id, currency_id, wallet_type, available_balance, locked_balance,
                                  total_deposited, total_withdrawn, is_frozen, created_at, updated_at)
             VALUES (:uid, :cid, :type, 0, 0, 0, 0, 0, NOW(), NOW())'
        );
        $ins->bindValue(':uid',  $userId,     PDO::PARAM_INT);
        $ins->bindValue(':cid',  $currencyId, PDO::PARAM_INT);
        $ins->bindValue(':type', $walletType);
        $ins->execute();
        $newId = (int)$pdo->lastInsertId();

        return ['id' => $newId, 'available_balance' => '0', 'locked_balance' => '0',
                'is_frozen' => 0, 'freeze_reason' => null];
    }

    public function findById(int $walletId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT w.id, w.user_id, w.available_balance, w.locked_balance, w.is_frozen,
                    w.total_deposited, w.total_withdrawn, w.wallet_type,
                    c.code, c.name AS currency_name, c.id AS currency_id
             FROM wallets w
             INNER JOIN currencies c ON c.id = w.currency_id
             WHERE w.id = :id LIMIT 1'
        );
        $stmt->bindValue(':id', $walletId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    // -------------------------------------------------------------------------
    // Credit (add available balance)
    // -------------------------------------------------------------------------

    public function credit(
        int    $walletId,
        string $amount,
        string $referenceType,
        int    $referenceId,
        string $notes = '',
        ?int   $adminId = null
    ): void {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'SELECT available_balance, locked_balance, is_frozen
                 FROM wallets WHERE id = :id FOR UPDATE'
            );
            $stmt->bindValue(':id', $walletId, PDO::PARAM_INT);
            $stmt->execute();
            $wallet = $stmt->fetch();
            if ($wallet === false) {
                throw new RuntimeException('Wallet not found');
            }
            if ((int)$wallet['is_frozen'] === 1) {
                throw new RuntimeException('Wallet is frozen');
            }

            $newBalance = bcadd((string)$wallet['available_balance'], $amount, 18);

            $upd = $pdo->prepare(
                'UPDATE wallets
                 SET available_balance = :bal,
                     total_deposited   = total_deposited + :amt,
                     updated_at        = NOW()
                 WHERE id = :id'
            );
            $upd->bindValue(':bal', $newBalance);
            $upd->bindValue(':amt', $amount);
            $upd->bindValue(':id',  $walletId, PDO::PARAM_INT);
            $upd->execute();

            $this->writeLedger($pdo, $walletId, $referenceType, $referenceId,
                               'credit', $amount, $newBalance, $notes, $adminId);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    // -------------------------------------------------------------------------
    // Debit (subtract available balance)
    // -------------------------------------------------------------------------

    public function debit(
        int    $walletId,
        string $amount,
        string $referenceType,
        int    $referenceId,
        string $notes = '',
        ?int   $adminId = null
    ): void {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'SELECT available_balance, locked_balance, is_frozen
                 FROM wallets WHERE id = :id FOR UPDATE'
            );
            $stmt->bindValue(':id', $walletId, PDO::PARAM_INT);
            $stmt->execute();
            $wallet = $stmt->fetch();
            if ($wallet === false) {
                throw new RuntimeException('Wallet not found');
            }
            if ((int)$wallet['is_frozen'] === 1) {
                throw new RuntimeException('Wallet is frozen');
            }
            if (bccomp((string)$wallet['available_balance'], $amount, 18) < 0) {
                throw new RuntimeException('Insufficient available balance');
            }

            $newBalance = bcsub((string)$wallet['available_balance'], $amount, 18);

            $upd = $pdo->prepare(
                'UPDATE wallets
                 SET available_balance = :bal,
                     total_withdrawn   = total_withdrawn + :amt,
                     updated_at        = NOW()
                 WHERE id = :id'
            );
            $upd->bindValue(':bal', $newBalance);
            $upd->bindValue(':amt', $amount);
            $upd->bindValue(':id',  $walletId, PDO::PARAM_INT);
            $upd->execute();

            $this->writeLedger($pdo, $walletId, $referenceType, $referenceId,
                               'debit', $amount, $newBalance, $notes, $adminId);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    // -------------------------------------------------------------------------
    // Lock funds (move available → locked for open orders)
    // -------------------------------------------------------------------------

    public function lock(int $walletId, string $amount): void
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT available_balance, locked_balance FROM wallets WHERE id = :id FOR UPDATE');
            $stmt->bindValue(':id', $walletId, PDO::PARAM_INT);
            $stmt->execute();
            $wallet = $stmt->fetch();
            if ($wallet === false) {
                throw new RuntimeException('Wallet not found');
            }
            if (bccomp((string)$wallet['available_balance'], $amount, 18) < 0) {
                throw new RuntimeException('Insufficient available balance to lock');
            }

            $upd = $pdo->prepare(
                'UPDATE wallets
                 SET available_balance = available_balance - :amt,
                     locked_balance    = locked_balance    + :amt,
                     updated_at        = NOW()
                 WHERE id = :id'
            );
            $upd->bindValue(':amt', $amount);
            $upd->bindValue(':id',  $walletId, PDO::PARAM_INT);
            $upd->execute();
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    // -------------------------------------------------------------------------
    // Unlock funds (move locked → available)
    // -------------------------------------------------------------------------

    public function unlock(int $walletId, string $amount): void
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT available_balance, locked_balance FROM wallets WHERE id = :id FOR UPDATE');
            $stmt->bindValue(':id', $walletId, PDO::PARAM_INT);
            $stmt->execute();
            $wallet = $stmt->fetch();
            if ($wallet === false) {
                throw new RuntimeException('Wallet not found');
            }
            if (bccomp((string)$wallet['locked_balance'], $amount, 18) < 0) {
                throw new RuntimeException('Insufficient locked balance to unlock');
            }

            $upd = $pdo->prepare(
                'UPDATE wallets
                 SET available_balance = available_balance + :amt,
                     locked_balance    = locked_balance    - :amt,
                     updated_at        = NOW()
                 WHERE id = :id'
            );
            $upd->bindValue(':amt', $amount);
            $upd->bindValue(':id',  $walletId, PDO::PARAM_INT);
            $upd->execute();
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    // -------------------------------------------------------------------------
    // Ledger query
    // -------------------------------------------------------------------------

    public function getLedger(int $walletId, int $limit = 100, int $offset = 0): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, reference_type, reference_id, direction, amount, balance_after, notes, created_at
             FROM ledger_entries
             WHERE wallet_id = :wid
             ORDER BY id DESC
             LIMIT :lim OFFSET :off'
        );
        $stmt->bindValue(':wid', $walletId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', max(1, $limit),  PDO::PARAM_INT);
        $stmt->bindValue(':off', max(0, $offset), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function countLedger(int $walletId): int
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM ledger_entries WHERE wallet_id = :wid'
        );
        $stmt->bindValue(':wid', $walletId, PDO::PARAM_INT);
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function writeLedger(
        \PDO   $pdo,
        int    $walletId,
        string $referenceType,
        int    $referenceId,
        string $direction,
        string $amount,
        string $balanceAfter,
        string $notes,
        ?int   $adminId
    ): void {
        $stmt = $pdo->prepare(
            'INSERT INTO ledger_entries
                (wallet_id, reference_type, reference_id, direction, amount, balance_after, notes, created_by_admin, created_at)
             VALUES
                (:wid, :ref_type, :ref_id, :dir, :amt, :bal, :notes, :admin, NOW())'
        );
        $stmt->bindValue(':wid',      $walletId,     PDO::PARAM_INT);
        $stmt->bindValue(':ref_type', $referenceType);
        $stmt->bindValue(':ref_id',   $referenceId,  PDO::PARAM_INT);
        $stmt->bindValue(':dir',      $direction);
        $stmt->bindValue(':amt',      $amount);
        $stmt->bindValue(':bal',      $balanceAfter);
        $stmt->bindValue(':notes',    $notes ?: null);
        $stmt->bindValue(':admin',    $adminId,      PDO::PARAM_INT);
        $stmt->execute();
    }
}
