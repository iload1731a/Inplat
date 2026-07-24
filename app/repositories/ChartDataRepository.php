<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Libraries\Database;
use PDO;

/**
 * Chart Data Repository
 *
 * Handles all DB queries for:
 *  - OHLCV candlestick data (from candlesticks table + trade-derived fallback)
 *  - Multi-pair comparison data
 *  - Market volatility analysis
 *  - Price correlation calculations
 *  - Volume profile
 *  - User chart preferences and saved templates
 */
final class ChartDataRepository
{
    // =========================================================================
    // OHLCV CANDLE DATA
    // =========================================================================

    /**
     * Fetch OHLCV candles from the dedicated candlesticks table.
     * Falls back to trade-derived buckets when the table has no data for this pair/interval.
     */
    public function getCandles(int $pairId, string $interval, int $limit): array
    {
        $candles = $this->getCandlesFromTable($pairId, $interval, $limit);
        if (count($candles) < 5) {
            $candles = $this->getCandlesFromTrades($pairId, $interval, $limit);
        }
        return $candles;
    }

    /**
     * Fetch from the candlesticks table (preferred — pre-aggregated, fast).
     */
    public function getCandlesFromTable(int $pairId, string $interval, int $limit): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT open_time, open_price, high_price, low_price, close_price, volume, quote_volume, trade_count
             FROM candlesticks
             WHERE trading_pair_id = :pid AND interval_code = :iv
             ORDER BY open_time DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':pid', $pairId, PDO::PARAM_INT);
        $stmt->bindValue(':iv', $interval);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_reverse($stmt->fetchAll() ?: []);
    }

    /**
     * Derive OHLCV buckets on-the-fly from the trades table.
     * Used when candlesticks table is empty (warm-up period).
     *
     * Interval is validated against $allowedIntervals before being used as a key,
     * so $bucketExpr is always a fixed SQL expression — no user input reaches the query.
     */
    public function getCandlesFromTrades(int $pairId, string $interval, int $limit): array
    {
        $allowedIntervals = ['1m', '5m', '15m', '30m', '1h', '4h', '1d', '1w', '1M'];
        if (!in_array($interval, $allowedIntervals, true)) {
            $interval = '1h';
        }
        $intervalMap = [
            '1m'  => 'FLOOR(UNIX_TIMESTAMP(created_at)/60)*60',
            '5m'  => 'FLOOR(UNIX_TIMESTAMP(created_at)/300)*300',
            '15m' => 'FLOOR(UNIX_TIMESTAMP(created_at)/900)*900',
            '30m' => 'FLOOR(UNIX_TIMESTAMP(created_at)/1800)*1800',
            '1h'  => 'FLOOR(UNIX_TIMESTAMP(created_at)/3600)*3600',
            '4h'  => 'FLOOR(UNIX_TIMESTAMP(created_at)/14400)*14400',
            '1d'  => 'DATE(created_at)',
            '1w'  => 'DATE(DATE_SUB(created_at, INTERVAL WEEKDAY(created_at) DAY))',
            '1M'  => 'DATE_FORMAT(created_at, \'%Y-%m-01\')',
        ];
        $bucketExpr = $intervalMap[$interval];
        $stmt = Database::connection()->prepare(
            "SELECT
                FROM_UNIXTIME({$bucketExpr})                                               AS open_time,
                SUBSTRING_INDEX(GROUP_CONCAT(price ORDER BY created_at ASC),  ',', 1)     AS open_price,
                MAX(price)                                                                  AS high_price,
                MIN(price)                                                                  AS low_price,
                SUBSTRING_INDEX(GROUP_CONCAT(price ORDER BY created_at DESC), ',', 1)     AS close_price,
                SUM(quantity)                                                               AS volume,
                SUM(price * quantity)                                                       AS quote_volume,
                COUNT(*)                                                                    AS trade_count
             FROM trades
             WHERE trading_pair_id = :pid
             GROUP BY {$bucketExpr}
             ORDER BY {$bucketExpr} DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':pid', $pairId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_reverse($stmt->fetchAll() ?: []);
    }

    /**
     * Get pair info (id, symbol, base/quote codes, precision) by symbol.
     */
    public function getPairBySymbol(string $symbol): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT tp.id, tp.symbol, tp.market_type,
                    tp.price_precision, tp.quantity_precision,
                    tp.maker_fee_percent, tp.taker_fee_percent,
                    bc.code AS base_code, bc.name AS base_name, bc.icon_url AS base_icon,
                    qc.code AS quote_code, qc.name AS quote_name,
                    COALESCE(pt.last_price, 0)         AS last_price,
                    COALESCE(pt.change_24h_percent, 0) AS change_24h_percent,
                    COALESCE(pt.high_24h, 0)           AS high_24h,
                    COALESCE(pt.low_24h, 0)            AS low_24h,
                    COALESCE(pt.volume_24h, 0)         AS volume_24h,
                    COALESCE(pt.best_bid, 0)           AS best_bid,
                    COALESCE(pt.best_ask, 0)           AS best_ask
             FROM trading_pairs tp
             INNER JOIN currencies bc ON bc.id = tp.base_currency_id
             INNER JOIN currencies qc ON qc.id = tp.quote_currency_id
             LEFT  JOIN price_tickers pt ON pt.trading_pair_id = tp.id
             WHERE tp.symbol = :sym AND tp.is_active = 1
             LIMIT 1"
        );
        $stmt->execute([':sym' => strtoupper($symbol)]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Paginated list of active pairs for the pair selector.
     */
    public function getActivePairs(string $marketType = ''): array
    {
        $sql = "SELECT tp.id, tp.symbol, tp.market_type,
                       bc.code AS base_code, qc.code AS quote_code,
                       COALESCE(pt.last_price, 0)         AS last_price,
                       COALESCE(pt.change_24h_percent, 0) AS change_24h_percent,
                       COALESCE(pt.volume_24h, 0)         AS volume_24h
                FROM trading_pairs tp
                INNER JOIN currencies bc ON bc.id = tp.base_currency_id
                INNER JOIN currencies qc ON qc.id = tp.quote_currency_id
                LEFT  JOIN price_tickers pt ON pt.trading_pair_id = tp.id
                WHERE tp.is_active = 1 AND tp.is_visible = 1";
        $params = [];
        if ($marketType !== '') {
            $sql .= ' AND tp.market_type = :mt';
            $params[':mt'] = $marketType;
        }
        $sql .= ' ORDER BY pt.volume_24h DESC, tp.symbol ASC LIMIT 200';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    // =========================================================================
    // MULTI-PAIR COMPARISON
    // =========================================================================

    /**
     * Get normalised price-change series for multiple pairs (comparison chart).
     * Returns an array keyed by symbol, each with [{time, pct_change}].
     */
    public function getComparisonData(array $pairIds, string $interval, int $limit): array
    {
        if (empty($pairIds)) {
            return [];
        }
        $result = [];
        foreach ($pairIds as $pairId) {
            $candles = $this->getCandles((int)$pairId, $interval, $limit);
            if (empty($candles)) {
                continue;
            }
            $basePrice = (float)($candles[0]['close_price'] ?? 0);
            if ($basePrice <= 0) {
                continue;
            }
            $series = [];
            foreach ($candles as $c) {
                $series[] = [
                    'time'       => $c['open_time'],
                    'pct_change' => round(((float)$c['close_price'] - $basePrice) / $basePrice * 100, 4),
                    'price'      => (float)$c['close_price'],
                ];
            }
            $result[(int)$pairId] = $series;
        }
        return $result;
    }

    /**
     * Get pair symbols for a list of pair IDs.
     */
    public function getPairSymbolsForIds(array $pairIds): array
    {
        if (empty($pairIds)) {
            return [];
        }
        // Guard against excessively large IN clauses
        $pairIds = array_slice(array_values($pairIds), 0, 100);
        $placeholders = implode(',', array_fill(0, count($pairIds), '?'));
        $stmt = Database::connection()->prepare(
            "SELECT id, symbol, market_type,
                    COALESCE(pt.last_price, 0) AS last_price,
                    COALESCE(pt.change_24h_percent, 0) AS change_24h_percent
             FROM trading_pairs tp
             LEFT JOIN price_tickers pt ON pt.trading_pair_id = tp.id
             WHERE tp.id IN ({$placeholders}) AND tp.is_active = 1"
        );
        $stmt->execute(array_values($pairIds));
        $rows = $stmt->fetchAll() ?: [];
        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row['id']] = $row;
        }
        return $map;
    }

    // =========================================================================
    // MARKET ANALYTICS — VOLATILITY
    // =========================================================================

    /**
     * Calculate daily price range (high-low)/mid volatility for each active pair over N days.
     */
    public function getPairVolatilityScores(int $days = 30): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT tp.id, tp.symbol, tp.market_type,
                    bc.code AS base_code, qc.code AS quote_code,
                    COUNT(*)                                                         AS candle_count,
                    AVG((c.high_price - c.low_price) / NULLIF(c.close_price, 0))    AS avg_hl_volatility,
                    STDDEV(c.close_price)                                            AS price_stddev,
                    MIN(c.low_price)                                                 AS period_low,
                    MAX(c.high_price)                                                AS period_high,
                    SUM(c.volume)                                                    AS total_volume
             FROM candlesticks c
             INNER JOIN trading_pairs tp ON tp.id = c.trading_pair_id
             INNER JOIN currencies bc ON bc.id = tp.base_currency_id
             INNER JOIN currencies qc ON qc.id = tp.quote_currency_id
             WHERE c.interval_code = '1d'
               AND c.open_time >= DATE_SUB(NOW(), INTERVAL :days DAY)
               AND tp.is_active = 1
             GROUP BY tp.id, tp.symbol, tp.market_type, bc.code, qc.code
             HAVING candle_count >= 5
             ORDER BY avg_hl_volatility DESC
             LIMIT 100"
        );
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Daily volatility series for a single pair (for the sparkline chart).
     */
    public function getPairDailyVolatilitySeries(int $pairId, int $days = 60): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT DATE(open_time) AS date,
                    (high_price - low_price) / NULLIF(close_price, 0) * 100 AS volatility_pct,
                    volume, close_price
             FROM candlesticks
             WHERE trading_pair_id = :pid AND interval_code = '1d'
               AND open_time >= DATE_SUB(NOW(), INTERVAL :days DAY)
             ORDER BY open_time ASC"
        );
        $stmt->bindValue(':pid', $pairId, PDO::PARAM_INT);
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    // =========================================================================
    // MARKET ANALYTICS — CORRELATION
    // =========================================================================

    /**
     * Fetch daily close prices for multiple top pairs over N days for correlation matrix.
     */
    public function getDailyClosePrices(int $days = 30, int $maxPairs = 15): array
    {
        // Get top pairs by volume
        $stmt = Database::connection()->prepare(
            "SELECT DISTINCT c.trading_pair_id, tp.symbol
             FROM candlesticks c
             INNER JOIN trading_pairs tp ON tp.id = c.trading_pair_id
             INNER JOIN price_tickers pt ON pt.trading_pair_id = tp.id
             WHERE c.interval_code = '1d' AND tp.is_active = 1
             ORDER BY pt.volume_24h DESC
             LIMIT :maxp"
        );
        $stmt->bindValue(':maxp', $maxPairs, PDO::PARAM_INT);
        $stmt->execute();
        $pairs = $stmt->fetchAll() ?: [];
        if (empty($pairs)) {
            return [];
        }

        $pairIds = array_column($pairs, 'trading_pair_id');
        $symbolMap = array_column($pairs, 'symbol', 'trading_pair_id');
        $placeholders = implode(',', array_fill(0, count($pairIds), '?'));
        // $days is already validated upstream (max(7, min(365, (int)$days))) so casting
        // to int here is safe for direct interpolation in INTERVAL which PDO cannot parameterize.
        $daysInt = (int)$days;

        $stmt = Database::connection()->prepare(
            "SELECT trading_pair_id, DATE(open_time) AS date, close_price
             FROM candlesticks
             WHERE trading_pair_id IN ({$placeholders})
               AND interval_code = '1d'
               AND open_time >= DATE_SUB(NOW(), INTERVAL {$daysInt} DAY)
             ORDER BY open_time ASC"
        );
        $stmt->execute($pairIds);
        $rows = $stmt->fetchAll() ?: [];

        $pricesByPair = [];
        foreach ($rows as $row) {
            $sym = $symbolMap[$row['trading_pair_id']] ?? $row['trading_pair_id'];
            $pricesByPair[$sym][$row['date']] = (float)$row['close_price'];
        }
        return $pricesByPair;
    }

    // =========================================================================
    // VOLUME PROFILE
    // =========================================================================

    /**
     * Build a volume profile (price level → volume) for a pair.
     * Groups trades into $buckets price bands.
     */
    public function getVolumeProfile(int $pairId, int $hours = 24, int $buckets = 30): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT MIN(price) AS price_min, MAX(price) AS price_max, SUM(quantity) AS total_volume
             FROM trades
             WHERE trading_pair_id = :pid
               AND created_at >= DATE_SUB(NOW(), INTERVAL :hrs HOUR)
             LIMIT 1"
        );
        $stmt->bindValue(':pid', $pairId, PDO::PARAM_INT);
        $stmt->bindValue(':hrs', $hours, PDO::PARAM_INT);
        $stmt->execute();
        $range = $stmt->fetch();
        if (!$range || (float)$range['price_min'] <= 0) {
            return [];
        }

        $priceMin = (float)$range['price_min'];
        $priceMax = (float)$range['price_max'];
        if ($priceMax <= $priceMin) {
            return [];
        }
        $bucketSize = ($priceMax - $priceMin) / $buckets;

        $stmt = Database::connection()->prepare(
            "SELECT FLOOR((price - :pmin) / :bsize) AS bucket_idx,
                    SUM(quantity) AS volume,
                    COUNT(*) AS trade_count,
                    AVG(price) AS avg_price
             FROM trades
             WHERE trading_pair_id = :pid
               AND created_at >= DATE_SUB(NOW(), INTERVAL :hrs HOUR)
             GROUP BY bucket_idx
             ORDER BY bucket_idx ASC"
        );
        $stmt->bindValue(':pmin', $priceMin);
        $stmt->bindValue(':bsize', $bucketSize);
        $stmt->bindValue(':pid', $pairId, PDO::PARAM_INT);
        $stmt->bindValue(':hrs', $hours, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll() ?: [];

        $profile = [];
        foreach ($rows as $row) {
            $idx = (int)$row['bucket_idx'];
            $profile[] = [
                'price_level'  => round($priceMin + $idx * $bucketSize + $bucketSize / 2, 8),
                'volume'       => (float)$row['volume'],
                'trade_count'  => (int)$row['trade_count'],
            ];
        }
        return $profile;
    }

    // =========================================================================
    // ADMIN MARKET ANALYTICS — AGGREGATE STATS
    // =========================================================================

    /**
     * Platform-wide chart analytics KPIs.
     */
    public function getAnalyticsKpis(): array
    {
        $db = Database::connection();
        $row = $db->query(
            "SELECT
                COUNT(DISTINCT trading_pair_id) AS active_pairs,
                SUM(CASE WHEN interval_code = '1d' AND open_time >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN volume ELSE 0 END) AS volume_24h,
                COUNT(*) AS total_candles
             FROM candlesticks
             WHERE open_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        )->fetch();

        $tradeRow = $db->query(
            "SELECT COUNT(*) AS trade_count, SUM(price * quantity) AS notional_volume
             FROM trades WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        )->fetch();

        $pairRow = $db->query(
            "SELECT COUNT(*) AS active_pairs FROM trading_pairs WHERE is_active = 1"
        )->fetch();

        return [
            'active_pairs'    => (int)($pairRow['active_pairs'] ?? 0),
            'volume_24h'      => (float)($row['volume_24h'] ?? 0),
            'total_candles'   => (int)($row['total_candles'] ?? 0),
            'trades_24h'      => (int)($tradeRow['trade_count'] ?? 0),
            'notional_24h'    => (float)($tradeRow['notional_volume'] ?? 0),
        ];
    }

    /**
     * Daily traded volume aggregation for a bar chart (last N days).
     */
    public function getDailyVolumeAggregation(int $days = 30): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT DATE(created_at) AS date, SUM(price * quantity) AS notional, COUNT(*) AS trades
             FROM trades
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
             GROUP BY DATE(created_at)
             ORDER BY date ASC"
        );
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Top N movers (gainers and losers) over 24H.
     */
    public function getTopMovers(int $limit = 10): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT tp.symbol, tp.market_type,
                    pt.last_price, pt.change_24h_percent, pt.volume_24h,
                    bc.code AS base_code, qc.code AS quote_code
             FROM price_tickers pt
             INNER JOIN trading_pairs tp ON tp.id = pt.trading_pair_id
             INNER JOIN currencies bc ON bc.id = tp.base_currency_id
             INNER JOIN currencies qc ON qc.id = tp.quote_currency_id
             WHERE tp.is_active = 1
             ORDER BY ABS(pt.change_24h_percent) DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Volume distribution by market type.
     */
    public function getVolumeByMarketType(): array
    {
        $stmt = Database::connection()->query(
            "SELECT tp.market_type, SUM(pt.volume_24h) AS total_volume
             FROM price_tickers pt
             INNER JOIN trading_pairs tp ON tp.id = pt.trading_pair_id
             WHERE tp.is_active = 1
             GROUP BY tp.market_type"
        );
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Market heatmap data: symbol, change%, volume bubble size.
     */
    public function getMarketHeatmapData(string $marketType = ''): array
    {
        $sql = "SELECT tp.symbol, tp.market_type,
                       pt.last_price, pt.change_24h_percent,
                       COALESCE(pt.volume_24h, 0) AS volume_24h,
                       bc.code AS base_code
                FROM price_tickers pt
                INNER JOIN trading_pairs tp ON tp.id = pt.trading_pair_id
                INNER JOIN currencies bc ON bc.id = tp.base_currency_id
                INNER JOIN currencies qc ON qc.id = tp.quote_currency_id
                WHERE tp.is_active = 1";
        $params = [];
        if ($marketType !== '') {
            $sql .= ' AND tp.market_type = :mt';
            $params[':mt'] = $marketType;
        }
        $sql .= ' ORDER BY pt.volume_24h DESC LIMIT 100';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    // =========================================================================
    // USER CHART PREFERENCES
    // =========================================================================

    /**
     * Load user preferences for a specific pair (or global default when pairId = null).
     */
    public function getUserPreferences(int $userId, ?int $pairId = null): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT * FROM user_chart_preferences
             WHERE user_id = :uid AND trading_pair_id <=> :pid
             LIMIT 1"
        );
        $stmt->execute([':uid' => $userId, ':pid' => $pairId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Upsert user preferences.
     */
    public function saveUserPreferences(int $userId, ?int $pairId, array $data): void
    {
        $stmt = Database::connection()->prepare(
            "INSERT INTO user_chart_preferences
                (user_id, trading_pair_id, interval_code, chart_type, indicators, drawings, layout)
             VALUES (:uid, :pid, :iv, :ct, :ind, :drw, :lay)
             ON DUPLICATE KEY UPDATE
                interval_code = VALUES(interval_code),
                chart_type    = VALUES(chart_type),
                indicators    = VALUES(indicators),
                drawings      = VALUES(drawings),
                layout        = VALUES(layout),
                updated_at    = NOW()"
        );
        $stmt->execute([
            ':uid' => $userId,
            ':pid' => $pairId,
            ':iv'  => $data['interval_code'] ?? '1h',
            ':ct'  => $data['chart_type'] ?? 'candlestick',
            ':ind' => isset($data['indicators']) ? json_encode($data['indicators']) : null,
            ':drw' => isset($data['drawings'])   ? json_encode($data['drawings'])   : null,
            ':lay' => isset($data['layout'])     ? json_encode($data['layout'])     : null,
        ]);
    }

    // =========================================================================
    // CHART TEMPLATES
    // =========================================================================

    public function listTemplates(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT id, name, description, chart_type, indicators, layout, is_public, created_at
             FROM chart_templates
             WHERE user_id = :uid OR is_public = 1
             ORDER BY is_public ASC, name ASC"
        );
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll() ?: [];
    }

    public function saveTemplate(int $userId, array $data): int
    {
        $stmt = Database::connection()->prepare(
            "INSERT INTO chart_templates (user_id, name, description, chart_type, indicators, layout, is_public)
             VALUES (:uid, :name, :desc, :ct, :ind, :lay, :pub)"
        );
        $stmt->execute([
            ':uid'  => $userId,
            ':name' => substr((string)($data['name'] ?? 'My Template'), 0, 80),
            ':desc' => isset($data['description']) ? substr((string)$data['description'], 0, 255) : null,
            ':ct'   => $data['chart_type'] ?? 'candlestick',
            ':ind'  => json_encode($data['indicators'] ?? []),
            ':lay'  => isset($data['layout']) ? json_encode($data['layout']) : null,
            ':pub'  => (int)(bool)($data['is_public'] ?? false),
        ]);
        return (int)Database::connection()->lastInsertId();
    }

    public function deleteTemplate(int $userId, int $templateId): bool
    {
        $stmt = Database::connection()->prepare(
            "DELETE FROM chart_templates WHERE id = :tid AND user_id = :uid"
        );
        $stmt->execute([':tid' => $templateId, ':uid' => $userId]);
        return $stmt->rowCount() > 0;
    }

    public function findTemplate(int $templateId): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT * FROM chart_templates WHERE id = :tid LIMIT 1"
        );
        $stmt->execute([':tid' => $templateId]);
        return $stmt->fetch() ?: null;
    }
}
