<?php
declare(strict_types=1);
namespace App\Repositories;

use App\Libraries\Database;
use PDO;

final class UserReferralRepository
{
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

    public function referrals(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT r.id, r.status, r.created_at,
                    u.username AS referred_username, u.email AS referred_email,
                    u.created_at AS user_joined_at
             FROM referrals r
             INNER JOIN users u ON u.id = r.referred_id
             WHERE r.referrer_id = :uid
             ORDER BY r.id DESC'
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function commissions(int $userId, int $limit = 200): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT ac.id, ac.commission_amount, ac.status, ac.created_at,
                    c.code AS currency_code,
                    u.username AS from_user
             FROM affiliate_commissions ac
             INNER JOIN currencies c ON c.id = ac.currency_id
             INNER JOIN users u ON u.id = ac.referred_id
             WHERE ac.referrer_id = :uid
             ORDER BY ac.id DESC LIMIT :lim'
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function stats(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT
                (SELECT COUNT(*) FROM referrals WHERE referrer_id = :uid) AS total_referred,
                (SELECT COUNT(*) FROM referrals WHERE referrer_id = :uid2 AND status = 'qualified') AS qualified_referrals,
                (SELECT COALESCE(SUM(commission_amount), 0) FROM affiliate_commissions WHERE referrer_id = :uid3 AND status = 'paid') AS total_earned,
                (SELECT COALESCE(SUM(commission_amount), 0) FROM affiliate_commissions WHERE referrer_id = :uid4 AND status = 'pending') AS pending_earnings"
        );
        $stmt->bindValue(':uid',  $userId, PDO::PARAM_INT);
        $stmt->bindValue(':uid2', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':uid3', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':uid4', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ?: [];
    }

    public function earningsSeries(int $userId, int $days = 30): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT DATE(created_at) AS day, SUM(commission_amount) AS earned
             FROM affiliate_commissions
             WHERE referrer_id = :uid AND status = 'paid'
               AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
             GROUP BY DATE(created_at)
             ORDER BY day ASC"
        );
        $stmt->bindValue(':uid',  $userId, PDO::PARAM_INT);
        $stmt->bindValue(':days', $days,   PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }
}
