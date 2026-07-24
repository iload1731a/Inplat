<?php
declare(strict_types=1);
namespace App\Repositories;

use App\Libraries\Database;
use PDO;

/**
 * Withdrawal Repository — dedicated repository for the complete withdrawal
 * management system (user + admin).  Handles fee calculation, daily-limit
 * enforcement, bulk operations, reporting, and notification helpers.
 */
final class WithdrawalRepository
{
    // -------------------------------------------------------------------------
    // Currency / fee helpers
    // -------------------------------------------------------------------------

    /** Return withdrawal-capable currencies with fee config. */
    public function withdrawalCurrencies(): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, code, name, type, decimals,
                    withdrawal_fee_fixed, withdrawal_fee_percent,
                    min_withdrawal, max_withdrawal_daily,
                    is_withdrawal_enabled, network, icon_url
             FROM currencies
             WHERE is_active = 1 AND is_withdrawal_enabled = 1
             ORDER BY type DESC, code ASC'
        );
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /** Return a single currency row for fee / limit calculations. */
    public function currencyById(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, code, name, type, decimals,
                    withdrawal_fee_fixed, withdrawal_fee_percent,
                    min_withdrawal, max_withdrawal_daily,
                    is_withdrawal_enabled, network
             FROM currencies
             WHERE id = :id LIMIT 1'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** Sum of completed+pending+processing withdrawals for a user in a given day. */
    public function dailyWithdrawalTotal(int $userId, int $currencyId, string $date): string
    {
        $stmt = Database::connection()->prepare(
            "SELECT COALESCE(SUM(amount + fee), '0')
             FROM withdrawals
             WHERE user_id = :uid
               AND currency_id = :cid
               AND status NOT IN ('rejected','cancelled')
               AND DATE(requested_at) = :dt"
        );
        $stmt->bindValue(':uid', $userId,     PDO::PARAM_INT);
        $stmt->bindValue(':cid', $currencyId, PDO::PARAM_INT);
        $stmt->bindValue(':dt',  $date);
        $stmt->execute();
        return (string)($stmt->fetchColumn() ?: '0');
    }

    // -------------------------------------------------------------------------
    // Creating / finding withdrawals
    // -------------------------------------------------------------------------

    public function create(array $data): int
    {
        $pdo  = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO withdrawals
               (user_id, wallet_id, currency_id, amount, fee,
                destination_address, destination_tag,
                requires_manual_review, status, requested_at)
             VALUES
               (:uid, :wid, :cid, :amount, :fee,
                :dest_addr, :dest_tag,
                :manual, :status, NOW())'
        );
        $stmt->bindValue(':uid',       $data['user_id'],              PDO::PARAM_INT);
        $stmt->bindValue(':wid',       $data['wallet_id'],            PDO::PARAM_INT);
        $stmt->bindValue(':cid',       $data['currency_id'],          PDO::PARAM_INT);
        $stmt->bindValue(':amount',    $data['amount']);
        $stmt->bindValue(':fee',       $data['fee']        ?? '0');
        $stmt->bindValue(':dest_addr', $data['destination_address']   ?? null);
        $stmt->bindValue(':dest_tag',  $data['destination_tag']       ?? null);
        $stmt->bindValue(':manual',    (int)($data['requires_manual_review'] ?? 0), PDO::PARAM_INT);
        $stmt->bindValue(':status',    $data['status']     ?? 'pending');
        $stmt->execute();
        return (int)$pdo->lastInsertId();
    }

    public function findById(int $id, ?int $userId = null): ?array
    {
        $sql = "SELECT w.*, u.username, u.email,
                       c.code AS currency_code, c.name AS currency_name,
                       c.type AS currency_type, c.network,
                       c.withdrawal_fee_fixed, c.withdrawal_fee_percent,
                       au.username AS reviewed_by_username
                FROM withdrawals w
                INNER JOIN users u         ON u.id  = w.user_id
                INNER JOIN currencies c    ON c.id  = w.currency_id
                LEFT  JOIN admin_users au  ON au.id = w.reviewed_by
                WHERE w.id = :id";
        $params = [':id' => $id];
        if ($userId !== null) {
            $sql .= ' AND w.user_id = :uid';
            $params[':uid'] = $userId;
        }
        $sql .= ' LIMIT 1';
        $stmt = Database::connection()->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    // -------------------------------------------------------------------------
    // User-scoped listings
    // -------------------------------------------------------------------------

    public function userWithdrawals(int $userId, array $filters = [], int $limit = 200): array
    {
        $sql = "SELECT w.id, w.amount, w.fee, w.destination_address, w.destination_tag,
                       w.tx_hash, w.status, w.requires_manual_review,
                       w.rejection_reason, w.requested_at, w.processed_at,
                       c.code AS currency_code, c.name AS currency_name, c.type AS currency_type, c.network
                FROM withdrawals w
                INNER JOIN currencies c ON c.id = w.currency_id
                WHERE w.user_id = :uid";
        $params = [':uid' => $userId];

        $status = trim((string)($filters['status'] ?? ''));
        if ($status !== '') {
            $sql .= ' AND w.status = :status';
            $params[':status'] = $status;
        }
        $currency = (int)($filters['currency_id'] ?? 0);
        if ($currency > 0) {
            $sql .= ' AND w.currency_id = :cid';
            $params[':cid'] = $currency;
        }
        $type = trim((string)($filters['type'] ?? ''));
        if (in_array($type, ['crypto', 'fiat'], true)) {
            $sql .= ' AND c.type = :ctype';
            $params[':ctype'] = $type;
        }
        $dateFrom = trim((string)($filters['date_from'] ?? ''));
        if ($dateFrom !== '') {
            $sql .= ' AND DATE(w.requested_at) >= :dfrom';
            $params[':dfrom'] = $dateFrom;
        }
        $dateTo = trim((string)($filters['date_to'] ?? ''));
        if ($dateTo !== '') {
            $sql .= ' AND DATE(w.requested_at) <= :dto';
            $params[':dto'] = $dateTo;
        }
        $sql .= ' ORDER BY w.id DESC LIMIT ' . max(1, min($limit, 1000));
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    public function userWithdrawalStats(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT
               COUNT(*) AS total,
               SUM(CASE WHEN status = 'pending'    THEN 1 ELSE 0 END) AS pending,
               SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) AS processing,
               SUM(CASE WHEN status = 'completed'  THEN 1 ELSE 0 END) AS completed,
               SUM(CASE WHEN status = 'rejected'   THEN 1 ELSE 0 END) AS rejected,
               SUM(CASE WHEN status = 'cancelled'  THEN 1 ELSE 0 END) AS cancelled,
               COALESCE(SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END), '0') AS total_withdrawn,
               COALESCE(SUM(CASE WHEN status = 'completed' THEN fee    ELSE 0 END), '0') AS total_fees
             FROM withdrawals
             WHERE user_id = :uid"
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch() ?: [];
    }

    /** Monthly aggregated withdrawal totals for the user (last 12 months). */
    public function userMonthlyTotals(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT DATE_FORMAT(requested_at, '%Y-%m') AS ym,
                    COUNT(*) AS cnt,
                    COALESCE(SUM(amount), '0') AS total_amount
             FROM withdrawals
             WHERE user_id = :uid
               AND status NOT IN ('rejected','cancelled')
               AND requested_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
             GROUP BY ym
             ORDER BY ym ASC"
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    // -------------------------------------------------------------------------
    // Admin-scoped listings
    // -------------------------------------------------------------------------

    public function adminList(array $filters = [], int $limit = 250): array
    {
        $sql = "SELECT w.id, w.user_id, w.wallet_id, w.currency_id,
                       w.amount, w.fee, w.destination_address, w.destination_tag,
                       w.tx_hash, w.status, w.requires_manual_review,
                       w.rejection_reason, w.requested_at, w.processed_at,
                       u.username, u.email,
                       c.code AS currency_code, c.name AS currency_name,
                       c.type AS currency_type, c.network
                FROM withdrawals w
                INNER JOIN users u      ON u.id  = w.user_id
                INNER JOIN currencies c ON c.id  = w.currency_id
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

        $currency = trim((string)($filters['currency'] ?? ''));
        if ($currency !== '') {
            $sql .= ' AND c.code = :curr';
            $params['curr'] = strtoupper($currency);
        }

        $type = trim((string)($filters['type'] ?? ''));
        if (in_array($type, ['crypto', 'fiat'], true)) {
            $sql .= ' AND c.type = :ctype';
            $params['ctype'] = $type;
        }

        $manual = $filters['manual_review'] ?? '';
        if ($manual === '1') {
            $sql .= ' AND w.requires_manual_review = 1';
        }

        $dateFrom = trim((string)($filters['date_from'] ?? ''));
        if ($dateFrom !== '') {
            $sql .= ' AND DATE(w.requested_at) >= :dfrom';
            $params['dfrom'] = $dateFrom;
        }
        $dateTo = trim((string)($filters['date_to'] ?? ''));
        if ($dateTo !== '') {
            $sql .= ' AND DATE(w.requested_at) <= :dto';
            $params['dto'] = $dateTo;
        }

        $amtMin = trim((string)($filters['amount_min'] ?? ''));
        if ($amtMin !== '' && is_numeric($amtMin)) {
            $sql .= ' AND w.amount >= :amin';
            $params['amin'] = $amtMin;
        }
        $amtMax = trim((string)($filters['amount_max'] ?? ''));
        if ($amtMax !== '' && is_numeric($amtMax)) {
            $sql .= ' AND w.amount <= :amax';
            $params['amax'] = $amtMax;
        }

        $sql .= ' ORDER BY w.id DESC LIMIT ' . max(1, min($limit, 2000));

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    // -------------------------------------------------------------------------
    // Admin statistics
    // -------------------------------------------------------------------------

    public function adminStats(): array
    {
        $stmt = Database::connection()->query(
            "SELECT
               COUNT(*) AS total,
               SUM(CASE WHEN status = 'pending'    THEN 1 ELSE 0 END) AS pending,
               SUM(CASE WHEN status = 'approved'   THEN 1 ELSE 0 END) AS approved,
               SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) AS processing,
               SUM(CASE WHEN status = 'completed'  THEN 1 ELSE 0 END) AS completed,
               SUM(CASE WHEN status = 'rejected'   THEN 1 ELSE 0 END) AS rejected,
               SUM(CASE WHEN status = 'cancelled'  THEN 1 ELSE 0 END) AS cancelled,
               SUM(CASE WHEN requires_manual_review = 1 AND status = 'pending' THEN 1 ELSE 0 END) AS manual_pending,
               COALESCE(SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END), '0') AS total_completed_amount,
               COALESCE(SUM(CASE WHEN status = 'completed' THEN fee    ELSE 0 END), '0') AS total_fee_revenue,
               COALESCE(SUM(CASE WHEN status NOT IN ('rejected','cancelled') AND DATE(requested_at) = CURDATE() THEN amount ELSE 0 END), '0') AS today_amount
             FROM withdrawals"
        );
        return $stmt->fetch() ?: [];
    }

    /** Daily withdrawal volume for last 30 days. */
    public function dailyVolume(int $days = 30): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT DATE(requested_at) AS day,
                    COUNT(*) AS cnt,
                    COALESCE(SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END), '0') AS completed_amount,
                    COALESCE(SUM(CASE WHEN status NOT IN ('rejected','cancelled') THEN amount ELSE 0 END), '0') AS total_amount
             FROM withdrawals
             WHERE requested_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
             GROUP BY day
             ORDER BY day ASC"
        );
        $stmt->bindValue(':days', max(1, $days), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /** Volume per currency (top 10). */
    public function currencyBreakdown(): array
    {
        $stmt = Database::connection()->query(
            "SELECT c.code, c.type,
                    COUNT(w.id) AS cnt,
                    COALESCE(SUM(CASE WHEN w.status = 'completed' THEN w.amount ELSE 0 END), '0') AS completed_amount
             FROM withdrawals w
             INNER JOIN currencies c ON c.id = w.currency_id
             WHERE w.status NOT IN ('rejected','cancelled')
             GROUP BY c.id, c.code, c.type
             ORDER BY completed_amount DESC
             LIMIT 10"
        );
        return $stmt->fetchAll() ?: [];
    }

    /** Monthly aggregated totals. */
    public function monthlyReport(int $months = 12): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT DATE_FORMAT(requested_at, '%Y-%m') AS ym,
                    COUNT(*) AS total,
                    SUM(CASE WHEN status = 'completed'  THEN 1 ELSE 0 END) AS completed,
                    SUM(CASE WHEN status = 'rejected'   THEN 1 ELSE 0 END) AS rejected,
                    COALESCE(SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END), '0') AS completed_amount,
                    COALESCE(SUM(CASE WHEN status = 'completed' THEN fee    ELSE 0 END), '0') AS fee_revenue
             FROM withdrawals
             WHERE requested_at >= DATE_SUB(NOW(), INTERVAL :m MONTH)
             GROUP BY ym
             ORDER BY ym ASC"
        );
        $stmt->bindValue(':m', max(1, $months), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    // -------------------------------------------------------------------------
    // Status updates
    // -------------------------------------------------------------------------

    public function updateStatus(
        int     $id,
        string  $status,
        ?int    $reviewedBy,
        ?string $txHash,
        ?string $rejectionReason
    ): void {
        $processedAt = in_array($status, ['completed', 'rejected', 'cancelled'], true)
            ? date('Y-m-d H:i:s') : null;

        $stmt = Database::connection()->prepare(
            'UPDATE withdrawals
             SET status           = :status,
                 reviewed_by      = :rb,
                 tx_hash          = COALESCE(:txh, tx_hash),
                 rejection_reason = :rr,
                 processed_at     = COALESCE(:pa, processed_at)
             WHERE id = :id'
        );
        $stmt->bindValue(':status', $status);
        $stmt->bindValue(':rb',     $reviewedBy,     PDO::PARAM_INT);
        $stmt->bindValue(':txh',    $txHash);
        $stmt->bindValue(':rr',     $rejectionReason);
        $stmt->bindValue(':pa',     $processedAt);
        $stmt->bindValue(':id',     $id,             PDO::PARAM_INT);
        $stmt->execute();
    }

    public function cancelByUser(int $id, int $userId): bool
    {
        $stmt = Database::connection()->prepare(
            "UPDATE withdrawals
             SET status = 'cancelled', processed_at = NOW()
             WHERE id = :id AND user_id = :uid AND status = 'pending'"
        );
        $stmt->bindValue(':id',  $id,     PDO::PARAM_INT);
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    // -------------------------------------------------------------------------
    // Bulk operations (admin)
    // -------------------------------------------------------------------------

    /** Bulk-approve pending withdrawals: returns list of IDs actually updated. */
    public function bulkApprove(array $ids, int $adminId): array
    {
        if (empty($ids)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::connection()->prepare(
            "UPDATE withdrawals
             SET status = 'approved', reviewed_by = ?
             WHERE id IN ({$placeholders}) AND status = 'pending'"
        );
        $params = array_merge([$adminId], array_map('intval', $ids));
        $stmt->execute($params);

        // Return the IDs that were actually updated
        $sel = Database::connection()->prepare(
            "SELECT id FROM withdrawals WHERE id IN ({$placeholders}) AND status = 'approved'"
        );
        $sel->execute(array_map('intval', $ids));
        return array_column($sel->fetchAll() ?: [], 'id');
    }

    /** Bulk-reject pending withdrawals: returns list of IDs actually updated. */
    public function bulkReject(array $ids, int $adminId, string $reason): array
    {
        if (empty($ids)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sel = Database::connection()->prepare(
            "SELECT id, wallet_id, amount, user_id FROM withdrawals
             WHERE id IN ({$placeholders}) AND status = 'pending'"
        );
        $sel->execute(array_map('intval', $ids));
        $rows = $sel->fetchAll() ?: [];
        if (empty($rows)) {
            return [];
        }

        // Refund each and mark rejected
        $pdo = Database::connection();
        $upd = $pdo->prepare(
            "UPDATE withdrawals
             SET status = 'rejected', reviewed_by = ?, rejection_reason = ?, processed_at = NOW()
             WHERE id = ? AND status = 'pending'"
        );

        $updated = [];
        foreach ($rows as $row) {
            $upd->execute([$adminId, $reason, (int)$row['id']]);
            if ($upd->rowCount() > 0) {
                $updated[] = (int)$row['id'];
            }
        }
        return $updated;
    }

    /** Return wallet_id, amount, user_id for each pending withdrawal by ID. */
    public function pendingWithdrawalsByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::connection()->prepare(
            "SELECT id, wallet_id, amount, fee, user_id, currency_id, status
             FROM withdrawals WHERE id IN ({$placeholders}) AND status = 'pending'"
        );
        $stmt->execute(array_map('intval', $ids));
        return $stmt->fetchAll() ?: [];
    }

    // -------------------------------------------------------------------------
    // Notification helper
    // -------------------------------------------------------------------------

    public function createNotification(int $userId, string $type, string $title, string $message, string $actionUrl = ''): void
    {
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

    // -------------------------------------------------------------------------
    // CSV export data
    // -------------------------------------------------------------------------

    public function exportData(array $filters): array
    {
        // Reuse admin list with higher limit for export
        return $this->adminList($filters, 10000);
    }

    // -------------------------------------------------------------------------
    // Payment gateway / currency withdrawal settings (admin)
    // -------------------------------------------------------------------------

    public function allWithdrawalCurrencySettings(): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, code, name, type, is_withdrawal_enabled,
                    withdrawal_fee_fixed, withdrawal_fee_percent,
                    min_withdrawal, max_withdrawal_daily,
                    network, confirmations_required, decimals
             FROM currencies
             WHERE is_active = 1
             ORDER BY type DESC, code ASC'
        );
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function updateWithdrawalSettings(int $currencyId, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE currencies
             SET is_withdrawal_enabled  = :enabled,
                 withdrawal_fee_fixed   = :fee_fixed,
                 withdrawal_fee_percent = :fee_pct,
                 min_withdrawal         = :min_wd,
                 max_withdrawal_daily   = :max_daily,
                 updated_at             = NOW()
             WHERE id = :id'
        );
        $stmt->bindValue(':enabled',   (int)(bool)$data['is_withdrawal_enabled'],   PDO::PARAM_INT);
        $stmt->bindValue(':fee_fixed', $data['withdrawal_fee_fixed']  ?? '0');
        $stmt->bindValue(':fee_pct',   $data['withdrawal_fee_percent'] ?? '0');
        $stmt->bindValue(':min_wd',    $data['min_withdrawal']        ?? '0');
        $stmt->bindValue(':max_daily', !empty($data['max_withdrawal_daily']) ? $data['max_withdrawal_daily'] : null);
        $stmt->bindValue(':id',        $currencyId,                                 PDO::PARAM_INT);
        $stmt->execute();
    }
}
