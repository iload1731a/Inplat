<?php
declare(strict_types=1);
namespace App\Repositories;

use App\Libraries\Database;
use PDO;

/**
 * Deposit Repository — dedicated repository for the complete deposit management
 * system (user + admin).  Handles deposit creation, querying, filtering,
 * status updates, gateway settings, reporting, and notifications.
 */
final class DepositRepository
{
    // -------------------------------------------------------------------------
    // Currency / deposit helpers
    // -------------------------------------------------------------------------

    /** Return deposit-capable currencies with config. */
    public function depositCurrencies(): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, code, name, type, decimals,
                    is_deposit_enabled, network, contract_address,
                    confirmations_required, icon_url
             FROM currencies
             WHERE is_active = 1 AND is_deposit_enabled = 1
             ORDER BY type DESC, code ASC'
        );
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /** All active currencies (for admin gateway management). */
    public function allCurrencies(): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, code, name, type, is_active, is_deposit_enabled,
                    network, confirmations_required, icon_url
             FROM currencies
             ORDER BY type DESC, code ASC'
        );
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /** Return a single currency row. */
    public function currencyById(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, code, name, type, decimals,
                    is_deposit_enabled, network, confirmations_required
             FROM currencies
             WHERE id = :id LIMIT 1'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    // -------------------------------------------------------------------------
    // Creating / finding deposits
    // -------------------------------------------------------------------------

    public function create(array $data): int
    {
        $pdo  = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO deposits
               (user_id, wallet_id, currency_id, amount, tx_hash, from_address,
                to_address, confirmations, status, created_at)
             VALUES
               (:uid, :wid, :cid, :amount, :tx_hash, :from_address,
                :to_address, 0, :status, NOW())'
        );
        $stmt->bindValue(':uid',          $data['user_id'],      PDO::PARAM_INT);
        $stmt->bindValue(':wid',          $data['wallet_id'],    PDO::PARAM_INT);
        $stmt->bindValue(':cid',          $data['currency_id'],  PDO::PARAM_INT);
        $stmt->bindValue(':amount',       $data['amount']);
        $stmt->bindValue(':tx_hash',      $data['tx_hash']       ?? null);
        $stmt->bindValue(':from_address', $data['from_address']  ?? null);
        $stmt->bindValue(':to_address',   $data['to_address']    ?? null);
        $stmt->bindValue(':status',       $data['status']        ?? 'pending');
        $stmt->execute();
        return (int)$pdo->lastInsertId();
    }

    public function findById(int $id, ?int $userId = null): ?array
    {
        $sql = 'SELECT d.*,
                       u.username, u.email,
                       c.code AS currency_code, c.name AS currency_name, c.type AS currency_type,
                       c.network, c.decimals,
                       a.username AS reviewed_by_name
                FROM deposits d
                INNER JOIN users u ON u.id = d.user_id
                INNER JOIN currencies c ON c.id = d.currency_id
                LEFT  JOIN admin_users a ON a.id = d.reviewed_by
                WHERE d.id = :id';
        if ($userId !== null) {
            $sql .= ' AND d.user_id = :uid';
        }
        $sql .= ' LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        if ($userId !== null) {
            $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        }
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    // -------------------------------------------------------------------------
    // User deposit list
    // -------------------------------------------------------------------------

    public function userDeposits(int $userId, array $filters = [], int $limit = 100): array
    {
        $where  = ['d.user_id = :uid'];
        $params = [':uid' => $userId];

        if (!empty($filters['status'])) {
            $where[]           = 'd.status = :status';
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['currency_id'])) {
            $where[]              = 'd.currency_id = :cid';
            $params[':cid']       = (int)$filters['currency_id'];
        }
        if (!empty($filters['date_from'])) {
            $where[]              = 'DATE(d.created_at) >= :df';
            $params[':df']        = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[]              = 'DATE(d.created_at) <= :dt';
            $params[':dt']        = $filters['date_to'];
        }

        $whereStr = implode(' AND ', $where);
        $stmt = Database::connection()->prepare(
            "SELECT d.id, d.amount, d.tx_hash, d.from_address, d.to_address,
                    d.confirmations, d.status, d.flagged_reason,
                    d.created_at, d.credited_at,
                    c.code AS currency_code, c.name AS currency_name, c.network
             FROM deposits d
             INNER JOIN currencies c ON c.id = d.currency_id
             WHERE {$whereStr}
             ORDER BY d.id DESC LIMIT :lim"
        );
        foreach ($params as $k => $v) {
            if (is_int($v)) {
                $stmt->bindValue($k, $v, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($k, $v);
            }
        }
        $stmt->bindValue(':lim', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /** User deposit statistics. */
    public function userStats(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT
                COUNT(*)                                             AS total,
                SUM(status = 'pending')                             AS pending,
                SUM(status = 'credited')                            AS credited,
                SUM(status = 'failed')                              AS failed,
                SUM(status = 'flagged')                             AS flagged,
                COALESCE(SUM(CASE WHEN status='credited' THEN amount ELSE 0 END), 0) AS total_credited_amount
             FROM deposits
             WHERE user_id = :uid"
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch() ?: [];
    }

    // -------------------------------------------------------------------------
    // Admin deposit list
    // -------------------------------------------------------------------------

    public function adminList(array $filters = [], int $limit = 200): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['search'])) {
            $where[]          = '(u.username LIKE :search OR u.email LIKE :search OR d.tx_hash LIKE :search OR d.from_address LIKE :search)';
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['status'])) {
            $where[]           = 'd.status = :status';
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['currency'])) {
            $where[]           = 'c.code = :currency';
            $params[':currency'] = strtoupper($filters['currency']);
        }
        if (!empty($filters['type'])) {
            $where[]           = 'c.type = :type';
            $params[':type']   = $filters['type'];
        }
        if (!empty($filters['date_from'])) {
            $where[]           = 'DATE(d.created_at) >= :df';
            $params[':df']     = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[]           = 'DATE(d.created_at) <= :dt';
            $params[':dt']     = $filters['date_to'];
        }
        if (!empty($filters['amount_min'])) {
            $where[]            = 'd.amount >= :amin';
            $params[':amin']    = $filters['amount_min'];
        }
        if (!empty($filters['amount_max'])) {
            $where[]            = 'd.amount <= :amax';
            $params[':amax']    = $filters['amount_max'];
        }

        $whereStr = implode(' AND ', $where);
        $stmt = Database::connection()->prepare(
            "SELECT d.id, d.user_id, d.wallet_id, d.amount, d.tx_hash,
                    d.from_address, d.to_address, d.confirmations, d.status,
                    d.flagged_reason, d.reviewed_by, d.created_at, d.credited_at,
                    u.username, u.email,
                    c.code AS currency_code, c.name AS currency_name, c.type AS currency_type
             FROM deposits d
             INNER JOIN users u ON u.id = d.user_id
             INNER JOIN currencies c ON c.id = d.currency_id
             WHERE {$whereStr}
             ORDER BY d.id DESC LIMIT :lim"
        );
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':lim', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /** Admin summary statistics. */
    public function adminStats(): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT
                COUNT(*)                                              AS total,
                SUM(d.status = 'pending')                             AS pending,
                SUM(d.status = 'confirmed')                           AS confirmed,
                SUM(d.status = 'credited')                            AS credited,
                SUM(d.status = 'failed')                              AS failed,
                SUM(d.status = 'flagged')                             AS flagged,
                COALESCE(SUM(CASE WHEN d.status='credited' THEN d.amount ELSE 0 END),0) AS total_credited_amount,
                COALESCE(SUM(CASE WHEN DATE(d.created_at)=CURDATE() THEN d.amount ELSE 0 END),0) AS today_amount,
                COALESCE(SUM(CASE WHEN DATE(d.created_at)=CURDATE() AND d.status='credited' THEN d.amount ELSE 0 END),0) AS today_credited
             FROM deposits d"
        );
        $stmt->execute();
        return $stmt->fetch() ?: [];
    }

    // -------------------------------------------------------------------------
    // Status update / credit
    // -------------------------------------------------------------------------

    public function updateStatus(
        int     $id,
        string  $status,
        ?int    $reviewedBy,
        ?string $flaggedReason
    ): void {
        $pdo  = Database::connection();
        $stmt = $pdo->prepare(
            'UPDATE deposits
                SET status         = :status,
                    flagged_reason = :flag_reason,
                    reviewed_by    = :reviewed_by
              WHERE id = :id'
        );
        $stmt->bindValue(':status',      $status);
        $stmt->bindValue(':flag_reason', $flaggedReason);
        $stmt->bindValue(':reviewed_by', $reviewedBy, $reviewedBy !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':id',          $id, PDO::PARAM_INT);
        $stmt->execute();

        // Set credited_at separately to avoid conditional SQL concatenation
        if ($status === 'credited') {
            $ts = $pdo->prepare('UPDATE deposits SET credited_at = NOW() WHERE id = :id AND credited_at IS NULL');
            $ts->bindValue(':id', $id, PDO::PARAM_INT);
            $ts->execute();
        }
    }

    /**
     * Atomically credit a wallet and mark the deposit as credited in a single
     * transaction, preventing balance/status desync if one step fails.
     */
    public function creditAndMarkDeposit(int $depositId, int $walletId, string $amount, int $adminId): void
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            // Lock and verify wallet
            $walletStmt = $pdo->prepare(
                'SELECT available_balance, total_deposited, is_frozen FROM wallets WHERE id = :id FOR UPDATE'
            );
            $walletStmt->bindValue(':id', $walletId, PDO::PARAM_INT);
            $walletStmt->execute();
            $wallet = $walletStmt->fetch();
            if ($wallet === false) {
                throw new \RuntimeException('Wallet not found');
            }
            if ((int)$wallet['is_frozen'] === 1) {
                throw new \RuntimeException('Wallet is frozen');
            }

            $newBalance  = bcadd((string)$wallet['available_balance'], $amount, 18);
            $newDeposited = bcadd((string)$wallet['total_deposited'],    $amount, 18);

            // Update wallet balance
            $updWallet = $pdo->prepare(
                'UPDATE wallets SET available_balance = :bal, total_deposited = :dep, updated_at = NOW() WHERE id = :id'
            );
            $updWallet->bindValue(':bal', $newBalance);
            $updWallet->bindValue(':dep', $newDeposited);
            $updWallet->bindValue(':id',  $walletId, PDO::PARAM_INT);
            $updWallet->execute();

            // Write ledger entry
            $ledger = $pdo->prepare(
                'INSERT INTO ledger_entries
                    (wallet_id, reference_type, reference_id, direction, amount, balance_after, notes, created_by_admin, created_at)
                 VALUES (:wid, :ref_type, :ref_id, :dir, :amt, :bal, :notes, :admin, NOW())'
            );
            $ledger->bindValue(':wid',      $walletId, PDO::PARAM_INT);
            $ledger->bindValue(':ref_type', 'deposit');
            $ledger->bindValue(':ref_id',   $depositId, PDO::PARAM_INT);
            $ledger->bindValue(':dir',      'credit');
            $ledger->bindValue(':amt',      $amount);
            $ledger->bindValue(':bal',      $newBalance);
            $ledger->bindValue(':notes',    "Admin credit for deposit #{$depositId}");
            $ledger->bindValue(':admin',    $adminId, PDO::PARAM_INT);
            $ledger->execute();

            // Mark deposit as credited
            $updDep = $pdo->prepare(
                "UPDATE deposits
                    SET status = 'credited', credited_at = NOW(), reviewed_by = :admin
                  WHERE id = :id AND status != 'credited'"
            );
            $updDep->bindValue(':admin', $adminId, PDO::PARAM_INT);
            $updDep->bindValue(':id',    $depositId, PDO::PARAM_INT);
            $updDep->execute();

            if ($updDep->rowCount() === 0) {
                // Already credited by another request — rollback wallet changes
                throw new \RuntimeException('Deposit is already credited.');
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Bulk status update for a list of IDs (non-credit statuses only). */
    public function bulkUpdateStatus(array $ids, string $status, int $adminId): int
    {
        if (empty($ids)) {
            return 0;
        }
        $pdo          = Database::connection();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $stmt = $pdo->prepare(
            "UPDATE deposits
                SET status = ?, reviewed_by = ?
              WHERE id IN ({$placeholders}) AND status NOT IN ('credited','failed')"
        );
        $bindings = array_merge([$status, $adminId], $ids);
        $stmt->execute($bindings);

        return (int)$stmt->rowCount();
    }

    // -------------------------------------------------------------------------
    // Reporting
    // -------------------------------------------------------------------------

    /** Monthly deposit totals for a specific user (credited only). */
    public function userMonthlyReport(int $userId, int $months = 12): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month,
                    SUM(CASE WHEN status = 'credited' THEN amount ELSE 0 END) AS total,
                    SUM(status = 'credited') AS count
             FROM deposits
             WHERE user_id = :uid
               AND created_at >= DATE_SUB(CURDATE(), INTERVAL :months MONTH)
             GROUP BY DATE_FORMAT(created_at, '%Y-%m')
             ORDER BY month ASC"
        );
        $stmt->bindValue(':uid',    $userId, PDO::PARAM_INT);
        $stmt->bindValue(':months', $months, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /** Daily deposit volume for the last N days. */
    public function dailyVolumeReport(int $days = 30): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT DATE(created_at) AS day,
                    COUNT(*)          AS total_count,
                    SUM(amount)       AS total_amount,
                    SUM(status='credited') AS credited_count
             FROM deposits
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
             GROUP BY DATE(created_at)
             ORDER BY day ASC"
        );
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /** Deposit volume breakdown by currency. */
    public function currencyBreakdown(): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT c.code, c.name, c.type,
                    COUNT(d.id)   AS deposit_count,
                    SUM(d.amount) AS total_amount,
                    SUM(d.status='credited') AS credited_count
             FROM deposits d
             INNER JOIN currencies c ON c.id = d.currency_id
             GROUP BY d.currency_id
             ORDER BY total_amount DESC
             LIMIT 20"
        );
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /** Monthly deposit report for the past N months. */
    public function monthlyReport(int $months = 12): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month,
                    COUNT(*)    AS total_count,
                    SUM(amount) AS total_amount,
                    SUM(status='credited') AS credited_count
             FROM deposits
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL :months MONTH)
             GROUP BY DATE_FORMAT(created_at, '%Y-%m')
             ORDER BY month ASC"
        );
        $stmt->bindValue(':months', $months, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /** Top depositors. */
    public function topDepositors(int $limit = 10): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT u.id, u.username, u.email,
                    COUNT(d.id)   AS deposit_count,
                    SUM(d.amount) AS total_amount
             FROM deposits d
             INNER JOIN users u ON u.id = d.user_id
             WHERE d.status = 'credited'
             GROUP BY d.user_id
             ORDER BY total_amount DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':lim', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /** Data for CSV export (high limit). */
    public function exportData(array $filters): array
    {
        return $this->adminList($filters, 50000);
    }

    // -------------------------------------------------------------------------
    // Deposit gateway / currency settings (admin)
    // -------------------------------------------------------------------------

    public function allDepositCurrencySettings(): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, code, name, type, is_active, is_deposit_enabled,
                    network, contract_address, confirmations_required, icon_url,
                    decimals
             FROM currencies
             ORDER BY type DESC, code ASC'
        );
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function updateDepositCurrencySetting(int $currencyId, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE currencies
                SET is_deposit_enabled      = :enabled,
                    network                 = :network,
                    confirmations_required  = :confirms,
                    updated_at              = NOW()
              WHERE id = :id'
        );
        $stmt->bindValue(':enabled',  (int)($data['is_deposit_enabled'] ?? 1), PDO::PARAM_INT);
        $stmt->bindValue(':network',  $data['network']                ?? null);
        $stmt->bindValue(':confirms', (int)($data['confirmations_required'] ?? 1), PDO::PARAM_INT);
        $stmt->bindValue(':id',       $currencyId, PDO::PARAM_INT);
        $stmt->execute();
    }

    // -------------------------------------------------------------------------
    // Wallet deposit addresses
    // -------------------------------------------------------------------------

    public function walletAddresses(int $userId, int $currencyId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT w.id, w.wallet_type, w.available_balance
             FROM wallets w
             WHERE w.user_id = :uid AND w.currency_id = :cid'
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':cid', $currencyId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    // -------------------------------------------------------------------------
    // Notifications
    // -------------------------------------------------------------------------

    public function createNotification(
        int    $userId,
        string $type,
        string $title,
        string $message,
        string $actionUrl = ''
    ): void {
        $stmt = Database::connection()->prepare(
            'INSERT INTO notifications (user_id, type, title, message, is_read, action_url, created_at)
             VALUES (:uid, :type, :title, :message, 0, :url, NOW())'
        );
        $stmt->bindValue(':uid',     $userId, PDO::PARAM_INT);
        $stmt->bindValue(':type',    $type);
        $stmt->bindValue(':title',   $title);
        $stmt->bindValue(':message', $message);
        $stmt->bindValue(':url',     $actionUrl);
        $stmt->execute();
    }
}
