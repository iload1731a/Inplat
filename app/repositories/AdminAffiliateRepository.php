<?php
declare(strict_types=1);
namespace App\Repositories;

use App\Libraries\Database;
use PDO;

/**
 * AdminAffiliateRepository
 *
 * Database layer for admin-facing Affiliate Program management:
 * dashboard KPIs, affiliate list, commission management, payout processing,
 * tier configuration, program settings, and analytics/reporting.
 */
final class AdminAffiliateRepository
{
    // =========================================================================
    // DASHBOARD KPIs
    // =========================================================================

    public function kpis(): array
    {
        $stmt = Database::connection()->query(
            "SELECT
                (SELECT COUNT(*)  FROM referrals)                               AS total_referrals,
                (SELECT COUNT(DISTINCT referrer_id) FROM referrals)             AS total_affiliates,
                (SELECT COUNT(*)  FROM referrals WHERE status='qualified')      AS qualified_referrals,
                (SELECT COUNT(*)  FROM referrals WHERE status='active')         AS active_referrals,
                (SELECT COALESCE(SUM(amount), 0) FROM affiliate_commissions)    AS total_commission_generated,
                (SELECT COALESCE(SUM(amount), 0) FROM affiliate_commissions WHERE status='pending') AS pending_commission,
                (SELECT COALESCE(SUM(amount), 0) FROM affiliate_commissions WHERE status='paid')    AS paid_commission,
                (SELECT COUNT(*) FROM affiliate_payouts WHERE status='pending') AS pending_payouts,
                (SELECT COALESCE(SUM(amount), 0) FROM affiliate_payouts WHERE status='paid') AS total_paid_out,
                (SELECT COUNT(*) FROM affiliate_commissions WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) AS commissions_24h,
                (SELECT COALESCE(SUM(amount), 0) FROM affiliate_commissions WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS commission_30d,
                (SELECT COUNT(*) FROM referrals WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS new_referrals_30d"
        );
        return $stmt->fetch() ?: [];
    }

    // =========================================================================
    // DAILY COMMISSION VOLUME
    // =========================================================================

    public function dailyCommissions(int $days = 30): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT DATE(created_at) AS day,
                    COUNT(*) AS commission_count,
                    COALESCE(SUM(amount), 0) AS total_amount
             FROM affiliate_commissions
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
             GROUP BY DATE(created_at)
             ORDER BY day ASC"
        );
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    // =========================================================================
    // COMMISSION TYPE BREAKDOWN
    // =========================================================================

    public function commissionByType(): array
    {
        $stmt = Database::connection()->query(
            "SELECT commission_type,
                    COUNT(*) AS cnt,
                    COALESCE(SUM(amount), 0) AS total_amount
             FROM affiliate_commissions
             GROUP BY commission_type
             ORDER BY total_amount DESC"
        );
        return $stmt->fetchAll() ?: [];
    }

    // =========================================================================
    // TOP AFFILIATES
    // =========================================================================

    public function topAffiliates(int $limit = 20): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT u.id, u.username, u.email,
                    COUNT(DISTINCT r.id)        AS total_referrals,
                    COUNT(DISTINCT CASE WHEN r.status='qualified' THEN r.id END) AS qualified,
                    COALESCE(SUM(ac.amount), 0) AS total_earned
             FROM users u
             INNER JOIN referrals r ON r.referrer_id = u.id
             LEFT  JOIN affiliate_commissions ac ON ac.referrer_id = u.id AND ac.status = 'paid'
             GROUP BY u.id, u.username, u.email
             ORDER BY total_earned DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':lim', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    // =========================================================================
    // AFFILIATES LIST (PAGINATED)
    // =========================================================================

    public function affiliatesList(array $f, int $page, int $perPage): array
    {
        [$where, $params] = $this->affiliatesWhere($f);
        $offset = ($page - 1) * $perPage;
        $stmt = Database::connection()->prepare(
            "SELECT u.id, u.username, u.email, u.referral_code, u.created_at,
                    COUNT(DISTINCT r.id)         AS total_referrals,
                    COUNT(DISTINCT CASE WHEN r.status='qualified' THEN r.id END) AS qualified,
                    COALESCE(SUM(ac.amount), 0)  AS total_commission,
                    (SELECT COUNT(*) FROM affiliate_payouts ap WHERE ap.user_id = u.id AND ap.status='pending') AS pending_payouts
             FROM users u
             LEFT JOIN referrals r  ON r.referrer_id = u.id
             LEFT JOIN affiliate_commissions ac ON ac.referrer_id = u.id
             WHERE {$where}
             GROUP BY u.id, u.username, u.email, u.referral_code, u.created_at
             ORDER BY total_commission DESC
             LIMIT :lim OFFSET :off"
        );
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset,  PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function affiliatesCount(array $f): int
    {
        [$where, $params] = $this->affiliatesWhere($f);
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(DISTINCT u.id)
             FROM users u
             LEFT JOIN referrals r ON r.referrer_id = u.id
             WHERE {$where}"
        );
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    private function affiliatesWhere(array $f): array
    {
        $wheres = ['EXISTS (SELECT 1 FROM referrals WHERE referrer_id = u.id)'];
        $params = [];

        if (!empty($f['search'])) {
            $wheres[] = '(u.username LIKE :srch OR u.email LIKE :srch2 OR u.referral_code = :srch3)';
            $like = '%' . $f['search'] . '%';
            $params[':srch']  = $like;
            $params[':srch2'] = $like;
            $params[':srch3'] = $f['search'];
        }
        if (!empty($f['date_from'])) {
            $wheres[] = 'u.created_at >= :df';
            $params[':df'] = $f['date_from'] . ' 00:00:00';
        }
        if (!empty($f['date_to'])) {
            $wheres[] = 'u.created_at <= :dt';
            $params[':dt'] = $f['date_to'] . ' 23:59:59';
        }
        return [implode(' AND ', $wheres), $params];
    }

    // =========================================================================
    // AFFILIATE DETAIL
    // =========================================================================

    public function affiliateDetail(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT u.id, u.username, u.email, u.referral_code,
                    u.created_at AS joined_at,
                    COUNT(DISTINCT r.id) AS total_referrals,
                    COUNT(DISTINCT CASE WHEN r.status='qualified' THEN r.id END) AS qualified,
                    COALESCE(SUM(ac.amount), 0) AS total_commission,
                    COALESCE(SUM(CASE WHEN ac.status='pending' THEN ac.amount ELSE 0 END), 0) AS pending_commission,
                    COALESCE(SUM(CASE WHEN ac.status='paid'    THEN ac.amount ELSE 0 END), 0) AS paid_commission
             FROM users u
             LEFT JOIN referrals r ON r.referrer_id = u.id
             LEFT JOIN affiliate_commissions ac ON ac.referrer_id = u.id
             WHERE u.id = :uid
             GROUP BY u.id, u.username, u.email, u.referral_code, u.created_at"
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch() ?: [];
    }

    // =========================================================================
    // COMMISSIONS LIST (PAGINATED)
    // =========================================================================

    public function commissionsList(array $f, int $page, int $perPage): array
    {
        [$where, $params] = $this->commissionsWhere($f);
        $offset = ($page - 1) * $perPage;
        $stmt = Database::connection()->prepare(
            "SELECT ac.id, ac.amount, ac.commission_type, ac.commission_rate,
                    ac.level, ac.status, ac.paid_at, ac.created_at,
                    c.code AS currency_code,
                    ru.username AS referrer_username,
                    fu.username AS from_username
             FROM affiliate_commissions ac
             INNER JOIN currencies c ON c.id = ac.currency_id
             LEFT  JOIN users ru ON ru.id = ac.referrer_id
             LEFT  JOIN users fu ON fu.id = ac.referred_id
             WHERE {$where}
             ORDER BY ac.id DESC
             LIMIT :lim OFFSET :off"
        );
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset,  PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function commissionsCount(array $f): int
    {
        [$where, $params] = $this->commissionsWhere($f);
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM affiliate_commissions ac WHERE ' . $where
        );
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    private function commissionsWhere(array $f): array
    {
        $wheres = ['1=1'];
        $params = [];

        if (!empty($f['status'])) {
            $wheres[] = 'ac.status = :status';
            $params[':status'] = $f['status'];
        }
        if (!empty($f['type'])) {
            $wheres[] = 'ac.commission_type = :type';
            $params[':type'] = $f['type'];
        }
        if (!empty($f['referrer_id'])) {
            $wheres[] = 'ac.referrer_id = :rid';
            $params[':rid'] = (int)$f['referrer_id'];
        }
        if (!empty($f['currency'])) {
            $wheres[] = 'ac.currency_id = (SELECT id FROM currencies WHERE code = :cur LIMIT 1)';
            $params[':cur'] = $f['currency'];
        }
        if (!empty($f['date_from'])) {
            $wheres[] = 'ac.created_at >= :df';
            $params[':df'] = $f['date_from'] . ' 00:00:00';
        }
        if (!empty($f['date_to'])) {
            $wheres[] = 'ac.created_at <= :dt';
            $params[':dt'] = $f['date_to'] . ' 23:59:59';
        }
        return [implode(' AND ', $wheres), $params];
    }

    public function markCommissionsPaid(array $ids): int
    {
        $ids = array_values(array_filter(array_map('intval', $ids), fn(int $id) => $id > 0));
        if ($ids === []) {
            return 0;
        }
        $ph   = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::connection()->prepare(
            "UPDATE affiliate_commissions
             SET status = 'paid', paid_at = NOW()
             WHERE id IN ({$ph}) AND status = 'pending'"
        );
        $stmt->execute($ids);
        return $stmt->rowCount();
    }

    // =========================================================================
    // PAYOUTS LIST (PAGINATED)
    // =========================================================================

    public function payoutsList(array $f, int $page, int $perPage): array
    {
        [$where, $params] = $this->payoutsWhere($f);
        $offset = ($page - 1) * $perPage;
        $stmt = Database::connection()->prepare(
            "SELECT ap.id, ap.amount, ap.status, ap.wallet_address, ap.network,
                    ap.notes, ap.admin_notes, ap.created_at, ap.processed_at, ap.paid_at,
                    c.code AS currency_code,
                    u.username, u.email
             FROM affiliate_payouts ap
             INNER JOIN currencies c ON c.id = ap.currency_id
             INNER JOIN users u ON u.id = ap.user_id
             WHERE {$where}
             ORDER BY ap.id DESC
             LIMIT :lim OFFSET :off"
        );
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset,  PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function payoutsCount(array $f): int
    {
        [$where, $params] = $this->payoutsWhere($f);
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM affiliate_payouts ap WHERE ' . $where
        );
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    private function payoutsWhere(array $f): array
    {
        $wheres = ['1=1'];
        $params = [];

        if (!empty($f['status'])) {
            $wheres[] = 'ap.status = :status';
            $params[':status'] = $f['status'];
        }
        if (!empty($f['search'])) {
            $wheres[] = '(u.username LIKE :srch OR u.email LIKE :srch2)';
            $like = '%' . $f['search'] . '%';
            $params[':srch']  = $like;
            $params[':srch2'] = $like;
        }
        if (!empty($f['currency'])) {
            $wheres[] = 'ap.currency_id = (SELECT id FROM currencies WHERE code = :cur LIMIT 1)';
            $params[':cur'] = $f['currency'];
        }
        if (!empty($f['date_from'])) {
            $wheres[] = 'ap.created_at >= :df';
            $params[':df'] = $f['date_from'] . ' 00:00:00';
        }
        if (!empty($f['date_to'])) {
            $wheres[] = 'ap.created_at <= :dt';
            $params[':dt'] = $f['date_to'] . ' 23:59:59';
        }
        return [implode(' AND ', $wheres), $params];
    }

    public function processPayout(int $payoutId, string $status, int $adminId, string $adminNotes): bool
    {
        $stmt = Database::connection()->prepare(
            'UPDATE affiliate_payouts
             SET status = :status,
                 admin_notes = :notes,
                 processed_by = :adm,
                 processed_at = NOW(),
                 paid_at = CASE WHEN :status2 = "paid" THEN NOW() ELSE paid_at END
             WHERE id = :id AND status IN ("pending","approved")'
        );
        $stmt->bindValue(':status',  $status);
        $stmt->bindValue(':status2', $status);
        $stmt->bindValue(':notes',   $adminNotes);
        $stmt->bindValue(':adm',     $adminId,  PDO::PARAM_INT);
        $stmt->bindValue(':id',      $payoutId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    public function bulkPayouts(array $ids, string $status, int $adminId): int
    {
        $ids = array_values(array_filter(array_map('intval', $ids), fn(int $id) => $id > 0));
        if ($ids === []) {
            return 0;
        }
        $ph   = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::connection()->prepare(
            "UPDATE affiliate_payouts
             SET status = ?, processed_by = ?, processed_at = NOW()
             WHERE id IN ({$ph}) AND status IN ('pending','approved')"
        );
        $stmt->execute(array_merge([$status, $adminId], $ids));
        return $stmt->rowCount();
    }

    // =========================================================================
    // COMMISSION TIERS
    // =========================================================================

    public function getTiers(): array
    {
        $stmt = Database::connection()->query(
            'SELECT id, level, label, commission_rate, is_active, min_referred
             FROM referral_tiers ORDER BY level ASC'
        );
        return $stmt->fetchAll() ?: [];
    }

    public function upsertTier(int $level, float $rate, string $label, int $isActive, int $minReferred): bool
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO referral_tiers (level, commission_rate, label, is_active, min_referred)
             VALUES (:lvl, :rate, :lbl, :active, :min)
             ON DUPLICATE KEY UPDATE
                commission_rate = VALUES(commission_rate),
                label           = VALUES(label),
                is_active       = VALUES(is_active),
                min_referred    = VALUES(min_referred)'
        );
        $stmt->bindValue(':lvl',    $level,       PDO::PARAM_INT);
        $stmt->bindValue(':rate',   $rate);
        $stmt->bindValue(':lbl',    $label);
        $stmt->bindValue(':active', $isActive,    PDO::PARAM_INT);
        $stmt->bindValue(':min',    $minReferred, PDO::PARAM_INT);
        $stmt->execute();
        return true;
    }

    // =========================================================================
    // PROGRAM SETTINGS
    // =========================================================================

    public function getProgramSettings(): array
    {
        $stmt = Database::connection()->query(
            'SELECT setting_key, setting_value, description FROM affiliate_program_settings'
        );
        $rows = $stmt->fetchAll() ?: [];
        $out  = [];
        foreach ($rows as $row) {
            $out[(string)$row['setting_key']] = [
                'value'       => $row['setting_value'],
                'description' => $row['description'],
            ];
        }
        return $out;
    }

    public function saveProgramSettings(array $settings): bool
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO affiliate_program_settings (setting_key, setting_value)
             VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
        foreach ($settings as $key => $value) {
            $stmt->bindValue(':k', (string)$key);
            $stmt->bindValue(':v', (string)$value);
            $stmt->execute();
        }
        return true;
    }

    // =========================================================================
    // REPORTS / ANALYTICS
    // =========================================================================

    public function monthlyCommissions(int $months = 12): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month,
                    COUNT(*) AS commission_count,
                    COALESCE(SUM(amount), 0) AS total_amount
             FROM affiliate_commissions
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL :months MONTH)
             GROUP BY DATE_FORMAT(created_at, '%Y-%m')
             ORDER BY month ASC"
        );
        $stmt->bindValue(':months', $months, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function referralGrowth(int $days = 90): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT DATE(created_at) AS day, COUNT(*) AS new_referrals
             FROM referrals
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
             GROUP BY DATE(created_at)
             ORDER BY day ASC"
        );
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function commissionsByCurrency(): array
    {
        $stmt = Database::connection()->query(
            "SELECT c.code AS currency_code,
                    COUNT(*) AS cnt,
                    COALESCE(SUM(ac.amount), 0) AS total_amount,
                    COALESCE(SUM(CASE WHEN ac.status='paid' THEN ac.amount ELSE 0 END), 0) AS paid_amount
             FROM affiliate_commissions ac
             INNER JOIN currencies c ON c.id = ac.currency_id
             GROUP BY c.code
             ORDER BY total_amount DESC
             LIMIT 20"
        );
        return $stmt->fetchAll() ?: [];
    }

    // =========================================================================
    // CSV EXPORT
    // =========================================================================

    public function exportCommissions(array $f, int $limit = 5000): array
    {
        [$where, $params] = $this->commissionsWhere($f);
        $stmt = Database::connection()->prepare(
            "SELECT ac.id, ac.amount, ac.commission_type, ac.level, ac.status,
                    ac.paid_at, ac.created_at,
                    c.code AS currency_code,
                    ru.username AS referrer, fu.username AS from_user
             FROM affiliate_commissions ac
             INNER JOIN currencies c ON c.id = ac.currency_id
             LEFT  JOIN users ru ON ru.id = ac.referrer_id
             LEFT  JOIN users fu ON fu.id = ac.referred_id
             WHERE {$where}
             ORDER BY ac.id DESC
             LIMIT :lim"
        );
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function exportPayouts(array $f, int $limit = 5000): array
    {
        [$where, $params] = $this->payoutsWhere($f);
        $stmt = Database::connection()->prepare(
            "SELECT ap.id, ap.amount, ap.status, ap.wallet_address, ap.network,
                    ap.created_at, ap.paid_at, c.code AS currency_code,
                    u.username, u.email
             FROM affiliate_payouts ap
             INNER JOIN currencies c ON c.id = ap.currency_id
             INNER JOIN users u ON u.id = ap.user_id
             WHERE {$where}
             ORDER BY ap.id DESC
             LIMIT :lim"
        );
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }
}
