<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ChartDataRepository;
use InvalidArgumentException;

/**
 * Chart Service
 *
 * Orchestrates chart data retrieval, technical indicator computation,
 * user preference management, and market analytics.
 *
 * Technical indicators computed server-side:
 *   SMA, EMA, RSI, MACD, Bollinger Bands, ATR, Stochastic %K/%D
 */
final class ChartService
{
    private ChartDataRepository $repo;

    public function __construct()
    {
        $this->repo = new ChartDataRepository();
    }

    // =========================================================================
    // ALLOWED INTERVALS
    // =========================================================================

    public const ALLOWED_INTERVALS = ['1m', '5m', '15m', '30m', '1h', '4h', '1d', '1w', '1M'];

    private function validateInterval(string $interval): string
    {
        return in_array($interval, self::ALLOWED_INTERVALS, true) ? $interval : '1h';
    }

    // =========================================================================
    // CHART PAGE DATA
    // =========================================================================

    /**
     * Data bundle for the main chart page.
     */
    public function getChartPageData(string $symbol, string $interval, int $userId): array
    {
        $interval = $this->validateInterval($interval);
        $pair     = $this->repo->getPairBySymbol($symbol);
        $pairs    = $this->repo->getActivePairs();
        $prefs    = $pair
            ? ($this->repo->getUserPreferences($userId, (int)$pair['id']) ?? $this->repo->getUserPreferences($userId, null))
            : null;

        if ($prefs) {
            $prefs['indicators'] = $prefs['indicators'] ? json_decode((string)$prefs['indicators'], true) : [];
            $prefs['layout']     = $prefs['layout']     ? json_decode((string)$prefs['layout'],     true) : [];
        }

        $templates = $this->repo->listTemplates($userId);
        foreach ($templates as &$tpl) {
            $tpl['indicators'] = $tpl['indicators'] ? json_decode((string)$tpl['indicators'], true) : [];
        }
        unset($tpl);

        return [
            'pair'      => $pair,
            'pairs'     => $pairs,
            'symbol'    => $pair ? (string)$pair['symbol'] : strtoupper($symbol),
            'interval'  => $interval,
            'prefs'     => $prefs,
            'templates' => $templates,
        ];
    }

    // =========================================================================
    // CANDLE DATA API (AJAX)
    // =========================================================================

    /**
     * Returns OHLCV candles + requested indicators.
     * Used by the AJAX /charts/data endpoint.
     *
     * @param string[] $indicatorKeys  e.g. ['sma_20','ema_50','rsi_14','macd','bb_20','atr_14','stoch_14']
     */
    public function getCandleDataWithIndicators(
        string $symbol,
        string $interval,
        int    $limit,
        array  $indicatorKeys = []
    ): array {
        $interval = $this->validateInterval($interval);
        $limit    = max(50, min(1000, $limit));
        $pair     = $this->repo->getPairBySymbol($symbol);

        if (!$pair) {
            return ['ok' => false, 'message' => 'Pair not found'];
        }

        // Fetch more candles than requested so indicators have warm-up data
        $warmup  = 100;
        $candles = $this->repo->getCandles((int)$pair['id'], $interval, $limit + $warmup);

        if (empty($candles)) {
            return ['ok' => true, 'candles' => [], 'indicators' => [], 'pair' => $pair];
        }

        $closes  = array_map(fn($c) => (float)$c['close_price'], $candles);
        $highs   = array_map(fn($c) => (float)$c['high_price'],  $candles);
        $lows    = array_map(fn($c) => (float)$c['low_price'],   $candles);
        $volumes = array_map(fn($c) => (float)$c['volume'],      $candles);

        // Format OHLCV for the frontend chart library
        $formatted = [];
        foreach ($candles as $c) {
            $formatted[] = [
                'time'   => $c['open_time'],
                'open'   => (float)$c['open_price'],
                'high'   => (float)$c['high_price'],
                'low'    => (float)$c['low_price'],
                'close'  => (float)$c['close_price'],
                'volume' => (float)$c['volume'],
            ];
        }

        // Trim to requested limit (discard warm-up candles from output)
        $totalCandles = count($formatted);
        $startIdx     = max(0, $totalCandles - $limit);
        $formatted    = array_values(array_slice($formatted, $startIdx));

        // Compute indicators
        $indicators = [];
        foreach ($indicatorKeys as $key) {
            [$type, $params] = $this->parseIndicatorKey($key);
            $data = match ($type) {
                'sma'   => $this->computeSMA($closes, $params[0] ?? 20),
                'ema'   => $this->computeEMA($closes, $params[0] ?? 20),
                'rsi'   => $this->computeRSI($closes, $params[0] ?? 14),
                'macd'  => $this->computeMACD($closes, $params[0] ?? 12, $params[1] ?? 26, $params[2] ?? 9),
                'bb'    => $this->computeBollingerBands($closes, $params[0] ?? 20, (float)($params[1] ?? 2)),
                'atr'   => $this->computeATR($highs, $lows, $closes, $params[0] ?? 14),
                'stoch' => $this->computeStochastic($highs, $lows, $closes, $params[0] ?? 14, $params[1] ?? 3),
                'vwap'  => $this->computeVWAP($highs, $lows, $closes, $volumes),
                default => [],
            };
            // Align to startIdx and attach timestamps
            $sliced = array_values(array_slice($data, $startIdx));
            foreach ($sliced as $i => &$point) {
                if (isset($formatted[$i])) {
                    $point['time'] = $formatted[$i]['time'];
                }
            }
            unset($point);
            $indicators[$key] = $sliced;
        }

        return [
            'ok'         => true,
            'candles'    => $formatted,
            'indicators' => $indicators,
            'pair'       => $pair,
            'interval'   => $interval,
        ];
    }

    // =========================================================================
    // TECHNICAL INDICATOR COMPUTATIONS
    // =========================================================================

    /** Parse 'sma_20' → ['sma', [20]] */
    private function parseIndicatorKey(string $key): array
    {
        $parts = explode('_', $key);
        $type  = array_shift($parts);
        $params = array_map('intval', $parts);
        return [$type, $params];
    }

    /** Simple Moving Average */
    private function computeSMA(array $closes, int $period): array
    {
        $result = [];
        $n      = count($closes);
        for ($i = 0; $i < $n; $i++) {
            if ($i < $period - 1) {
                $result[] = ['value' => null];
                continue;
            }
            $slice    = array_slice($closes, $i - $period + 1, $period);
            $result[] = ['value' => round(array_sum($slice) / $period, 8)];
        }
        return $result;
    }

    /** Exponential Moving Average */
    private function computeEMA(array $closes, int $period): array
    {
        $result = [];
        $k      = 2.0 / ($period + 1);
        $ema    = null;
        foreach ($closes as $i => $price) {
            if ($i < $period - 1) {
                $result[] = ['value' => null];
                continue;
            }
            if ($ema === null) {
                $ema = array_sum(array_slice($closes, 0, $period)) / $period;
                $result[] = ['value' => round($ema, 8)];
                continue;
            }
            $ema      = ($price - $ema) * $k + $ema;
            $result[] = ['value' => round($ema, 8)];
        }
        return $result;
    }

    /** Relative Strength Index */
    private function computeRSI(array $closes, int $period): array
    {
        $result = [];
        $gains  = [];
        $losses = [];
        $n      = count($closes);

        for ($i = 0; $i < $n; $i++) {
            if ($i === 0) {
                $result[] = ['value' => null];
                continue;
            }
            $delta = $closes[$i] - $closes[$i - 1];
            $gains[]  = max(0.0, $delta);
            $losses[] = max(0.0, -$delta);
            if ($i < $period) {
                $result[] = ['value' => null];
                continue;
            }
            if ($i === $period) {
                $avgGain = array_sum(array_slice($gains, 0, $period)) / $period;
                $avgLoss = array_sum(array_slice($losses, 0, $period)) / $period;
            } else {
                $lastIdx  = count($gains) - 1;
                $avgGain  = ($result[$i - 1]['rsi_ag'] ?? (array_sum(array_slice($gains,  0, $period)) / $period));
                $avgLoss  = ($result[$i - 1]['rsi_al'] ?? (array_sum(array_slice($losses, 0, $period)) / $period));
                $avgGain  = ($avgGain * ($period - 1) + $gains[$lastIdx]) / $period;
                $avgLoss  = ($avgLoss * ($period - 1) + $losses[$lastIdx]) / $period;
            }
            $rs        = $avgLoss > 0 ? $avgGain / $avgLoss : 100;
            $rsi       = 100 - (100 / (1 + $rs));
            $result[]  = ['value' => round($rsi, 4), 'rsi_ag' => $avgGain, 'rsi_al' => $avgLoss];
        }
        // Remove internal tracking keys
        return array_map(fn($r) => ['value' => $r['value']], $result);
    }

    /** MACD (fast EMA, slow EMA, signal EMA) */
    private function computeMACD(array $closes, int $fast, int $slow, int $signal): array
    {
        $fastEma   = $this->computeEMA($closes, $fast);
        $slowEma   = $this->computeEMA($closes, $slow);
        $macdLine  = [];
        $n         = count($closes);
        for ($i = 0; $i < $n; $i++) {
            $f = $fastEma[$i]['value'];
            $s = $slowEma[$i]['value'];
            $macdLine[] = ($f !== null && $s !== null) ? round($f - $s, 8) : null;
        }

        // Signal line (EMA of macdLine)
        $validMacd    = [];
        $validIndices = [];
        foreach ($macdLine as $i => $v) {
            if ($v !== null) {
                $validMacd[]    = $v;
                $validIndices[] = $i;
            }
        }
        $signalEma = $this->computeEMA($validMacd, $signal);

        // Rebuild full-length arrays
        $signalLine = array_fill(0, $n, null);
        foreach ($validIndices as $j => $origI) {
            $signalLine[$origI] = $signalEma[$j]['value'];
        }

        $result = [];
        for ($i = 0; $i < $n; $i++) {
            $m = $macdLine[$i];
            $s = $signalLine[$i];
            $result[] = [
                'macd'      => $m,
                'signal'    => $s,
                'histogram' => ($m !== null && $s !== null) ? round($m - $s, 8) : null,
            ];
        }
        return $result;
    }

    /** Bollinger Bands (middle SMA, upper, lower) */
    private function computeBollingerBands(array $closes, int $period, float $stdDevMult): array
    {
        $result = [];
        $n      = count($closes);
        for ($i = 0; $i < $n; $i++) {
            if ($i < $period - 1) {
                $result[] = ['middle' => null, 'upper' => null, 'lower' => null];
                continue;
            }
            $slice  = array_slice($closes, $i - $period + 1, $period);
            $mean   = array_sum($slice) / $period;
            $variance = array_sum(array_map(fn($v) => ($v - $mean) ** 2, $slice)) / $period;
            $stdDev = sqrt($variance);
            $result[] = [
                'middle' => round($mean, 8),
                'upper'  => round($mean + $stdDevMult * $stdDev, 8),
                'lower'  => round($mean - $stdDevMult * $stdDev, 8),
            ];
        }
        return $result;
    }

    /** Average True Range */
    private function computeATR(array $highs, array $lows, array $closes, int $period): array
    {
        $result = [];
        $n      = count($closes);
        $trList = [];
        for ($i = 0; $i < $n; $i++) {
            if ($i === 0) {
                $tr = $highs[$i] - $lows[$i];
            } else {
                $tr = max(
                    $highs[$i] - $lows[$i],
                    abs($highs[$i] - $closes[$i - 1]),
                    abs($lows[$i] - $closes[$i - 1])
                );
            }
            $trList[] = $tr;
            if ($i < $period - 1) {
                $result[] = ['value' => null];
                continue;
            }
            if ($i === $period - 1) {
                $atr      = array_sum(array_slice($trList, 0, $period)) / $period;
                $result[] = ['value' => round($atr, 8), '_atr' => $atr];
                continue;
            }
            $prevAtr  = $result[$i - 1]['_atr'] ?? 0;
            $atr      = ($prevAtr * ($period - 1) + $tr) / $period;
            $result[] = ['value' => round($atr, 8), '_atr' => $atr];
        }
        return array_map(fn($r) => ['value' => $r['value']], $result);
    }

    /** Stochastic Oscillator %K and %D */
    private function computeStochastic(array $highs, array $lows, array $closes, int $kPeriod, int $dPeriod): array
    {
        $result = [];
        $n      = count($closes);
        $kLine  = [];
        for ($i = 0; $i < $n; $i++) {
            if ($i < $kPeriod - 1) {
                $result[] = ['k' => null, 'd' => null];
                $kLine[]  = null;
                continue;
            }
            $highSlice = array_slice($highs, $i - $kPeriod + 1, $kPeriod);
            $lowSlice  = array_slice($lows,  $i - $kPeriod + 1, $kPeriod);
            $hh        = max($highSlice);
            $ll        = min($lowSlice);
            $k         = $hh > $ll ? ($closes[$i] - $ll) / ($hh - $ll) * 100 : 50;
            $kLine[]   = round($k, 4);
            $result[]  = ['k' => round($k, 4), 'd' => null];
        }

        // %D = SMA of %K over dPeriod
        $validK = array_filter($kLine, fn($v) => $v !== null);
        $validK = array_values($validK);
        $dSma   = $this->computeSMA($validK, $dPeriod);

        $dIdx = 0;
        for ($i = 0; $i < $n; $i++) {
            if ($kLine[$i] !== null) {
                $result[$i]['d'] = $dSma[$dIdx]['value'] ?? null;
                $dIdx++;
            }
        }
        return $result;
    }

    /** VWAP (cumulative intra-period, resets each day) */
    private function computeVWAP(array $highs, array $lows, array $closes, array $volumes): array
    {
        $result    = [];
        $cumPV     = 0.0;
        $cumVol    = 0.0;
        foreach ($closes as $i => $close) {
            $typicalPrice = ($highs[$i] + $lows[$i] + $close) / 3;
            $vol          = $volumes[$i];
            $cumPV        += $typicalPrice * $vol;
            $cumVol       += $vol;
            $result[]      = ['value' => $cumVol > 0 ? round($cumPV / $cumVol, 8) : null];
        }
        return $result;
    }

    // =========================================================================
    // COMPARISON CHART DATA
    // =========================================================================

    /**
     * Multi-pair normalised performance comparison.
     */
    public function getComparisonData(array $symbols, string $interval, int $limit): array
    {
        $interval = $this->validateInterval($interval);
        $limit    = max(20, min(500, $limit));
        $pairIds  = [];
        $symbolMap = [];
        foreach ($symbols as $sym) {
            $pair = $this->repo->getPairBySymbol($sym);
            if ($pair) {
                $pairIds[]                      = (int)$pair['id'];
                $symbolMap[(int)$pair['id']]    = (string)$pair['symbol'];
            }
        }

        if (empty($pairIds)) {
            return ['ok' => false, 'message' => 'No valid pairs'];
        }

        $compData = $this->repo->getComparisonData($pairIds, $interval, $limit);
        $result   = [];
        foreach ($compData as $pairId => $series) {
            $result[$symbolMap[$pairId] ?? $pairId] = $series;
        }
        return ['ok' => true, 'data' => $result, 'interval' => $interval];
    }

    /**
     * Get available pairs for comparison selector.
     */
    public function getComparisonPageData(array $selectedSymbols, string $interval): array
    {
        $interval = $this->validateInterval($interval);
        $allPairs = $this->repo->getActivePairs();
        return [
            'pairs'    => $allPairs,
            'selected' => $selectedSymbols,
            'interval' => $interval,
        ];
    }

    // =========================================================================
    // VOLUME PROFILE
    // =========================================================================

    public function getVolumeProfile(string $symbol, int $hours = 24, int $buckets = 30): array
    {
        $pair = $this->repo->getPairBySymbol($symbol);
        if (!$pair) {
            return ['ok' => false, 'message' => 'Pair not found'];
        }
        $profile = $this->repo->getVolumeProfile((int)$pair['id'], $hours, $buckets);
        $maxVol  = count($profile) > 0 ? max(array_column($profile, 'volume')) : 1;
        foreach ($profile as &$item) {
            $item['pct'] = $maxVol > 0 ? round($item['volume'] / $maxVol * 100, 2) : 0;
        }
        unset($item);
        return ['ok' => true, 'profile' => $profile, 'pair' => $pair];
    }

    // =========================================================================
    // ADMIN MARKET ANALYTICS
    // =========================================================================

    /**
     * Data for the admin analytics dashboard.
     */
    public function getAdminAnalyticsDashboard(): array
    {
        return [
            'kpis'          => $this->repo->getAnalyticsKpis(),
            'daily_volume'  => $this->repo->getDailyVolumeAggregation(30),
            'top_movers'    => $this->repo->getTopMovers(20),
            'market_types'  => $this->repo->getVolumeByMarketType(),
            'heatmap'       => $this->repo->getMarketHeatmapData(),
        ];
    }

    /**
     * Volatility analysis page data.
     */
    public function getVolatilityAnalysis(int $days = 30): array
    {
        $scores = $this->repo->getPairVolatilityScores($days);
        // Normalise volatility to 0-100 score
        $maxVol = count($scores) > 0 ? max(array_column($scores, 'avg_hl_volatility')) : 1;
        foreach ($scores as &$s) {
            $s['volatility_score'] = $maxVol > 0
                ? round((float)$s['avg_hl_volatility'] / $maxVol * 100, 2)
                : 0;
        }
        unset($s);
        return [
            'scores' => $scores,
            'days'   => $days,
        ];
    }

    /**
     * Correlation matrix computation.
     */
    public function getCorrelationMatrix(int $days = 30): array
    {
        $pricesByPair = $this->repo->getDailyClosePrices($days);
        if (empty($pricesByPair)) {
            return ['symbols' => [], 'matrix' => []];
        }

        // Build aligned returns
        $symbols = array_keys($pricesByPair);
        $dates   = [];
        foreach ($pricesByPair as $series) {
            $dates = array_unique(array_merge($dates, array_keys($series)));
        }
        sort($dates);

        // Compute daily log returns
        $returns = [];
        foreach ($symbols as $sym) {
            $series  = $pricesByPair[$sym];
            $prev    = null;
            foreach ($dates as $date) {
                $price = $series[$date] ?? null;
                if ($prev !== null && $price !== null && $price > 0 && $prev > 0) {
                    $returns[$sym][$date] = log($price / $prev);
                } else {
                    $returns[$sym][$date] = null;
                }
                $prev = $price ?? $prev;
            }
        }

        // Pearson correlation for each pair of symbols
        $matrix = [];
        foreach ($symbols as $symA) {
            $row = [];
            foreach ($symbols as $symB) {
                if ($symA === $symB) {
                    $row[$symB] = 1.0;
                    continue;
                }
                $pairsA = [];
                $pairsB = [];
                foreach ($dates as $date) {
                    $a = $returns[$symA][$date] ?? null;
                    $b = $returns[$symB][$date] ?? null;
                    if ($a !== null && $b !== null) {
                        $pairsA[] = $a;
                        $pairsB[] = $b;
                    }
                }
                $row[$symB] = count($pairsA) >= 5 ? $this->pearsonCorrelation($pairsA, $pairsB) : null;
            }
            $matrix[$symA] = $row;
        }

        return ['symbols' => $symbols, 'matrix' => $matrix, 'days' => $days];
    }

    private function pearsonCorrelation(array $x, array $y): float
    {
        $n    = count($x);
        $sumX = array_sum($x);
        $sumY = array_sum($y);
        $sumXY = 0.0;
        $sumX2 = 0.0;
        $sumY2 = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $sumXY += $x[$i] * $y[$i];
            $sumX2 += $x[$i] ** 2;
            $sumY2 += $y[$i] ** 2;
        }
        $denom = sqrt(($n * $sumX2 - $sumX ** 2) * ($n * $sumY2 - $sumY ** 2));
        if (abs($denom) < 1e-10) {
            return 0.0;
        }
        return round(($n * $sumXY - $sumX * $sumY) / $denom, 4);
    }

    // =========================================================================
    // USER PREFERENCES
    // =========================================================================

    /**
     * Resolve a pair ID from a symbol string (delegate to repository).
     * Allows controllers to avoid directly instantiating the repository.
     */
    public function getPairIdBySymbol(string $symbol): ?int
    {
        $pair = $this->repo->getPairBySymbol($symbol);
        return $pair ? (int)$pair['id'] : null;
    }

    /**
     * Save user chart preferences.
     */
    public function saveUserPreferences(int $userId, ?int $pairId, array $data): void
    {
        $allowed = ['interval_code', 'chart_type', 'indicators', 'drawings', 'layout'];
        $clean   = array_intersect_key($data, array_flip($allowed));

        if (isset($clean['interval_code'])) {
            $clean['interval_code'] = $this->validateInterval((string)$clean['interval_code']);
        }

        $chartTypes = ['candlestick', 'line', 'bar', 'area', 'heikin_ashi'];
        if (isset($clean['chart_type']) && !in_array($clean['chart_type'], $chartTypes, true)) {
            $clean['chart_type'] = 'candlestick';
        }

        $this->repo->saveUserPreferences($userId, $pairId, $clean);
    }

    /**
     * Save a named chart template.
     */
    public function saveTemplate(int $userId, array $data): array
    {
        $id = $this->repo->saveTemplate($userId, $data);
        return ['ok' => true, 'id' => $id];
    }

    public function deleteTemplate(int $userId, int $templateId): bool
    {
        return $this->repo->deleteTemplate($userId, $templateId);
    }

    public function getTemplate(int $templateId): ?array
    {
        return $this->repo->findTemplate($templateId);
    }

    public function listTemplates(int $userId): array
    {
        $templates = $this->repo->listTemplates($userId);
        foreach ($templates as &$t) {
            $t['indicators'] = $t['indicators'] ? json_decode((string)$t['indicators'], true) : [];
            $t['layout']     = $t['layout']     ? json_decode((string)$t['layout'],     true) : [];
        }
        unset($t);
        return $templates;
    }
}
