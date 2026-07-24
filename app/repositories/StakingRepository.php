<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Libraries\Database;
use PDO;

final class StakingRepository
{
    private function sanitizePositiveInt(int $value): int
    {
        return max(1, min(200, $value));
    }

    public function listActivePools(): array
    {
        return Database::connection()
            ->query("SELECT sp.id, c.code AS currency, sp.name, sp.apy_percent,
                            sp.lock_period_days, sp.min_stake_amount,
                            sp.max_pool_capacity, sp.total_staked,
                            sp.early_unstake_fee_percent, sp.is_active
                     FROM staking_pools sp
                     INNER JOIN currencies c ON c.id = sp.currency_id
                     WHERE sp.is_active = 1
                     ORDER BY sp.apy_percent DESC")
            ->fetchAll() ?: [];
    }

    public function getUserStakes(int $userId, int $limit = 20): array
    {
        $safeLimit = $this->sanitizePositiveInt($limit);
        $stmt = Database::connection()->prepare(
            "SELECT us.id, sp.name AS pool_name, c.code AS currency,
                    us.amount, us.status, us.rewards_earned,
                    us.started_at, us.unlock_at, us.ended_at
             FROM user_stakes us
             INNER JOIN staking_pools sp ON sp.id = us.staking_pool_id
             INNER JOIN currencies c ON c.id = sp.currency_id
             WHERE us.user_id = :user_id
             ORDER BY us.id DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $safeLimit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public function getUserStakingRewards(int $userId, int $limit = 20): array
    {
        $safeLimit = $this->sanitizePositiveInt($limit);
        $stmt = Database::connection()->prepare(
            "SELECT srp.id, sp.name AS pool_name, c.code AS currency,
                    srp.amount AS reward_amount, srp.paid_at
             FROM staking_reward_payouts srp
             INNER JOIN user_stakes us ON us.id = srp.user_stake_id
             INNER JOIN staking_pools sp ON sp.id = us.staking_pool_id
             INNER JOIN currencies c ON c.id = sp.currency_id
             WHERE us.user_id = :user_id
             ORDER BY srp.id DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $safeLimit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public function getUserStakingSummary(int $userId): array
    {
        $pdo = Database::connection();

        $activeStmt = $pdo->prepare("SELECT COUNT(*) FROM user_stakes WHERE user_id = :uid AND status = 'active'");
        $activeStmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $activeStmt->execute();

        $totalStakedStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM user_stakes WHERE user_id = :uid AND status = 'active'");
        $totalStakedStmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $totalStakedStmt->execute();

        $totalRewardsStmt = $pdo->prepare("SELECT COALESCE(SUM(rewards_earned), 0) FROM user_stakes WHERE user_id = :uid");
        $totalRewardsStmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $totalRewardsStmt->execute();

        $poolCountStmt = $pdo->query('SELECT COUNT(*) FROM staking_pools WHERE is_active = 1');

        return [
            'active_stakes' => (int)$activeStmt->fetchColumn(),
            'total_staked_value' => (float)$totalStakedStmt->fetchColumn(),
            'total_rewards_earned' => (float)$totalRewardsStmt->fetchColumn(),
            'available_pools' => (int)$poolCountStmt->fetchColumn(),
        ];
    }
}
