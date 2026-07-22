<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Libraries\Database;
use PDO;
use RuntimeException;

/**
 * Internal transfer repository – both same-user wallet-type transfers
 * and user-to-user transfers, all atomic.
 */
final class WalletTransferRepository
{
    // -------------------------------------------------------------------------
    // Internal transfer between two wallets (same or different users)
    // -------------------------------------------------------------------------

    public function transfer(
        int    $fromWalletId,
        int    $toWalletId,
        int    $fromUserId,
        int    $toUserId,
        int    $currencyId,
        string $amount,
        string $note = ''
    ): int {
        if (bccomp($amount, '0', 18) <= 0) {
            throw new \InvalidArgumentException('Transfer amount must be greater than zero');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            // Lock sender wallet
            $stmtFrom = $pdo->prepare(
                'SELECT available_balance, is_frozen FROM wallets WHERE id = :id FOR UPDATE'
            );
            $stmtFrom->bindValue(':id', $fromWalletId, PDO::PARAM_INT);
            $stmtFrom->execute();
            $from = $stmtFrom->fetch();
            if ($from === false) {
                throw new RuntimeException('Source wallet not found');
            }
            if ((int)$from['is_frozen'] === 1) {
                throw new RuntimeException('Source wallet is frozen');
            }
            if (bccomp((string)$from['available_balance'], $amount, 18) < 0) {
                throw new RuntimeException('Insufficient balance for transfer');
            }

            // Lock receiver wallet
            $stmtTo = $pdo->prepare(
                'SELECT available_balance, is_frozen FROM wallets WHERE id = :id FOR UPDATE'
            );
            $stmtTo->bindValue(':id', $toWalletId, PDO::PARAM_INT);
            $stmtTo->execute();
            $to = $stmtTo->fetch();
            if ($to === false) {
                throw new RuntimeException('Destination wallet not found');
            }

            $newFromBal = bcsub((string)$from['available_balance'], $amount, 18);
            $newToBal   = bcadd((string)$to['available_balance'],   $amount, 18);

            // Deduct from source
            $updFrom = $pdo->prepare(
                'UPDATE wallets SET available_balance = :bal, updated_at = NOW() WHERE id = :id'
            );
            $updFrom->bindValue(':bal', $newFromBal);
            $updFrom->bindValue(':id',  $fromWalletId, PDO::PARAM_INT);
            $updFrom->execute();

            // Add to destination
            $updTo = $pdo->prepare(
                'UPDATE wallets SET available_balance = :bal, updated_at = NOW() WHERE id = :id'
            );
            $updTo->bindValue(':bal', $newToBal);
            $updTo->bindValue(':id',  $toWalletId, PDO::PARAM_INT);
            $updTo->execute();

            // Record internal_transfers row
            $ins = $pdo->prepare(
                'INSERT INTO internal_transfers
                    (from_user_id, to_user_id, currency_id, amount, note, status, created_at)
                 VALUES (:from_uid, :to_uid, :cid, :amt, :note, :status, NOW())'
            );
            $ins->bindValue(':from_uid', $fromUserId, PDO::PARAM_INT);
            $ins->bindValue(':to_uid',   $toUserId,   PDO::PARAM_INT);
            $ins->bindValue(':cid',      $currencyId, PDO::PARAM_INT);
            $ins->bindValue(':amt',      $amount);
            $ins->bindValue(':note',     $note ?: null);
            $ins->bindValue(':status',   'completed');
            $ins->execute();
            $transferId = (int)$pdo->lastInsertId();

            // Write debit ledger for sender
            $this->writeLedger($pdo, $fromWalletId, 'transfer', $transferId, 'debit',
                               $amount, $newFromBal, $note);
            // Write credit ledger for receiver
            $this->writeLedger($pdo, $toWalletId, 'transfer', $transferId, 'credit',
                               $amount, $newToBal, $note);

            $pdo->commit();
            return $transferId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    // -------------------------------------------------------------------------
    // Queries
    // -------------------------------------------------------------------------

    public function transfersByUser(int $userId, int $limit = 100): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT it.id, it.from_user_id, it.to_user_id, it.amount, it.note,
                    it.status, it.created_at,
                    c.code AS currency_code,
                    uf.username AS from_username,
                    ut.username AS to_username
             FROM internal_transfers it
             INNER JOIN currencies c  ON c.id  = it.currency_id
             INNER JOIN users uf      ON uf.id = it.from_user_id
             INNER JOIN users ut      ON ut.id = it.to_user_id
             WHERE it.from_user_id = :uid OR it.to_user_id = :uid2
             ORDER BY it.id DESC
             LIMIT :lim'
        );
        $stmt->bindValue(':uid',  $userId, PDO::PARAM_INT);
        $stmt->bindValue(':uid2', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':lim',  max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function allTransfers(array $filters = [], int $limit = 200): array
    {
        $sql = 'SELECT it.id, it.from_user_id, it.to_user_id, it.amount, it.note,
                       it.status, it.created_at,
                       c.code AS currency_code,
                       uf.username AS from_username,
                       ut.username AS to_username
                FROM internal_transfers it
                INNER JOIN currencies c  ON c.id  = it.currency_id
                INNER JOIN users uf      ON uf.id = it.from_user_id
                INNER JOIN users ut      ON ut.id = it.to_user_id
                WHERE 1=1';
        $params = [];

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $sql .= ' AND (uf.username LIKE :s OR ut.username LIKE :s2 OR c.code LIKE :s3)';
            $params['s']  = '%' . $search . '%';
            $params['s2'] = '%' . $search . '%';
            $params['s3'] = '%' . $search . '%';
        }

        $status = trim((string)($filters['status'] ?? ''));
        if ($status !== '') {
            $sql .= ' AND it.status = :status';
            $params['status'] = $status;
        }

        $sql .= ' ORDER BY it.id DESC LIMIT ' . max(1, $limit);

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    public function findTransferById(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT it.*, c.code AS currency_code,
                    uf.username AS from_username, uf.email AS from_email,
                    ut.username AS to_username,   ut.email AS to_email
             FROM internal_transfers it
             INNER JOIN currencies c  ON c.id  = it.currency_id
             INNER JOIN users uf      ON uf.id = it.from_user_id
             INNER JOIN users ut      ON ut.id = it.to_user_id
             WHERE it.id = :id LIMIT 1'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function writeLedger(
        \PDO   $pdo,
        int    $walletId,
        string $refType,
        int    $refId,
        string $direction,
        string $amount,
        string $balanceAfter,
        string $notes
    ): void {
        $stmt = $pdo->prepare(
            'INSERT INTO ledger_entries
                (wallet_id, reference_type, reference_id, direction, amount, balance_after, notes, created_at)
             VALUES (:wid, :ref_type, :ref_id, :dir, :amt, :bal, :notes, NOW())'
        );
        $stmt->bindValue(':wid',      $walletId, PDO::PARAM_INT);
        $stmt->bindValue(':ref_type', $refType);
        $stmt->bindValue(':ref_id',   $refId,    PDO::PARAM_INT);
        $stmt->bindValue(':dir',      $direction);
        $stmt->bindValue(':amt',      $amount);
        $stmt->bindValue(':bal',      $balanceAfter);
        $stmt->bindValue(':notes',    $notes ?: null);
        $stmt->execute();
    }
}
