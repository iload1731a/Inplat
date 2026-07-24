<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Libraries\Database;
use PDO;

/**
 * Signals Repository
 *
 * DB layer for:
 *  - Signal providers CRUD
 *  - Trading signals CRUD + feed queries
 *  - Signal subscriptions
 *  - Signal performance snapshots
 *  - Price alerts CRUD + trigger processing
 *  - Alert history log
 *  - Automation rules CRUD + execution log
 *  - Admin dashboard aggregates
 */
final class SignalsRepository
{
    // =========================================================================
    // SIGNAL PROVIDERS
    // =========================================================================

    public function allProviders(bool $activeOnly = false): array
    {
        $where = $activeOnly ? 'WHERE sp.is_active = 1' : '';
        $stmt  = Database::connection()->prepare(
            "SELECT sp.*,
                    COALESCE(perf.win_rate, 0)        AS win_rate_30d,
                    COALESCE(perf.total_signals, 0)   AS signals_30d,
                    COALESCE(perf.avg_profit_pct, 0)  AS avg_profit_30d,
                    (SELECT COUNT(*) FROM signal_subscriptions ss
                     WHERE ss.provider_id = sp.id AND ss.is_active = 1) AS subscriber_count
             FROM signal_providers sp
             LEFT JOIN signal_performance perf
                    ON perf.provider_id = sp.id AND perf.period = '30d'
             $where
             ORDER BY sp.is_active DESC, sp.total_signals DESC"
        );
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function providerById(int $id): array|false
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM signal_providers WHERE id = :id'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch() ?: false;
    }

    public function providerBySlug(string $slug): array|false
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM signal_providers WHERE slug = :slug'
        );
        $stmt->bindValue(':slug', $slug);
        $stmt->execute();
        return $stmt->fetch() ?: false;
    }

    public function createProvider(array $data): int
    {
        $pdo  = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO signal_providers
                (name, slug, description, provider_type, logo_url, website_url,
                 is_active, is_public, subscription_price, subscription_currency, created_by)
             VALUES
                (:name, :slug, :desc, :type, :logo, :website,
                 :active, :public, :price, :currency, :by)'
        );
        $stmt->execute([
            ':name'     => $data['name'],
            ':slug'     => $data['slug'],
            ':desc'     => $data['description'] ?? null,
            ':type'     => $data['provider_type'] ?? 'internal',
            ':logo'     => $data['logo_url'] ?? null,
            ':website'  => $data['website_url'] ?? null,
            ':active'   => (int)($data['is_active'] ?? 1),
            ':public'   => (int)($data['is_public'] ?? 1),
            ':price'    => $data['subscription_price'] ?? 0,
            ':currency' => $data['subscription_currency'] ?? null,
            ':by'       => $data['created_by'] ?? null,
        ]);
        return (int)$pdo->lastInsertId();
    }

    public function updateProvider(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE signal_providers
             SET name=:name, slug=:slug, description=:desc, provider_type=:type,
                 logo_url=:logo, website_url=:website, is_active=:active,
                 is_public=:public, subscription_price=:price, subscription_currency=:currency
             WHERE id=:id'
        );
        $stmt->execute([
            ':name'     => $data['name'],
            ':slug'     => $data['slug'],
            ':desc'     => $data['description'] ?? null,
            ':type'     => $data['provider_type'] ?? 'internal',
            ':logo'     => $data['logo_url'] ?? null,
            ':website'  => $data['website_url'] ?? null,
            ':active'   => (int)($data['is_active'] ?? 1),
            ':public'   => (int)($data['is_public'] ?? 1),
            ':price'    => $data['subscription_price'] ?? 0,
            ':currency' => $data['subscription_currency'] ?? null,
            ':id'       => $id,
        ]);
    }

    public function toggleProvider(int $id): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE signal_providers SET is_active = 1 - is_active WHERE id = :id'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    // =========================================================================
    // TRADING SIGNALS
    // =========================================================================

    public function getSignalFeed(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['provider_id'])) {
            $where[]              = 'ts.provider_id = :provider_id';
            $params[':provider_id'] = (int)$filters['provider_id'];
        }
        if (!empty($filters['pair_id'])) {
            $where[]           = 'ts.trading_pair_id = :pair_id';
            $params[':pair_id'] = (int)$filters['pair_id'];
        }
        if (!empty($filters['signal_type'])) {
            $where[]             = 'ts.signal_type = :signal_type';
            $params[':signal_type'] = $filters['signal_type'];
        }
        if (!empty($filters['market_type'])) {
            $where[]              = 'ts.market_type = :market_type';
            $params[':market_type'] = $filters['market_type'];
        }
        if (!empty($filters['status'])) {
            $where[]        = 'ts.status = :status';
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['timeframe'])) {
            $where[]           = 'ts.timeframe = :timeframe';
            $params[':timeframe'] = $filters['timeframe'];
        }

        $whereStr = implode(' AND ', $where);
        $stmt = Database::connection()->prepare(
            "SELECT ts.*, sp.name AS provider_name, sp.slug AS provider_slug,
                    sp.win_rate AS provider_win_rate,
                    tp.symbol AS pair_symbol_live
             FROM trading_signals ts
             JOIN signal_providers sp ON sp.id = ts.provider_id
             LEFT JOIN trading_pairs tp ON tp.id = ts.trading_pair_id
             WHERE sp.is_active = 1 AND sp.is_public = 1 AND $whereStr
             ORDER BY ts.published_at DESC
             LIMIT :lim OFFSET :off"
        );
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':lim',  max(1, $limit),  PDO::PARAM_INT);
        $stmt->bindValue(':off',  max(0, $offset),  PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function countSignalFeed(array $filters = []): int
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['provider_id'])) {
            $where[]              = 'ts.provider_id = :provider_id';
            $params[':provider_id'] = (int)$filters['provider_id'];
        }
        if (!empty($filters['pair_id'])) {
            $where[]           = 'ts.trading_pair_id = :pair_id';
            $params[':pair_id'] = (int)$filters['pair_id'];
        }
        if (!empty($filters['signal_type'])) {
            $where[]             = 'ts.signal_type = :signal_type';
            $params[':signal_type'] = $filters['signal_type'];
        }
        if (!empty($filters['status'])) {
            $where[]        = 'ts.status = :status';
            $params[':status'] = $filters['status'];
        }

        $whereStr = implode(' AND ', $where);
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) FROM trading_signals ts
             JOIN signal_providers sp ON sp.id = ts.provider_id
             WHERE sp.is_active = 1 AND sp.is_public = 1 AND $whereStr"
        );
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    public function signalById(int $id): array|false
    {
        $stmt = Database::connection()->prepare(
            "SELECT ts.*, sp.name AS provider_name, sp.slug AS provider_slug,
                    tp.symbol AS pair_symbol_live
             FROM trading_signals ts
             JOIN signal_providers sp ON sp.id = ts.provider_id
             LEFT JOIN trading_pairs tp ON tp.id = ts.trading_pair_id
             WHERE ts.id = :id"
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch() ?: false;
    }

    public function createSignal(array $data): int
    {
        $pdo  = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO trading_signals
                (provider_id, trading_pair_id, pair_symbol, signal_type, market_type, timeframe,
                 entry_price, entry_price_high, entry_price_low,
                 take_profit_1, take_profit_2, take_profit_3, stop_loss,
                 leverage, risk_reward_ratio, confidence_score,
                 analysis_text, chart_url, tags, expires_at, published_at)
             VALUES
                (:provider_id, :pair_id, :symbol, :type, :market, :timeframe,
                 :entry, :entry_high, :entry_low,
                 :tp1, :tp2, :tp3, :sl,
                 :lev, :rr, :conf,
                 :analysis, :chart, :tags, :expires, NOW())'
        );
        $stmt->execute([
            ':provider_id' => $data['provider_id'],
            ':pair_id'     => $data['trading_pair_id'] ?? null,
            ':symbol'      => $data['pair_symbol'] ?? null,
            ':type'        => $data['signal_type'],
            ':market'      => $data['market_type'] ?? 'spot',
            ':timeframe'   => $data['timeframe'] ?? '1h',
            ':entry'       => $data['entry_price'] ?? null,
            ':entry_high'  => $data['entry_price_high'] ?? null,
            ':entry_low'   => $data['entry_price_low'] ?? null,
            ':tp1'         => $data['take_profit_1'] ?? null,
            ':tp2'         => $data['take_profit_2'] ?? null,
            ':tp3'         => $data['take_profit_3'] ?? null,
            ':sl'          => $data['stop_loss'] ?? null,
            ':lev'         => $data['leverage'] ?? null,
            ':rr'          => $data['risk_reward_ratio'] ?? null,
            ':conf'        => $data['confidence_score'] ?? null,
            ':analysis'    => $data['analysis_text'] ?? null,
            ':chart'       => $data['chart_url'] ?? null,
            ':tags'        => isset($data['tags']) ? json_encode($data['tags']) : null,
            ':expires'     => $data['expires_at'] ?? null,
        ]);
        return (int)$pdo->lastInsertId();
    }

    public function updateSignalStatus(int $id, string $status, ?float $profitPct = null): void
    {
        $pdo  = Database::connection();
        $stmt = $pdo->prepare(
            'UPDATE trading_signals
             SET status = :status, profit_pct = :profit, hit_at = IF(:status2 IN ("hit_tp","hit_sl"), NOW(), hit_at)
             WHERE id = :id'
        );
        $stmt->execute([
            ':status'  => $status,
            ':status2' => $status,
            ':profit'  => $profitPct,
            ':id'      => $id,
        ]);
    }

    public function incrementSignalViews(int $id): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE trading_signals SET views_count = views_count + 1 WHERE id = :id'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function upsertInteraction(int $userId, int $signalId, string $type): void
    {
        $pdo  = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT IGNORE INTO signal_interactions (user_id, signal_id, interaction_type)
             VALUES (:uid, :sid, :type)'
        );
        $stmt->execute([':uid' => $userId, ':sid' => $signalId, ':type' => $type]);

        if ($type === 'like') {
            $pdo->prepare(
                'UPDATE trading_signals SET likes_count = (
                     SELECT COUNT(*) FROM signal_interactions
                     WHERE signal_id = :sid AND interaction_type = "like"
                 ) WHERE id = :sid2'
            )->execute([':sid' => $signalId, ':sid2' => $signalId]);
        }
    }

    public function removeInteraction(int $userId, int $signalId, string $type): void
    {
        $pdo  = Database::connection();
        $stmt = $pdo->prepare(
            'DELETE FROM signal_interactions
             WHERE user_id = :uid AND signal_id = :sid AND interaction_type = :type'
        );
        $stmt->execute([':uid' => $userId, ':sid' => $signalId, ':type' => $type]);

        if ($type === 'like') {
            $pdo->prepare(
                'UPDATE trading_signals SET likes_count = (
                     SELECT COUNT(*) FROM signal_interactions
                     WHERE signal_id = :sid AND interaction_type = "like"
                 ) WHERE id = :sid2'
            )->execute([':sid' => $signalId, ':sid2' => $signalId]);
        }
    }

    public function getUserInteractions(int $userId, array $signalIds): array
    {
        if (empty($signalIds)) {
            return [];
        }
        $in   = implode(',', array_map('intval', $signalIds));
        $stmt = Database::connection()->prepare(
            "SELECT signal_id, interaction_type
             FROM signal_interactions
             WHERE user_id = :uid AND signal_id IN ($in)"
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll() ?: [];

        $map = [];
        foreach ($rows as $row) {
            $map[$row['signal_id']][$row['interaction_type']] = true;
        }
        return $map;
    }

    public function getBookmarkedSignals(int $userId, int $limit = 50): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT ts.*, sp.name AS provider_name, si.created_at AS bookmarked_at
             FROM signal_interactions si
             JOIN trading_signals ts ON ts.id = si.signal_id
             JOIN signal_providers sp ON sp.id = ts.provider_id
             WHERE si.user_id = :uid AND si.interaction_type = 'bookmark'
             ORDER BY si.created_at DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit,  PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function getAdminSignals(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['provider_id'])) {
            $where[]              = 'ts.provider_id = :provider_id';
            $params[':provider_id'] = (int)$filters['provider_id'];
        }
        if (!empty($filters['status'])) {
            $where[]        = 'ts.status = :status';
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['signal_type'])) {
            $where[]             = 'ts.signal_type = :signal_type';
            $params[':signal_type'] = $filters['signal_type'];
        }
        if (!empty($filters['pair_symbol'])) {
            $where[]             = 'ts.pair_symbol LIKE :symbol';
            $params[':symbol']   = '%' . $filters['pair_symbol'] . '%';
        }

        $whereStr = implode(' AND ', $where);
        $stmt = Database::connection()->prepare(
            "SELECT ts.*, sp.name AS provider_name
             FROM trading_signals ts
             JOIN signal_providers sp ON sp.id = ts.provider_id
             WHERE $whereStr
             ORDER BY ts.published_at DESC
             LIMIT :lim OFFSET :off"
        );
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':lim',  max(1, $limit),  PDO::PARAM_INT);
        $stmt->bindValue(':off',  max(0, $offset),  PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function deleteSignal(int $id): void
    {
        $stmt = Database::connection()->prepare(
            'DELETE FROM trading_signals WHERE id = :id'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    // =========================================================================
    // SIGNAL SUBSCRIPTIONS
    // =========================================================================

    public function userSubscriptions(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT ss.*, sp.name AS provider_name, sp.slug, sp.description,
                    sp.provider_type, sp.logo_url, sp.total_signals,
                    sp.win_rate, sp.is_active AS provider_active,
                    COALESCE(perf.win_rate, 0) AS win_rate_30d,
                    COALESCE(perf.avg_profit_pct, 0) AS avg_profit_30d
             FROM signal_subscriptions ss
             JOIN signal_providers sp ON sp.id = ss.provider_id
             LEFT JOIN signal_performance perf ON perf.provider_id = sp.id AND perf.period = '30d'
             WHERE ss.user_id = :uid
             ORDER BY ss.subscribed_at DESC"
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function isSubscribed(int $userId, int $providerId): bool
    {
        $stmt = Database::connection()->prepare(
            'SELECT id FROM signal_subscriptions
             WHERE user_id = :uid AND provider_id = :pid AND is_active = 1'
        );
        $stmt->execute([':uid' => $userId, ':pid' => $providerId]);
        return (bool)$stmt->fetchColumn();
    }

    public function subscribe(int $userId, int $providerId, array $opts = []): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO signal_subscriptions
                (user_id, provider_id, is_active, notify_email, notify_platform, expires_at)
             VALUES (:uid, :pid, 1, :email, :platform, :exp)
             ON DUPLICATE KEY UPDATE
                is_active = 1, notify_email = :email2, notify_platform = :platform2, expires_at = :exp2'
        );
        $stmt->execute([
            ':uid'       => $userId,
            ':pid'       => $providerId,
            ':email'     => (int)($opts['notify_email'] ?? 1),
            ':platform'  => (int)($opts['notify_platform'] ?? 1),
            ':exp'       => $opts['expires_at'] ?? null,
            ':email2'    => (int)($opts['notify_email'] ?? 1),
            ':platform2' => (int)($opts['notify_platform'] ?? 1),
            ':exp2'      => $opts['expires_at'] ?? null,
        ]);
    }

    public function unsubscribe(int $userId, int $providerId): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE signal_subscriptions SET is_active = 0
             WHERE user_id = :uid AND provider_id = :pid'
        );
        $stmt->execute([':uid' => $userId, ':pid' => $providerId]);
    }

    public function updateSubscriptionPrefs(int $userId, int $providerId, array $prefs): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE signal_subscriptions
             SET notify_email = :email, notify_platform = :platform
             WHERE user_id = :uid AND provider_id = :pid'
        );
        $stmt->execute([
            ':email'    => (int)($prefs['notify_email'] ?? 1),
            ':platform' => (int)($prefs['notify_platform'] ?? 1),
            ':uid'      => $userId,
            ':pid'      => $providerId,
        ]);
    }

    // =========================================================================
    // SIGNAL PERFORMANCE
    // =========================================================================

    public function getPerformance(int $providerId, string $period = '30d'): array|false
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM signal_performance WHERE provider_id = :pid AND period = :period'
        );
        $stmt->execute([':pid' => $providerId, ':period' => $period]);
        return $stmt->fetch() ?: false;
    }

    public function upsertPerformance(int $providerId, string $period, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO signal_performance
                (provider_id, period, total_signals, active_signals,
                 hit_tp_count, hit_sl_count, cancelled_count,
                 win_rate, avg_profit_pct, avg_loss_pct, avg_rr_ratio,
                 best_signal_id, worst_signal_id, total_return_pct, calculated_at)
             VALUES
                (:pid, :period, :total, :active,
                 :tp, :sl, :cancelled,
                 :wr, :avg_p, :avg_l, :avg_rr,
                 :best, :worst, :ret, NOW())
             ON DUPLICATE KEY UPDATE
                total_signals=:total2, active_signals=:active2,
                hit_tp_count=:tp2, hit_sl_count=:sl2, cancelled_count=:cancelled2,
                win_rate=:wr2, avg_profit_pct=:avg_p2, avg_loss_pct=:avg_l2,
                avg_rr_ratio=:avg_rr2, best_signal_id=:best2, worst_signal_id=:worst2,
                total_return_pct=:ret2, calculated_at=NOW()'
        );
        $stmt->execute([
            ':pid'         => $providerId,
            ':period'      => $period,
            ':total'       => $data['total_signals'] ?? 0,
            ':active'      => $data['active_signals'] ?? 0,
            ':tp'          => $data['hit_tp_count'] ?? 0,
            ':sl'          => $data['hit_sl_count'] ?? 0,
            ':cancelled'   => $data['cancelled_count'] ?? 0,
            ':wr'          => $data['win_rate'] ?? 0,
            ':avg_p'       => $data['avg_profit_pct'] ?? null,
            ':avg_l'       => $data['avg_loss_pct'] ?? null,
            ':avg_rr'      => $data['avg_rr_ratio'] ?? null,
            ':best'        => $data['best_signal_id'] ?? null,
            ':worst'       => $data['worst_signal_id'] ?? null,
            ':ret'         => $data['total_return_pct'] ?? null,
            ':total2'      => $data['total_signals'] ?? 0,
            ':active2'     => $data['active_signals'] ?? 0,
            ':tp2'         => $data['hit_tp_count'] ?? 0,
            ':sl2'         => $data['hit_sl_count'] ?? 0,
            ':cancelled2'  => $data['cancelled_count'] ?? 0,
            ':wr2'         => $data['win_rate'] ?? 0,
            ':avg_p2'      => $data['avg_profit_pct'] ?? null,
            ':avg_l2'      => $data['avg_loss_pct'] ?? null,
            ':avg_rr2'     => $data['avg_rr_ratio'] ?? null,
            ':best2'       => $data['best_signal_id'] ?? null,
            ':worst2'      => $data['worst_signal_id'] ?? null,
            ':ret2'        => $data['total_return_pct'] ?? null,
        ]);
    }

    /** Recalculate performance from raw signals (used by cron + service). */
    public function recalculatePerformance(int $providerId, string $period): array
    {
        $sinceMap = ['7d' => '7 DAY', '30d' => '30 DAY', '90d' => '90 DAY', 'all' => null];
        $since    = $sinceMap[$period] ?? '30 DAY';
        $dateCond = $since ? "AND published_at >= NOW() - INTERVAL $since" : '';

        $stmt = Database::connection()->prepare(
            "SELECT
                COUNT(*)                                                         AS total_signals,
                SUM(status = 'active')                                           AS active_signals,
                SUM(status = 'hit_tp')                                           AS hit_tp_count,
                SUM(status = 'hit_sl')                                           AS hit_sl_count,
                SUM(status = 'cancelled' OR status = 'expired')                 AS cancelled_count,
                ROUND(100 * SUM(status='hit_tp') / NULLIF(SUM(status IN ('hit_tp','hit_sl')),0),2) AS win_rate,
                AVG(CASE WHEN profit_pct > 0 THEN profit_pct END)               AS avg_profit_pct,
                AVG(CASE WHEN profit_pct < 0 THEN profit_pct END)               AS avg_loss_pct,
                AVG(risk_reward_ratio)                                           AS avg_rr_ratio,
                SUM(profit_pct)                                                  AS total_return_pct
             FROM trading_signals
             WHERE provider_id = :pid $dateCond"
        );
        $stmt->bindValue(':pid', $providerId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch() ?: [];

        $best = Database::connection()->prepare(
            "SELECT id FROM trading_signals
             WHERE provider_id = :pid AND profit_pct IS NOT NULL $dateCond
             ORDER BY profit_pct DESC LIMIT 1"
        );
        $best->bindValue(':pid', $providerId, PDO::PARAM_INT);
        $best->execute();
        $row['best_signal_id'] = $best->fetchColumn() ?: null;

        $worst = Database::connection()->prepare(
            "SELECT id FROM trading_signals
             WHERE provider_id = :pid AND profit_pct IS NOT NULL $dateCond
             ORDER BY profit_pct ASC LIMIT 1"
        );
        $worst->bindValue(':pid', $providerId, PDO::PARAM_INT);
        $worst->execute();
        $row['worst_signal_id'] = $worst->fetchColumn() ?: null;

        return $row;
    }

    // =========================================================================
    // PRICE ALERTS
    // =========================================================================

    public function userAlerts(int $userId, string $status = ''): array
    {
        $where = $status ? 'AND pa.status = :status' : "AND pa.status != 'deleted'";
        $stmt  = Database::connection()->prepare(
            "SELECT pa.*, tp.base_currency, tp.quote_currency
             FROM price_alerts pa
             JOIN trading_pairs tp ON tp.id = pa.trading_pair_id
             WHERE pa.user_id = :uid $where
             ORDER BY pa.created_at DESC"
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        if ($status) {
            $stmt->bindValue(':status', $status);
        }
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function alertById(int $id, int $userId): array|false
    {
        $stmt = Database::connection()->prepare(
            'SELECT pa.*, tp.symbol AS pair_symbol_live
             FROM price_alerts pa
             JOIN trading_pairs tp ON tp.id = pa.trading_pair_id
             WHERE pa.id = :id AND pa.user_id = :uid'
        );
        $stmt->execute([':id' => $id, ':uid' => $userId]);
        return $stmt->fetch() ?: false;
    }

    public function createAlert(array $data): int
    {
        $pdo  = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO price_alerts
                (user_id, trading_pair_id, pair_symbol, alert_type, threshold_value,
                 timeframe, note, notify_email, notify_platform, is_recurring)
             VALUES
                (:uid, :pair, :symbol, :type, :threshold,
                 :tf, :note, :email, :platform, :recurring)'
        );
        $stmt->execute([
            ':uid'       => $data['user_id'],
            ':pair'      => $data['trading_pair_id'],
            ':symbol'    => $data['pair_symbol'],
            ':type'      => $data['alert_type'],
            ':threshold' => $data['threshold_value'],
            ':tf'        => $data['timeframe'] ?? '1h',
            ':note'      => $data['note'] ?? null,
            ':email'     => (int)($data['notify_email'] ?? 1),
            ':platform'  => (int)($data['notify_platform'] ?? 1),
            ':recurring' => (int)($data['is_recurring'] ?? 0),
        ]);
        return (int)$pdo->lastInsertId();
    }

    public function updateAlert(int $id, int $userId, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE price_alerts
             SET alert_type=:type, threshold_value=:threshold, timeframe=:tf,
                 note=:note, notify_email=:email, notify_platform=:platform,
                 is_recurring=:recurring
             WHERE id=:id AND user_id=:uid AND status != "deleted"'
        );
        $stmt->execute([
            ':type'      => $data['alert_type'],
            ':threshold' => $data['threshold_value'],
            ':tf'        => $data['timeframe'] ?? '1h',
            ':note'      => $data['note'] ?? null,
            ':email'     => (int)($data['notify_email'] ?? 1),
            ':platform'  => (int)($data['notify_platform'] ?? 1),
            ':recurring' => (int)($data['is_recurring'] ?? 0),
            ':id'        => $id,
            ':uid'       => $userId,
        ]);
    }

    public function deleteAlert(int $id, int $userId): void
    {
        $stmt = Database::connection()->prepare(
            "UPDATE price_alerts SET status = 'deleted' WHERE id = :id AND user_id = :uid"
        );
        $stmt->execute([':id' => $id, ':uid' => $userId]);
    }

    public function pauseAlert(int $id, int $userId): void
    {
        $stmt = Database::connection()->prepare(
            "UPDATE price_alerts SET status = 'paused' WHERE id = :id AND user_id = :uid"
        );
        $stmt->execute([':id' => $id, ':uid' => $userId]);
    }

    public function resumeAlert(int $id, int $userId): void
    {
        $stmt = Database::connection()->prepare(
            "UPDATE price_alerts SET status = 'active' WHERE id = :id AND user_id = :uid"
        );
        $stmt->execute([':id' => $id, ':uid' => $userId]);
    }

    public function triggerAlert(int $id, float $triggeredValue): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE price_alerts
             SET trigger_count = trigger_count + 1,
                 last_triggered_at = NOW(),
                 status = IF(is_recurring = 1, "active", "triggered")
             WHERE id = :id'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function logAlertHistory(int $alertId, int $userId, int $pairId, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO alert_history
                (alert_id, user_id, trading_pair_id, alert_type,
                 threshold_value, triggered_value, notification_sent)
             VALUES
                (:alert, :uid, :pair, :type, :threshold, :triggered, :sent)'
        );
        $stmt->execute([
            ':alert'     => $alertId,
            ':uid'       => $userId,
            ':pair'      => $pairId,
            ':type'      => $data['alert_type'],
            ':threshold' => $data['threshold_value'],
            ':triggered' => $data['triggered_value'],
            ':sent'      => (int)($data['notification_sent'] ?? 0),
        ]);
    }

    public function alertHistory(int $userId, int $limit = 100): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT ah.*, pa.alert_type AS alert_label, pa.pair_symbol, pa.note
             FROM alert_history ah
             LEFT JOIN price_alerts pa ON pa.id = ah.alert_id
             WHERE ah.user_id = :uid
             ORDER BY ah.triggered_at DESC
             LIMIT :lim'
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit,  PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    // Admin: all active alerts across all users
    public function adminActiveAlerts(int $limit = 200): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT pa.*, u.username, u.email,
                    tp.symbol AS pair_symbol_live
             FROM price_alerts pa
             JOIN users u ON u.id = pa.user_id
             JOIN trading_pairs tp ON tp.id = pa.trading_pair_id
             WHERE pa.status = 'active'
             ORDER BY pa.created_at DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function adminAlertStats(): array
    {
        $stmt = Database::connection()->query(
            "SELECT
                COUNT(*)                                       AS total_alerts,
                SUM(status = 'active')                         AS active_alerts,
                SUM(status = 'triggered')                      AS triggered_alerts,
                SUM(status = 'paused')                         AS paused_alerts,
                SUM(trigger_count)                             AS total_triggers,
                COUNT(DISTINCT user_id)                        AS users_with_alerts,
                (SELECT COUNT(*) FROM alert_history
                 WHERE triggered_at >= NOW() - INTERVAL 24 HOUR) AS triggers_24h
             FROM price_alerts
             WHERE status != 'deleted'"
        );
        return $stmt->fetch() ?: [];
    }

    // =========================================================================
    // AUTOMATION RULES
    // =========================================================================

    public function userRules(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT ar.*,
                    tp1.symbol AS trigger_pair_symbol,
                    tp2.symbol AS action_pair_symbol
             FROM automation_rules ar
             LEFT JOIN trading_pairs tp1 ON tp1.id = ar.trigger_pair_id
             LEFT JOIN trading_pairs tp2 ON tp2.id = ar.action_pair_id
             WHERE ar.user_id = :uid
             ORDER BY ar.created_at DESC"
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function ruleById(int $id, int $userId): array|false
    {
        $stmt = Database::connection()->prepare(
            'SELECT ar.*, tp1.symbol AS trigger_pair_symbol, tp2.symbol AS action_pair_symbol
             FROM automation_rules ar
             LEFT JOIN trading_pairs tp1 ON tp1.id = ar.trigger_pair_id
             LEFT JOIN trading_pairs tp2 ON tp2.id = ar.action_pair_id
             WHERE ar.id = :id AND ar.user_id = :uid'
        );
        $stmt->execute([':id' => $id, ':uid' => $userId]);
        return $stmt->fetch() ?: false;
    }

    public function createRule(array $data): int
    {
        $pdo  = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO automation_rules
                (user_id, name, description, trigger_type, trigger_pair_id, trigger_value,
                 trigger_timeframe, action_type, action_pair_id, action_side,
                 action_quantity, action_quantity_type, action_price, action_params,
                 cooldown_minutes, max_executions)
             VALUES
                (:uid, :name, :desc, :trigger_type, :tpair, :tval,
                 :ttf, :action_type, :apair, :aside,
                 :aqty, :aqty_type, :aprice, :aparams,
                 :cooldown, :maxex)'
        );
        $stmt->execute([
            ':uid'         => $data['user_id'],
            ':name'        => $data['name'],
            ':desc'        => $data['description'] ?? null,
            ':trigger_type'=> $data['trigger_type'],
            ':tpair'       => $data['trigger_pair_id'] ?? null,
            ':tval'        => $data['trigger_value'],
            ':ttf'         => $data['trigger_timeframe'] ?? '1h',
            ':action_type' => $data['action_type'],
            ':apair'       => $data['action_pair_id'] ?? null,
            ':aside'       => $data['action_side'] ?? null,
            ':aqty'        => $data['action_quantity'] ?? null,
            ':aqty_type'   => $data['action_quantity_type'] ?? 'fixed',
            ':aprice'      => $data['action_price'] ?? null,
            ':aparams'     => isset($data['action_params']) ? json_encode($data['action_params']) : null,
            ':cooldown'    => $data['cooldown_minutes'] ?? 60,
            ':maxex'       => $data['max_executions'] ?? null,
        ]);
        return (int)$pdo->lastInsertId();
    }

    public function updateRule(int $id, int $userId, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE automation_rules
             SET name=:name, description=:desc, trigger_type=:trigger_type,
                 trigger_pair_id=:tpair, trigger_value=:tval, trigger_timeframe=:ttf,
                 action_type=:action_type, action_pair_id=:apair, action_side=:aside,
                 action_quantity=:aqty, action_quantity_type=:aqty_type, action_price=:aprice,
                 action_params=:aparams, cooldown_minutes=:cooldown, max_executions=:maxex
             WHERE id=:id AND user_id=:uid'
        );
        $stmt->execute([
            ':name'        => $data['name'],
            ':desc'        => $data['description'] ?? null,
            ':trigger_type'=> $data['trigger_type'],
            ':tpair'       => $data['trigger_pair_id'] ?? null,
            ':tval'        => $data['trigger_value'],
            ':ttf'         => $data['trigger_timeframe'] ?? '1h',
            ':action_type' => $data['action_type'],
            ':apair'       => $data['action_pair_id'] ?? null,
            ':aside'       => $data['action_side'] ?? null,
            ':aqty'        => $data['action_quantity'] ?? null,
            ':aqty_type'   => $data['action_quantity_type'] ?? 'fixed',
            ':aprice'      => $data['action_price'] ?? null,
            ':aparams'     => isset($data['action_params']) ? json_encode($data['action_params']) : null,
            ':cooldown'    => $data['cooldown_minutes'] ?? 60,
            ':maxex'       => $data['max_executions'] ?? null,
            ':id'          => $id,
            ':uid'         => $userId,
        ]);
    }

    public function deleteRule(int $id, int $userId): void
    {
        $stmt = Database::connection()->prepare(
            'DELETE FROM automation_rules WHERE id = :id AND user_id = :uid'
        );
        $stmt->execute([':id' => $id, ':uid' => $userId]);
    }

    public function toggleRule(int $id, int $userId): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE automation_rules SET is_active = 1 - is_active WHERE id = :id AND user_id = :uid'
        );
        $stmt->execute([':id' => $id, ':uid' => $userId]);
    }

    public function logRuleExecution(int $ruleId, int $userId, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO automation_rule_logs
                (rule_id, user_id, trigger_type, trigger_value, action_type, action_result, result_detail)
             VALUES
                (:rule, :uid, :trigger_type, :tval, :action, :result, :detail)'
        );
        $stmt->execute([
            ':rule'         => $ruleId,
            ':uid'          => $userId,
            ':trigger_type' => $data['trigger_type'],
            ':tval'         => $data['trigger_value'],
            ':action'       => $data['action_type'],
            ':result'       => $data['action_result'] ?? 'success',
            ':detail'       => $data['result_detail'] ?? null,
        ]);

        // update execution count and last_executed_at
        $stmt2 = Database::connection()->prepare(
            'UPDATE automation_rules
             SET execution_count = execution_count + 1, last_executed_at = NOW()
             WHERE id = :id'
        );
        $stmt2->bindValue(':id', $ruleId, PDO::PARAM_INT);
        $stmt2->execute();
    }

    public function ruleLogs(int $ruleId, int $userId, int $limit = 50): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM automation_rule_logs
             WHERE rule_id = :rule AND user_id = :uid
             ORDER BY executed_at DESC LIMIT :lim'
        );
        $stmt->bindValue(':rule', $ruleId, PDO::PARAM_INT);
        $stmt->bindValue(':uid',  $userId, PDO::PARAM_INT);
        $stmt->bindValue(':lim',  $limit,  PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    // =========================================================================
    // ADMIN DASHBOARD STATS
    // =========================================================================

    public function adminKpis(): array
    {
        $pdo = Database::connection();

        $signals = $pdo->query(
            "SELECT
                COUNT(*)                         AS total_signals,
                SUM(status = 'active')            AS active_signals,
                SUM(status = 'hit_tp')            AS hit_tp_total,
                SUM(status = 'hit_sl')            AS hit_sl_total,
                ROUND(100*SUM(status='hit_tp')/NULLIF(SUM(status IN ('hit_tp','hit_sl')),0),2) AS platform_win_rate,
                COUNT(DISTINCT provider_id)       AS provider_count,
                (SELECT COUNT(*) FROM trading_signals WHERE published_at >= NOW() - INTERVAL 24 HOUR) AS signals_24h,
                (SELECT COUNT(*) FROM trading_signals WHERE published_at >= NOW() - INTERVAL 7 DAY)  AS signals_7d
             FROM trading_signals"
        )->fetch() ?: [];

        $providers = $pdo->query(
            "SELECT COUNT(*) AS total, SUM(is_active) AS active FROM signal_providers"
        )->fetch() ?: [];

        $alerts = $pdo->query(
            "SELECT COUNT(*) AS total_alerts,
                    SUM(status='active') AS active_alerts,
                    (SELECT COUNT(*) FROM alert_history WHERE triggered_at >= NOW() - INTERVAL 24 HOUR) AS triggered_24h
             FROM price_alerts WHERE status != 'deleted'"
        )->fetch() ?: [];

        $automations = $pdo->query(
            "SELECT COUNT(*) AS total_rules, SUM(is_active) AS active_rules,
                    SUM(execution_count) AS total_executions
             FROM automation_rules"
        )->fetch() ?: [];

        $subs = $pdo->query(
            "SELECT COUNT(*) AS total_subs, COUNT(DISTINCT user_id) AS subscribed_users
             FROM signal_subscriptions WHERE is_active = 1"
        )->fetch() ?: [];

        return array_merge($signals, $providers, $alerts, $automations, $subs);
    }

    public function dailySignalVolume(int $days = 30): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT DATE(published_at) AS day,
                    COUNT(*) AS total,
                    SUM(status = 'hit_tp') AS won,
                    SUM(status = 'hit_sl') AS lost
             FROM trading_signals
             WHERE published_at >= NOW() - INTERVAL :days DAY
             GROUP BY DATE(published_at)
             ORDER BY day ASC"
        );
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function topProvidersByPerformance(int $limit = 10): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT sp.id, sp.name, sp.slug, sp.total_signals,
                    COALESCE(perf.win_rate, 0)       AS win_rate,
                    COALESCE(perf.avg_profit_pct, 0) AS avg_profit_pct,
                    COALESCE(perf.total_return_pct, 0) AS total_return_pct,
                    (SELECT COUNT(*) FROM signal_subscriptions ss
                     WHERE ss.provider_id = sp.id AND ss.is_active = 1) AS subscribers
             FROM signal_providers sp
             LEFT JOIN signal_performance perf ON perf.provider_id = sp.id AND perf.period = '30d'
             WHERE sp.is_active = 1
             ORDER BY win_rate DESC, subscribers DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function recentSignalActivity(int $limit = 20): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT ts.id, ts.pair_symbol, ts.signal_type, ts.status,
                    ts.profit_pct, ts.published_at, ts.hit_at,
                    sp.name AS provider_name
             FROM trading_signals ts
             JOIN signal_providers sp ON sp.id = ts.provider_id
             ORDER BY ts.updated_at DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    public function getActivePairs(): array
    {
        $stmt = Database::connection()->query(
            "SELECT id, symbol, base_currency, quote_currency
             FROM trading_pairs WHERE is_active = 1 ORDER BY symbol ASC"
        );
        return $stmt->fetchAll() ?: [];
    }

    public function insertNotification(int $userId, string $type, string $title, string $message, string $url = ''): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO notifications (user_id, type, title, message, action_url)
             VALUES (:uid, :type, :title, :msg, :url)'
        );
        $stmt->execute([
            ':uid'   => $userId,
            ':type'  => $type,
            ':title' => $title,
            ':msg'   => $message,
            ':url'   => $url,
        ]);
    }
}
