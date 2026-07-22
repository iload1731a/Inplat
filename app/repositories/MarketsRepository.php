<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Libraries\Database;
use PDO;

/**
 * Markets Repository
 *
 * Complete DB layer for:
 *  - Trading pairs with live ticker data
 *  - Price data providers CRUD
 *  - Provider asset mappings
 *  - Price feed subscriptions
 *  - Price sync logs
 *  - Pair import jobs & staging
 *  - User watchlists
 *  - Market statistics
 */
final class MarketsRepository
{
    // =========================================================================
    // MARKET OVERVIEW — TRADING PAIRS + TICKERS
    // =========================================================================

    /**
     * Full list of trading pairs joined with current ticker data.
     * Supports filtering by market_type, search, status.
     */
    public function listPairsWithTickers(array $filters = [], int $limit = 200): array
    {
        $sql = "SELECT tp.id, tp.symbol, tp.market_type,
                       tp.is_active, tp.trading_enabled, tp.is_visible, tp.display_order,
                       tp.maker_fee_percent, tp.taker_fee_percent, tp.max_leverage,
                       tp.min_order_size, tp.max_order_size, tp.min_notional,
                       tp.price_precision, tp.quantity_precision,
                       bc.id AS base_id, bc.code AS base_code, bc.name AS base_name, bc.icon_url AS base_icon,
                       qc.id AS quote_id, qc.code AS quote_code, qc.name AS quote_name,
                       COALESCE(pt.last_price, 0)          AS last_price,
                       COALESCE(pt.best_bid, 0)             AS best_bid,
                       COALESCE(pt.best_ask, 0)             AS best_ask,
                       COALESCE(pt.change_24h_percent, 0)   AS change_24h_percent,
                       COALESCE(pt.high_24h, 0)             AS high_24h,
                       COALESCE(pt.low_24h, 0)              AS low_24h,
                       COALESCE(pt.volume_24h, 0)           AS volume_24h,
                       pt.updated_at                        AS ticker_updated_at
                FROM trading_pairs tp
                INNER JOIN currencies bc ON bc.id = tp.base_currency_id
                INNER JOIN currencies qc ON qc.id = tp.quote_currency_id
                LEFT  JOIN price_tickers pt ON pt.trading_pair_id = tp.id
                WHERE 1=1";
        $params = [];

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $sql .= ' AND (tp.symbol LIKE :search OR bc.code LIKE :search OR qc.code LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        $marketType = trim((string)($filters['market_type'] ?? ''));
        if ($marketType !== '') {
            $sql .= ' AND tp.market_type = :market_type';
            $params['market_type'] = $marketType;
        }

        $isActive = $filters['is_active'] ?? '';
        if ($isActive !== '') {
            $sql .= ' AND tp.is_active = :is_active';
            $params['is_active'] = (int)$isActive;
        }

        $quoteCode = trim((string)($filters['quote'] ?? ''));
        if ($quoteCode !== '') {
            $sql .= ' AND qc.code = :quote';
            $params['quote'] = strtoupper($quoteCode);
        }

        $sortMap = [
            'volume'  => 'pt.volume_24h DESC',
            'change'  => 'ABS(pt.change_24h_percent) DESC',
            'symbol'  => 'tp.symbol ASC',
            'price'   => 'pt.last_price DESC',
        ];
        $sort = $sortMap[trim((string)($filters['sort'] ?? ''))] ?? 'tp.display_order ASC, tp.symbol ASC';
        $sql .= " ORDER BY {$sort} LIMIT :lim";
        $params['lim'] = $limit;

        $stmt = Database::connection()->prepare($sql);
        foreach ($params as $key => $val) {
            if ($key === 'lim') {
                $stmt->bindValue(':lim', $val, PDO::PARAM_INT);
            } elseif (in_array($key, ['is_active'], true)) {
                $stmt->bindValue(':' . $key, $val, PDO::PARAM_INT);
            } else {
                $stmt->bindValue(':' . $key, $val);
            }
        }
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function getPairDetail(int $pairId): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT tp.*, bc.code AS base_code, bc.name AS base_name, bc.icon_url AS base_icon,
                    qc.code AS quote_code, qc.name AS quote_name,
                    COALESCE(pt.last_price, 0)         AS last_price,
                    COALESCE(pt.best_bid, 0)            AS best_bid,
                    COALESCE(pt.best_ask, 0)            AS best_ask,
                    COALESCE(pt.change_24h_percent, 0)  AS change_24h_percent,
                    COALESCE(pt.high_24h, 0)            AS high_24h,
                    COALESCE(pt.low_24h, 0)             AS low_24h,
                    COALESCE(pt.volume_24h, 0)          AS volume_24h,
                    pt.updated_at                       AS ticker_updated_at
             FROM trading_pairs tp
             INNER JOIN currencies bc ON bc.id = tp.base_currency_id
             INNER JOIN currencies qc ON qc.id = tp.quote_currency_id
             LEFT  JOIN price_tickers pt ON pt.trading_pair_id = tp.id
             WHERE tp.id = :id LIMIT 1"
        );
        $stmt->bindValue(':id', $pairId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function getPairBySymbol(string $symbol): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT tp.*, bc.code AS base_code, qc.code AS quote_code,
                    COALESCE(pt.last_price, 0) AS last_price,
                    COALESCE(pt.change_24h_percent, 0) AS change_24h_percent,
                    COALESCE(pt.volume_24h, 0) AS volume_24h
             FROM trading_pairs tp
             INNER JOIN currencies bc ON bc.id = tp.base_currency_id
             INNER JOIN currencies qc ON qc.id = tp.quote_currency_id
             LEFT  JOIN price_tickers pt ON pt.trading_pair_id = tp.id
             WHERE tp.symbol = :sym AND tp.is_active = 1 LIMIT 1"
        );
        $stmt->bindValue(':sym', strtoupper($symbol));
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    // =========================================================================
    // MARKET STATISTICS
    // =========================================================================

    public function getMarketStats(): array
    {
        $pdo = Database::connection();

        $row = $pdo->query(
            "SELECT COUNT(*) AS total_pairs,
                    SUM(CASE WHEN tp.is_active = 1 THEN 1 ELSE 0 END) AS active_pairs,
                    SUM(CASE WHEN tp.trading_enabled = 1 AND tp.is_active = 1 THEN 1 ELSE 0 END) AS trading_pairs,
                    SUM(CASE WHEN tp.market_type = 'spot' THEN 1 ELSE 0 END) AS spot_pairs,
                    SUM(CASE WHEN tp.market_type = 'margin' THEN 1 ELSE 0 END) AS margin_pairs,
                    SUM(CASE WHEN tp.market_type = 'futures' THEN 1 ELSE 0 END) AS futures_pairs
             FROM trading_pairs tp"
        )->fetch() ?: [];

        $tickerStats = $pdo->query(
            "SELECT COALESCE(SUM(pt.volume_24h), 0)   AS total_volume_24h,
                    COUNT(*)                           AS pairs_with_tickers,
                    MAX(pt.updated_at)                 AS last_ticker_update
             FROM price_tickers pt"
        )->fetch() ?: [];

        $currencyStats = $pdo->query(
            "SELECT COUNT(*) AS total_currencies,
                    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active_currencies,
                    SUM(CASE WHEN type = 'crypto' THEN 1 ELSE 0 END) AS crypto_count,
                    SUM(CASE WHEN type = 'fiat' THEN 1 ELSE 0 END) AS fiat_count
             FROM currencies"
        )->fetch() ?: [];

        return array_merge($row, $tickerStats, $currencyStats);
    }

    public function getTopPairsByVolume(int $limit = 10): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT tp.symbol, tp.market_type, bc.code AS base_code, qc.code AS quote_code,
                    COALESCE(pt.last_price, 0)        AS last_price,
                    COALESCE(pt.change_24h_percent, 0) AS change_24h_percent,
                    COALESCE(pt.volume_24h, 0)         AS volume_24h,
                    COALESCE(pt.high_24h, 0)           AS high_24h,
                    COALESCE(pt.low_24h, 0)            AS low_24h
             FROM trading_pairs tp
             INNER JOIN currencies bc ON bc.id = tp.base_currency_id
             INNER JOIN currencies qc ON qc.id = tp.quote_currency_id
             INNER JOIN price_tickers pt ON pt.trading_pair_id = tp.id
             WHERE tp.is_active = 1
             ORDER BY pt.volume_24h DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function getTopGainers(int $limit = 5): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT tp.symbol, bc.code AS base_code,
                    COALESCE(pt.last_price, 0)        AS last_price,
                    COALESCE(pt.change_24h_percent, 0) AS change_24h_percent,
                    COALESCE(pt.volume_24h, 0)         AS volume_24h
             FROM trading_pairs tp
             INNER JOIN currencies bc ON bc.id = tp.base_currency_id
             INNER JOIN price_tickers pt ON pt.trading_pair_id = tp.id
             WHERE tp.is_active = 1 AND pt.change_24h_percent > 0
             ORDER BY pt.change_24h_percent DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function getTopLosers(int $limit = 5): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT tp.symbol, bc.code AS base_code,
                    COALESCE(pt.last_price, 0)        AS last_price,
                    COALESCE(pt.change_24h_percent, 0) AS change_24h_percent,
                    COALESCE(pt.volume_24h, 0)         AS volume_24h
             FROM trading_pairs tp
             INNER JOIN currencies bc ON bc.id = tp.base_currency_id
             INNER JOIN price_tickers pt ON pt.trading_pair_id = tp.id
             WHERE tp.is_active = 1 AND pt.change_24h_percent < 0
             ORDER BY pt.change_24h_percent ASC
             LIMIT :lim"
        );
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function getDailyVolumeChart(int $days = 30): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT DATE(t.created_at) AS day, COALESCE(SUM(t.price * t.quantity), 0) AS volume
             FROM trades t
             WHERE t.created_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
             GROUP BY DATE(t.created_at)
             ORDER BY day ASC"
        );
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function getMarketTypeVolumes(): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT tp.market_type, COALESCE(SUM(pt.volume_24h), 0) AS volume_24h
             FROM trading_pairs tp
             LEFT JOIN price_tickers pt ON pt.trading_pair_id = tp.id
             WHERE tp.is_active = 1
             GROUP BY tp.market_type"
        );
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    // =========================================================================
    // PRICE DATA PROVIDERS
    // =========================================================================

    public function listProviders(): array
    {
        $stmt = Database::connection()->query(
            "SELECT p.*,
                    (SELECT COUNT(*) FROM provider_asset_mappings pam WHERE pam.provider_id = p.id) AS mapped_assets,
                    (SELECT COUNT(*) FROM price_feed_subscriptions pfs WHERE pfs.primary_provider_id = p.id AND pfs.is_active = 1) AS active_subscriptions
             FROM price_data_providers p
             ORDER BY p.priority ASC, p.name ASC"
        );
        return $stmt->fetchAll() ?: [];
    }

    public function findProviderById(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM price_data_providers WHERE id = :id LIMIT 1'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function createProvider(array $data): int
    {
        $pdo  = Database::connection();
        $stmt = $pdo->prepare(
            "INSERT INTO price_data_providers
                (name, provider_code, provider_type, base_url, websocket_url,
                 auth_type, api_key_encrypted, api_secret_encrypted, auth_header_name,
                 rate_limit_per_minute, priority, supports_pair_import, supports_realtime_price,
                 default_sync_interval_seconds, is_active, notes, created_by)
             VALUES
                (:name, :provider_code, :provider_type, :base_url, :websocket_url,
                 :auth_type, :api_key, :api_secret, :auth_header_name,
                 :rate_limit, :priority, :supports_import, :supports_price,
                 :sync_interval, :is_active, :notes, :created_by)"
        );
        $stmt->execute($this->bindProviderData($data));
        return (int)$pdo->lastInsertId();
    }

    public function updateProvider(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            "UPDATE price_data_providers SET
                name = :name, provider_code = :provider_code, provider_type = :provider_type,
                base_url = :base_url, websocket_url = :websocket_url,
                auth_type = :auth_type, auth_header_name = :auth_header_name,
                rate_limit_per_minute = :rate_limit, priority = :priority,
                supports_pair_import = :supports_import, supports_realtime_price = :supports_price,
                default_sync_interval_seconds = :sync_interval, is_active = :is_active, notes = :notes,
                updated_at = NOW()
             WHERE id = :id"
        );
        $stmt->execute([
            ':name'           => trim((string)($data['name'] ?? '')),
            ':provider_code'  => strtolower(trim((string)($data['provider_code'] ?? ''))),
            ':provider_type'  => trim((string)($data['provider_type'] ?? 'rest_market_data')),
            ':base_url'       => trim((string)($data['base_url'] ?? '')) ?: null,
            ':websocket_url'  => trim((string)($data['websocket_url'] ?? '')) ?: null,
            ':auth_type'      => trim((string)($data['auth_type'] ?? 'api_key_header')),
            ':auth_header_name' => trim((string)($data['auth_header_name'] ?? '')) ?: null,
            ':rate_limit'     => (int)($data['rate_limit_per_minute'] ?? 60),
            ':priority'       => max(1, (int)($data['priority'] ?? 100)),
            ':supports_import'=> (int)(bool)($data['supports_pair_import'] ?? 1),
            ':supports_price' => (int)(bool)($data['supports_realtime_price'] ?? 1),
            ':sync_interval'  => max(1, (int)($data['default_sync_interval_seconds'] ?? 60)),
            ':is_active'      => (int)(bool)($data['is_active'] ?? 1),
            ':notes'          => trim((string)($data['notes'] ?? '')) ?: null,
            ':id'             => $id,
        ]);
    }

    public function toggleProvider(int $id, bool $active): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE price_data_providers SET is_active = :a, updated_at = NOW() WHERE id = :id'
        );
        $stmt->bindValue(':a', (int)$active, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    private function bindProviderData(array $data): array
    {
        return [
            ':name'           => trim((string)($data['name'] ?? '')),
            ':provider_code'  => strtolower(trim((string)($data['provider_code'] ?? ''))),
            ':provider_type'  => trim((string)($data['provider_type'] ?? 'rest_market_data')),
            ':base_url'       => trim((string)($data['base_url'] ?? '')) ?: null,
            ':websocket_url'  => trim((string)($data['websocket_url'] ?? '')) ?: null,
            ':auth_type'      => trim((string)($data['auth_type'] ?? 'none')),
            ':api_key'        => trim((string)($data['api_key'] ?? '')) ?: null,
            ':api_secret'     => trim((string)($data['api_secret'] ?? '')) ?: null,
            ':auth_header_name' => trim((string)($data['auth_header_name'] ?? '')) ?: null,
            ':rate_limit'     => (int)($data['rate_limit_per_minute'] ?? 60),
            ':priority'       => max(1, (int)($data['priority'] ?? 100)),
            ':supports_import'=> (int)(bool)($data['supports_pair_import'] ?? 1),
            ':supports_price' => (int)(bool)($data['supports_realtime_price'] ?? 1),
            ':sync_interval'  => max(1, (int)($data['default_sync_interval_seconds'] ?? 60)),
            ':is_active'      => (int)(bool)($data['is_active'] ?? 1),
            ':notes'          => trim((string)($data['notes'] ?? '')) ?: null,
            ':created_by'     => isset($data['created_by']) ? (int)$data['created_by'] : null,
        ];
    }

    // =========================================================================
    // PROVIDER ASSET MAPPINGS
    // =========================================================================

    public function listAssetMappings(int $providerId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT pam.*, c.code AS currency_code, c.name AS currency_name
             FROM provider_asset_mappings pam
             INNER JOIN currencies c ON c.id = pam.currency_id
             WHERE pam.provider_id = :pid
             ORDER BY c.code ASC"
        );
        $stmt->bindValue(':pid', $providerId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function upsertAssetMapping(int $providerId, int $currencyId, array $data): void
    {
        $stmt = Database::connection()->prepare(
            "INSERT INTO provider_asset_mappings
                (provider_id, currency_id, external_id, external_symbol, external_slug, is_active)
             VALUES (:pid, :cid, :ext_id, :ext_sym, :ext_slug, :active)
             ON DUPLICATE KEY UPDATE
                external_id = VALUES(external_id),
                external_symbol = VALUES(external_symbol),
                external_slug = VALUES(external_slug),
                is_active = VALUES(is_active)"
        );
        $stmt->execute([
            ':pid'      => $providerId,
            ':cid'      => $currencyId,
            ':ext_id'   => trim((string)($data['external_id'] ?? '')) ?: null,
            ':ext_sym'  => trim((string)($data['external_symbol'] ?? '')) ?: null,
            ':ext_slug' => trim((string)($data['external_slug'] ?? '')) ?: null,
            ':active'   => (int)(bool)($data['is_active'] ?? 1),
        ]);
    }

    public function deleteAssetMapping(int $id): void
    {
        $stmt = Database::connection()->prepare(
            'DELETE FROM provider_asset_mappings WHERE id = :id'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    // =========================================================================
    // PRICE FEED SUBSCRIPTIONS
    // =========================================================================

    public function listFeedSubscriptions(array $filters = []): array
    {
        $sql = "SELECT pfs.*, tp.symbol,
                       pp.name AS primary_provider_name, pp.provider_code AS primary_code,
                       fp.name AS fallback_provider_name
                FROM price_feed_subscriptions pfs
                INNER JOIN trading_pairs tp ON tp.id = pfs.trading_pair_id
                LEFT  JOIN price_data_providers pp ON pp.id = pfs.primary_provider_id
                LEFT  JOIN price_data_providers fp ON fp.id = pfs.fallback_provider_id
                WHERE 1=1";
        $params = [];

        $isActive = $filters['is_active'] ?? '';
        if ($isActive !== '') {
            $sql .= ' AND pfs.is_active = :is_active';
            $params[':is_active'] = (int)$isActive;
        }

        $providerId = (int)($filters['provider_id'] ?? 0);
        if ($providerId > 0) {
            $sql .= ' AND pfs.primary_provider_id = :pid';
            $params[':pid'] = $providerId;
        }

        $sql .= ' ORDER BY tp.symbol ASC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    public function findFeedSubscription(int $pairId): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT pfs.*, tp.symbol,
                    pp.name AS primary_provider_name,
                    fp.name AS fallback_provider_name
             FROM price_feed_subscriptions pfs
             INNER JOIN trading_pairs tp ON tp.id = pfs.trading_pair_id
             LEFT  JOIN price_data_providers pp ON pp.id = pfs.primary_provider_id
             LEFT  JOIN price_data_providers fp ON fp.id = pfs.fallback_provider_id
             WHERE pfs.trading_pair_id = :pid LIMIT 1"
        );
        $stmt->bindValue(':pid', $pairId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function upsertFeedSubscription(int $pairId, array $data): void
    {
        $stmt = Database::connection()->prepare(
            "INSERT INTO price_feed_subscriptions
                (trading_pair_id, primary_provider_id, fallback_provider_id, feed_mode,
                 poll_interval_seconds, max_allowed_staleness_seconds, is_active)
             VALUES (:pair_id, :primary_id, :fallback_id, :feed_mode, :poll_interval, :max_staleness, :active)
             ON DUPLICATE KEY UPDATE
                primary_provider_id = VALUES(primary_provider_id),
                fallback_provider_id = VALUES(fallback_provider_id),
                feed_mode = VALUES(feed_mode),
                poll_interval_seconds = VALUES(poll_interval_seconds),
                max_allowed_staleness_seconds = VALUES(max_allowed_staleness_seconds),
                is_active = VALUES(is_active),
                updated_at = NOW()"
        );
        $fallbackId = (int)($data['fallback_provider_id'] ?? 0);
        $stmt->execute([
            ':pair_id'      => $pairId,
            ':primary_id'   => (int)($data['primary_provider_id'] ?? 0),
            ':fallback_id'  => $fallbackId > 0 ? $fallbackId : null,
            ':feed_mode'    => in_array($data['feed_mode'] ?? '', ['websocket', 'polling'], true) ? $data['feed_mode'] : 'websocket',
            ':poll_interval'=> (int)($data['poll_interval_seconds'] ?? 30),
            ':max_staleness'=> max(5, (int)($data['max_allowed_staleness_seconds'] ?? 30)),
            ':active'       => (int)(bool)($data['is_active'] ?? 1),
        ]);
    }

    public function toggleFeedSubscription(int $pairId, bool $active): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE price_feed_subscriptions SET is_active = :a, updated_at = NOW() WHERE trading_pair_id = :pid'
        );
        $stmt->bindValue(':a', (int)$active, PDO::PARAM_INT);
        $stmt->bindValue(':pid', $pairId, PDO::PARAM_INT);
        $stmt->execute();
    }

    // =========================================================================
    // PRICE SYNC LOGS
    // =========================================================================

    public function listSyncLogs(array $filters = [], int $limit = 100): array
    {
        $sql = "SELECT psl.*, p.name AS provider_name, tp.symbol AS pair_symbol
                FROM price_sync_logs psl
                INNER JOIN price_data_providers p ON p.id = psl.provider_id
                LEFT  JOIN trading_pairs tp ON tp.id = psl.trading_pair_id
                WHERE 1=1";
        $params = [];

        $providerId = (int)($filters['provider_id'] ?? 0);
        if ($providerId > 0) {
            $sql .= ' AND psl.provider_id = :pid';
            $params[':pid'] = $providerId;
        }

        $eventType = trim((string)($filters['event_type'] ?? ''));
        if ($eventType !== '') {
            $sql .= ' AND psl.event_type = :event_type';
            $params[':event_type'] = $eventType;
        }

        $since = trim((string)($filters['since'] ?? ''));
        if ($since !== '') {
            $sql .= ' AND psl.created_at >= :since';
            $params[':since'] = $since;
        }

        $sql .= ' ORDER BY psl.created_at DESC LIMIT :lim';

        $stmt = Database::connection()->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function getSyncLogStats(): array
    {
        $row = Database::connection()->query(
            "SELECT
                COUNT(*) AS total_logs,
                SUM(CASE WHEN event_type = 'sync_success' THEN 1 ELSE 0 END) AS successes,
                SUM(CASE WHEN event_type = 'sync_failed' THEN 1 ELSE 0 END) AS failures,
                SUM(CASE WHEN event_type = 'rate_limited' THEN 1 ELSE 0 END) AS rate_limited,
                SUM(CASE WHEN event_type LIKE 'ws_%' THEN 1 ELSE 0 END) AS ws_events,
                AVG(CASE WHEN response_time_ms IS NOT NULL THEN response_time_ms END) AS avg_response_ms,
                MAX(created_at) AS last_event_at
             FROM price_sync_logs
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        )->fetch();
        return $row ?: [];
    }

    public function insertSyncLog(int $providerId, ?int $pairId, string $eventType, array $data = []): void
    {
        $allowed = ['sync_success','sync_failed','rate_limited','ws_connected','ws_disconnected','ws_error'];
        if (!in_array($eventType, $allowed, true)) {
            return;
        }
        $stmt = Database::connection()->prepare(
            "INSERT INTO price_sync_logs
                (provider_id, trading_pair_id, event_type, http_status, message, response_time_ms)
             VALUES (:pid, :pair_id, :event_type, :http_status, :message, :response_ms)"
        );
        $stmt->execute([
            ':pid'          => $providerId,
            ':pair_id'      => $pairId,
            ':event_type'   => $eventType,
            ':http_status'  => isset($data['http_status']) ? (int)$data['http_status'] : null,
            ':message'      => isset($data['message']) ? substr((string)$data['message'], 0, 500) : null,
            ':response_ms'  => isset($data['response_time_ms']) ? (int)$data['response_time_ms'] : null,
        ]);
    }

    // =========================================================================
    // USER WATCHLIST
    // =========================================================================

    public function getUserWatchlist(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT w.id AS watchlist_id, w.trading_pair_id, w.created_at AS added_at,
                    tp.symbol, tp.market_type, tp.is_active,
                    bc.code AS base_code, bc.icon_url AS base_icon,
                    qc.code AS quote_code,
                    COALESCE(pt.last_price, 0)         AS last_price,
                    COALESCE(pt.change_24h_percent, 0)  AS change_24h_percent,
                    COALESCE(pt.volume_24h, 0)          AS volume_24h,
                    COALESCE(pt.high_24h, 0)            AS high_24h,
                    COALESCE(pt.low_24h, 0)             AS low_24h
             FROM watchlists w
             INNER JOIN trading_pairs tp ON tp.id = w.trading_pair_id
             INNER JOIN currencies bc ON bc.id = tp.base_currency_id
             INNER JOIN currencies qc ON qc.id = tp.quote_currency_id
             LEFT  JOIN price_tickers pt ON pt.trading_pair_id = tp.id
             WHERE w.user_id = :uid
             ORDER BY pt.volume_24h DESC, tp.symbol ASC"
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function isInWatchlist(int $userId, int $pairId): bool
    {
        $stmt = Database::connection()->prepare(
            'SELECT 1 FROM watchlists WHERE user_id = :uid AND trading_pair_id = :pid LIMIT 1'
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':pid', $pairId, PDO::PARAM_INT);
        $stmt->execute();
        return (bool)$stmt->fetchColumn();
    }

    public function addToWatchlist(int $userId, int $pairId): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT IGNORE INTO watchlists (user_id, trading_pair_id) VALUES (:uid, :pid)'
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':pid', $pairId, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function removeFromWatchlist(int $userId, int $pairId): void
    {
        $stmt = Database::connection()->prepare(
            'DELETE FROM watchlists WHERE user_id = :uid AND trading_pair_id = :pid'
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':pid', $pairId, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function getWatchlistPairIds(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT trading_pair_id FROM watchlists WHERE user_id = :uid'
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return array_column($stmt->fetchAll() ?: [], 'trading_pair_id');
    }

    // =========================================================================
    // MARKET ACTIVITY (recent trades for a pair)
    // =========================================================================

    public function getRecentTradesForPair(int $pairId, int $limit = 20): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT t.price, t.quantity, t.side, t.created_at
             FROM trades t
             WHERE t.trading_pair_id = :pid
             ORDER BY t.created_at DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':pid', $pairId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function getCandlesticksForPair(int $pairId, string $interval = '1h', int $limit = 50): array
    {
        $allowedIntervals = ['1m', '5m', '15m', '1h', '4h', '1d'];
        if (!in_array($interval, $allowedIntervals, true)) {
            $interval = '1h';
        }
        // $intervalMap values are static SQL expressions defined entirely in code.
        // $interval is validated against $allowedIntervals before being used as a key,
        // so $bucketExpr is always one of the fixed expressions below — no user input reaches the query.
        $intervalMap = [
            '1m'  => 'FLOOR(UNIX_TIMESTAMP(created_at)/60)*60',
            '5m'  => 'FLOOR(UNIX_TIMESTAMP(created_at)/300)*300',
            '15m' => 'FLOOR(UNIX_TIMESTAMP(created_at)/900)*900',
            '1h'  => 'FLOOR(UNIX_TIMESTAMP(created_at)/3600)*3600',
            '4h'  => 'FLOOR(UNIX_TIMESTAMP(created_at)/14400)*14400',
            '1d'  => 'DATE(created_at)',
        ];
        $bucketExpr = $intervalMap[$interval];

        $stmt = Database::connection()->prepare(
            "SELECT {$bucketExpr} AS bucket,
                    SUBSTRING_INDEX(GROUP_CONCAT(price ORDER BY created_at ASC), ',', 1) AS open_price,
                    MAX(price) AS high_price,
                    MIN(price) AS low_price,
                    SUBSTRING_INDEX(GROUP_CONCAT(price ORDER BY created_at DESC), ',', 1) AS close_price,
                    SUM(quantity) AS volume
             FROM trades
             WHERE trading_pair_id = :pid
             GROUP BY bucket
             ORDER BY bucket DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':pid', $pairId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_reverse($stmt->fetchAll() ?: []);
    }

    // =========================================================================
    // PRICE TICKER UPDATE (used by price feed workers)
    // =========================================================================

    public function upsertTicker(int $pairId, array $data): void
    {
        $stmt = Database::connection()->prepare(
            "INSERT INTO price_tickers
                (trading_pair_id, last_price, best_bid, best_ask, change_24h_percent, high_24h, low_24h, volume_24h)
             VALUES (:pid, :last, :bid, :ask, :change_pct, :high, :low, :vol)
             ON DUPLICATE KEY UPDATE
                last_price = VALUES(last_price),
                best_bid = VALUES(best_bid),
                best_ask = VALUES(best_ask),
                change_24h_percent = VALUES(change_24h_percent),
                high_24h = VALUES(high_24h),
                low_24h = VALUES(low_24h),
                volume_24h = VALUES(volume_24h),
                updated_at = NOW()"
        );
        $stmt->execute([
            ':pid'        => $pairId,
            ':last'       => (string)($data['last_price'] ?? '0'),
            ':bid'        => isset($data['best_bid']) ? (string)$data['best_bid'] : null,
            ':ask'        => isset($data['best_ask']) ? (string)$data['best_ask'] : null,
            ':change_pct' => (string)($data['change_24h_percent'] ?? '0'),
            ':high'       => isset($data['high_24h']) ? (string)$data['high_24h'] : null,
            ':low'        => isset($data['low_24h']) ? (string)$data['low_24h'] : null,
            ':vol'        => (string)($data['volume_24h'] ?? '0'),
        ]);
    }

    // =========================================================================
    // QUOTE CURRENCIES (for market filter tabs)
    // =========================================================================

    public function getDistinctQuoteCurrencies(): array
    {
        $stmt = Database::connection()->query(
            "SELECT DISTINCT qc.code, qc.name
             FROM trading_pairs tp
             INNER JOIN currencies qc ON qc.id = tp.quote_currency_id
             WHERE tp.is_active = 1
             ORDER BY qc.code ASC"
        );
        return $stmt->fetchAll() ?: [];
    }
}
