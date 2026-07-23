<?php
declare(strict_types=1);
namespace App\Repositories;

use App\Libraries\Database;
use PDO;

/**
 * ReferralRepository
 *
 * Database layer for the full Referral System, Affiliate Program
 * and Commission Engine — user-facing operations.
 */
final class ReferralRepository
{
    // =========================================================================
    // REFERRAL CODE & BASICS
    // =========================================================================

    public function referralCode(int $userId): string
    {
        $stmt = Database::connection()->prepare(
            'SELECT referral_code FROM users WHERE id = :uid LIMIT 1'
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $val = $stmt->fetchColumn();
        return is_string($val) ? $val : '';
    }

    public function generateCode(int $userId): string
    {
        $code = strtoupper(substr(bin2hex(random_bytes(6)), 0, 8));
        $stmt = Database::connection()->prepare(
            'UPDATE users SET referral_code = :code WHERE id = :uid'
        );
        $stmt->bindValue(':code', $code);
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $code;
    }

    // =========================================================================
    // STATS
    // =========================================================================

    public function stats(int $userId): array
    {
        $db   = Database::connection();
        $stmt = $db->prepare(
            "SELECT
                (SELECT COUNT(*) FROM referrals WHERE referrer_id = :uid1) AS total_referred,
                (SELECT COUNT(*) FROM referrals WHERE referrer_id = :uid2 AND status = 'qualified') AS qualified_referrals,
                (SELECT COUNT(*) FROM referrals WHERE referrer_id = :uid3 AND status = 'active') AS active_referrals,
                (SELECT COALESCE(SUM(amount), 0) FROM affiliate_commissions WHERE referrer_id = :uid4 AND status = 'paid') AS total_earned,
                (SELECT COALESCE(SUM(amount), 0) FROM affiliate_commissions WHERE referrer_id = :uid5 AND status = 'pending') AS pending_earnings,
                (SELECT COALESCE(SUM(amount), 0) FROM affiliate_commissions WHERE referrer_id = :uid6 AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS earned_30d,
                (SELECT COUNT(*) FROM affiliate_commissions WHERE referrer_id = :uid7 AND commission_type = 'trade') AS trade_commissions,
                (SELECT COUNT(*) FROM affiliate_payouts WHERE user_id = :uid8 AND status = 'pending') AS pending_payouts,
                (SELECT COALESCE(SUM(amount), 0) FROM affiliate_payouts WHERE user_id = :uid9 AND status = 'paid') AS total_withdrawn"
        );
        foreach ([1,2,3,4,5,6,7,8,9] as $i) {
            $stmt->bindValue(":uid{$i}", $userId, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetch() ?: [];
    }

    public function tierStats(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT r.level,
                    COUNT(r.id)                                   AS referral_count,
                    COALESCE(SUM(ac.amount), 0)                   AS total_earned
             FROM referrals r
             LEFT JOIN affiliate_commissions ac
                    ON ac.referral_id = r.id AND ac.status = 'paid'
             WHERE r.referrer_id = :uid
             GROUP BY r.level
             ORDER BY r.level ASC"
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function earningsByCurrency(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT c.code AS currency_code,
                    COALESCE(SUM(CASE WHEN ac.status='paid'    THEN ac.amount ELSE 0 END), 0) AS total_paid,
                    COALESCE(SUM(CASE WHEN ac.status='pending' THEN ac.amount ELSE 0 END), 0) AS total_pending
             FROM affiliate_commissions ac
             INNER JOIN currencies c ON c.id = ac.currency_id
             WHERE ac.referrer_id = :uid
             GROUP BY c.code
             ORDER BY total_paid DESC"
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    // =========================================================================
    // REFERRALS LIST (PAGINATED)
    // =========================================================================

    public function referrals(int $userId, int $page = 1, int $perPage = 20, string $status = ''): array
    {
        $wheres = ['r.referrer_id = :uid'];
        $params = [':uid' => $userId];

        if ($status !== '' && $status !== 'all') {
            $wheres[] = 'r.status = :status';
            $params[':status'] = $status;
        }

        $where  = implode(' AND ', $wheres);
        $offset = ($page - 1) * $perPage;

        $stmt = Database::connection()->prepare(
            "SELECT r.id, r.level, r.status, r.qualified_at, r.total_earned, r.created_at,
                    u.id AS referred_user_id, u.username AS referred_username,
                    u.email AS referred_email, u.created_at AS user_joined_at,
                    (SELECT COUNT(*) FROM affiliate_commissions
                      WHERE referral_id = r.id AND status = 'paid') AS paid_commissions
             FROM referrals r
             INNER JOIN users u ON u.id = r.referee_id
             WHERE {$where}
             ORDER BY r.id DESC
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

    public function referralsCount(int $userId, string $status = ''): int
    {
        $wheres = ['referrer_id = :uid'];
        $params = [':uid' => $userId];
        if ($status !== '' && $status !== 'all') {
            $wheres[] = 'status = :status';
            $params[':status'] = $status;
        }
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM referrals WHERE ' . implode(' AND ', $wheres)
        );
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    // =========================================================================
    // NETWORK TREE (multi-level)
    // =========================================================================

    /** Returns up to 3 levels of referral network below $userId */
    public function networkTree(int $userId): array
    {
        // Level 1
        $l1 = $this->networkLevel($userId);
        foreach ($l1 as &$r1) {
            $r1['children'] = $this->networkLevel((int)$r1['referred_user_id']);
            foreach ($r1['children'] as &$r2) {
                $r2['children'] = $this->networkLevel((int)$r2['referred_user_id']);
            }
            unset($r2);
        }
        unset($r1);
        return $l1;
    }

    private function networkLevel(int $referrerId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT r.id, r.level, r.status, r.total_earned, r.created_at,
                    u.id AS referred_user_id, u.username AS referred_username
             FROM referrals r
             INNER JOIN users u ON u.id = r.referee_id
             WHERE r.referrer_id = :uid
             ORDER BY r.id ASC
             LIMIT 50"
        );
        $stmt->bindValue(':uid', $referrerId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    // =========================================================================
    // COMMISSIONS (PAGINATED)
    // =========================================================================

    public function commissions(
        int    $userId,
        int    $page    = 1,
        int    $perPage = 25,
        string $status  = '',
        string $type    = ''
    ): array {
        $wheres = ['ac.referrer_id = :uid'];
        $params = [':uid' => $userId];

        if ($status !== '' && $status !== 'all') {
            $wheres[] = 'ac.status = :status';
            $params[':status'] = $status;
        }
        if ($type !== '' && $type !== 'all') {
            $wheres[] = 'ac.commission_type = :type';
            $params[':type'] = $type;
        }

        $offset = ($page - 1) * $perPage;
        $stmt   = Database::connection()->prepare(
            'SELECT ac.id, ac.amount, ac.commission_type, ac.commission_rate, ac.level,
                    ac.status, ac.paid_at, ac.created_at,
                    c.code AS currency_code,
                    u.username AS from_user,
                    t.id AS trade_id
             FROM affiliate_commissions ac
             INNER JOIN currencies c ON c.id = ac.currency_id
             LEFT  JOIN users u ON u.id = ac.referred_id
             LEFT  JOIN trades t ON t.id = ac.trade_id
             WHERE ' . implode(' AND ', $wheres) . '
             ORDER BY ac.id DESC
             LIMIT :lim OFFSET :off'
        );
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset,  PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function commissionsCount(int $userId, string $status = '', string $type = ''): int
    {
        $wheres = ['referrer_id = :uid'];
        $params = [':uid' => $userId];
        if ($status !== '' && $status !== 'all') {
            $wheres[] = 'status = :status';
            $params[':status'] = $status;
        }
        if ($type !== '' && $type !== 'all') {
            $wheres[] = 'commission_type = :type';
            $params[':type'] = $type;
        }
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM affiliate_commissions WHERE ' . implode(' AND ', $wheres)
        );
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    // =========================================================================
    // EARNINGS SERIES
    // =========================================================================

    public function earningsSeries(int $userId, int $days = 30): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT DATE(created_at) AS day, SUM(amount) AS earned
             FROM affiliate_commissions
             WHERE referrer_id = :uid
               AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
             GROUP BY DATE(created_at)
             ORDER BY day ASC"
        );
        $stmt->bindValue(':uid',  $userId, PDO::PARAM_INT);
        $stmt->bindValue(':days', $days,   PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    // =========================================================================
    // REWARDS
    // =========================================================================

    public function rewards(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT rr.id, rr.reward_type, rr.amount, rr.description, rr.status,
                    rr.credited_at, rr.created_at, c.code AS currency_code
             FROM referral_rewards rr
             INNER JOIN currencies c ON c.id = rr.currency_id
             WHERE rr.user_id = :uid
             ORDER BY rr.id DESC
             LIMIT 200'
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function rewardStats(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT
                COUNT(*)                                                    AS total_rewards,
                COALESCE(SUM(CASE WHEN status='credited' THEN amount ELSE 0 END), 0) AS total_credited,
                COALESCE(SUM(CASE WHEN status='pending'  THEN amount ELSE 0 END), 0) AS total_pending
             FROM referral_rewards
             WHERE user_id = :uid"
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch() ?: [];
    }

    // =========================================================================
    // PAYOUTS
    // =========================================================================

    public function payouts(int $userId, int $limit = 50): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT ap.id, ap.amount, ap.status, ap.wallet_address, ap.network,
                    ap.notes, ap.created_at, ap.processed_at, ap.paid_at,
                    c.code AS currency_code
             FROM affiliate_payouts ap
             INNER JOIN currencies c ON c.id = ap.currency_id
             WHERE ap.user_id = :uid
             ORDER BY ap.id DESC
             LIMIT :lim'
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function pendingPayoutBalance(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT c.code AS currency_code, c.id AS currency_id,
                    COALESCE(SUM(ac.amount), 0) AS available_balance
             FROM affiliate_commissions ac
             INNER JOIN currencies c ON c.id = ac.currency_id
             WHERE ac.referrer_id = :uid AND ac.status = 'pending'
             GROUP BY c.id, c.code
             ORDER BY available_balance DESC"
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function requestPayout(
        int    $userId,
        int    $currencyId,
        string $amount,
        string $walletAddress,
        string $network,
        string $notes
    ): int {
        $stmt = Database::connection()->prepare(
            'INSERT INTO affiliate_payouts
                (user_id, currency_id, amount, wallet_address, network, notes, status)
             VALUES (:uid, :cid, :amt, :addr, :net, :notes, "pending")'
        );
        $stmt->bindValue(':uid',   $userId,        PDO::PARAM_INT);
        $stmt->bindValue(':cid',   $currencyId,    PDO::PARAM_INT);
        $stmt->bindValue(':amt',   $amount);
        $stmt->bindValue(':addr',  $walletAddress);
        $stmt->bindValue(':net',   $network);
        $stmt->bindValue(':notes', $notes);
        $stmt->execute();
        return (int)Database::connection()->lastInsertId();
    }

    public function hasPendingPayout(int $userId): bool
    {
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) FROM affiliate_payouts WHERE user_id = :uid AND status = 'pending'"
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return (int)$stmt->fetchColumn() > 0;
    }

    // =========================================================================
    // PROGRAM SETTINGS
    // =========================================================================

    public function programSettings(): array
    {
        $stmt = Database::connection()->query(
            'SELECT setting_key, setting_value FROM affiliate_program_settings'
        );
        $rows = $stmt->fetchAll() ?: [];
        $out  = [];
        foreach ($rows as $row) {
            $out[(string)$row['setting_key']] = $row['setting_value'];
        }
        return $out;
    }

    public function tiers(): array
    {
        $stmt = Database::connection()->query(
            'SELECT id, level, label, commission_rate, is_active, min_referred
             FROM referral_tiers ORDER BY level ASC'
        );
        return $stmt->fetchAll() ?: [];
    }
}
