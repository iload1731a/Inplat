<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\RequestContext;
use App\Models\Exchange;
use App\Models\TradingPair;
use App\Repositories\AdminManagementRepository;
use App\Repositories\MarketsRepository;
use RuntimeException;

final class MarketDataService
{
    private const int MIN_POLL_INTERVAL_SECONDS = 15;
    private const int DEFAULT_PRECISION = 8;

    public function __construct(
        private readonly MarketsRepository $repo = new MarketsRepository(),
        private readonly AdminManagementRepository $mgmt = new AdminManagementRepository(),
        private readonly BinanceService $binance = new BinanceService(),
    ) {}

    public function dashboard(): array
    {
        $provider = $this->repo->findProviderByCode(Exchange::BINANCE);
        $providerId = (int)($provider['id'] ?? 0);
        return [
            'provider' => $provider,
            'stats' => $this->repo->getMarketStats(),
            'syncStats' => $this->repo->getSyncLogStats(),
            'recentJobs' => $providerId > 0 ? $this->repo->getLatestImportJobs($providerId, 15) : [],
            'pairs' => $providerId > 0 ? $this->repo->listActiveFeedPairsForProvider($providerId, 200) : [],
        ];
    }

    public function syncExchangeInfo(int $adminId): array
    {
        $provider = $this->requireBinanceProvider();
        $providerId = (int)$provider['id'];
        $start = microtime(true);
        $jobId = $this->repo->createPairImportJob($providerId, $adminId);
        $pairsFound = 0;
        $pairsCreated = 0;
        $pairsUpdated = 0;
        $pairsSkipped = 0;

        try {
            $symbols = $this->binance->exchangeInfo();
            foreach ($symbols as $symbol) {
                if (!is_array($symbol) || !isset($symbol['baseAsset'], $symbol['quoteAsset'], $symbol['symbol'])) {
                    continue;
                }
                $status = strtoupper((string)($symbol['status'] ?? ''));
                if ($status !== 'TRADING') {
                    continue;
                }

                $baseCode = strtoupper((string)$symbol['baseAsset']);
                $quoteCode = strtoupper((string)$symbol['quoteAsset']);
                $baseCurrency = $this->repo->findCurrencyByCode($baseCode);
                $quoteCurrency = $this->repo->findCurrencyByCode($quoteCode);
                $pairsFound++;

                $stagingStatus = 'pending';
                $newPairId = null;
                if ($baseCurrency === null || $quoteCurrency === null) {
                    $stagingStatus = 'rejected';
                    $pairsSkipped++;
                } else {
                    $existing = $this->repo->findPairByBaseQuote((int)$baseCurrency['id'], (int)$quoteCurrency['id'], 'spot');
                    $rules = $this->extractTradingRules($symbol);
                    if ($existing === null) {
                        $newPairId = $this->repo->createTradingPairFromImport([
                            'symbol' => TradingPair::toInternalSymbol($baseCode, $quoteCode),
                            'base_currency_id' => (int)$baseCurrency['id'],
                            'quote_currency_id' => (int)$quoteCurrency['id'],
                            'market_type' => 'spot',
                            ...$rules,
                        ]);
                        $this->repo->upsertFeedSubscription($newPairId, [
                            'primary_provider_id' => $providerId,
                            'fallback_provider_id' => null,
                            'feed_mode' => 'polling',
                            'poll_interval_seconds' => max(self::MIN_POLL_INTERVAL_SECONDS, (int)($provider['default_sync_interval_seconds'] ?? 60)),
                            'max_allowed_staleness_seconds' => 60,
                            'is_active' => 1,
                        ]);
                        $pairsCreated++;
                        $stagingStatus = 'approved';
                    } else {
                        $this->repo->updateTradingPairTradingRules((int)$existing['id'], $rules);
                        $pairsUpdated++;
                        $stagingStatus = 'already_exists';
                        $newPairId = (int)$existing['id'];
                    }
                }

                $this->repo->insertImportedPairStaging($jobId, $providerId, [
                    'external_base_symbol' => $baseCode,
                    'external_quote_symbol' => $quoteCode,
                    'external_pair_id' => (string)$symbol['symbol'],
                    'suggested_symbol' => TradingPair::toInternalSymbol($baseCode, $quoteCode),
                    'raw_payload' => json_encode($symbol, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'status' => $stagingStatus,
                ]);
            }

            $elapsed = (int)round((microtime(true) - $start) * 1000);
            $this->repo->completePairImportJob($jobId, [
                'status' => 'completed',
                'pairs_found' => $pairsFound,
                'pairs_created' => $pairsCreated,
                'pairs_updated' => $pairsUpdated,
                'pairs_skipped' => $pairsSkipped,
            ]);
            $this->repo->insertSyncLog($providerId, null, 'sync_success', [
                'response_time_ms' => $elapsed,
                'message' => "Binance exchange sync: found={$pairsFound}, created={$pairsCreated}, updated={$pairsUpdated}, skipped={$pairsSkipped}",
            ]);
            $this->repo->updateProviderHealth($providerId, 'healthy');
            $this->mgmt->logAdminAction(
                $adminId,
                'sync_binance_exchange_info',
                'pair_import_jobs',
                (string)$jobId,
                null,
                compact('pairsFound', 'pairsCreated', 'pairsUpdated', 'pairsSkipped'),
                RequestContext::ipAddress()
            );
        } catch (\Throwable $e) {
            $this->repo->completePairImportJob($jobId, [
                'status' => 'failed',
                'pairs_found' => $pairsFound,
                'pairs_created' => $pairsCreated,
                'pairs_updated' => $pairsUpdated,
                'pairs_skipped' => $pairsSkipped,
                'error_message' => $e->getMessage(),
            ]);
            $this->repo->insertSyncLog($providerId, null, 'sync_failed', [
                'message' => $e->getMessage(),
            ]);
            $this->repo->updateProviderHealth($providerId, 'down');
            throw $e;
        }

        return compact('jobId', 'pairsFound', 'pairsCreated', 'pairsUpdated', 'pairsSkipped');
    }

    public function syncTickers(int $adminId): array
    {
        $provider = $this->requireBinanceProvider();
        $providerId = (int)$provider['id'];
        $start = microtime(true);
        try {
            $stats = (new TickerSyncService($this->repo, $this->binance))->syncProviderPairs($providerId);
            $elapsed = (int)round((microtime(true) - $start) * 1000);
            $this->repo->insertSyncLog($providerId, null, 'sync_success', [
                'response_time_ms' => $elapsed,
                'message' => "Ticker sync updated {$stats['updated']} / {$stats['processed']} pairs",
            ]);
            $this->repo->updateProviderHealth($providerId, $stats['missing'] > 0 ? 'degraded' : 'healthy');
            $this->mgmt->logAdminAction(
                $adminId,
                'sync_binance_tickers',
                'price_tickers',
                (string)$providerId,
                null,
                $stats,
                RequestContext::ipAddress()
            );
            return $stats;
        } catch (\Throwable $e) {
            $this->repo->insertSyncLog($providerId, null, 'sync_failed', ['message' => $e->getMessage()]);
            $this->repo->updateProviderHealth($providerId, 'down');
            throw $e;
        }
    }

    public function syncCandles(int $adminId, string $interval = '1h', int $limit = 300): array
    {
        $provider = $this->requireBinanceProvider();
        $providerId = (int)$provider['id'];
        $start = microtime(true);
        try {
            $stats = (new CandleSyncService($this->repo, $this->binance))->syncProviderCandles($providerId, $interval, $limit);
            $elapsed = (int)round((microtime(true) - $start) * 1000);
            $this->repo->insertSyncLog($providerId, null, 'sync_success', [
                'response_time_ms' => $elapsed,
                'message' => "Candle sync {$interval}: {$stats['candles']} candles for {$stats['pairs']} pairs",
            ]);
            $this->repo->updateProviderHealth($providerId, $stats['errors'] > 0 ? 'degraded' : 'healthy');
            $this->mgmt->logAdminAction(
                $adminId,
                'sync_binance_candles',
                'candlesticks',
                (string)$providerId,
                null,
                ['interval' => $interval, 'limit' => $limit] + $stats,
                RequestContext::ipAddress()
            );
            return $stats;
        } catch (\Throwable $e) {
            $this->repo->insertSyncLog($providerId, null, 'sync_failed', ['message' => $e->getMessage()]);
            $this->repo->updateProviderHealth($providerId, 'down');
            throw $e;
        }
    }

    public function rawExchangeInfoSample(int $limit = 20): array
    {
        $rows = [];
        foreach ($this->binance->exchangeInfo() as $symbol) {
            if (!is_array($symbol)) {
                continue;
            }
            $rows[] = [
                'symbol' => (string)($symbol['symbol'] ?? ''),
                'status' => (string)($symbol['status'] ?? ''),
                'base' => (string)($symbol['baseAsset'] ?? ''),
                'quote' => (string)($symbol['quoteAsset'] ?? ''),
            ];
            if (count($rows) >= $limit) {
                break;
            }
        }
        return $rows;
    }

    private function requireBinanceProvider(): array
    {
        $provider = $this->repo->findProviderByCode(Exchange::BINANCE);
        if ($provider === null) {
            throw new RuntimeException('Binance provider not found. Create a provider with code "binance" first.');
        }
        if (!(int)($provider['is_active'] ?? 0)) {
            throw new RuntimeException('Binance provider is disabled.');
        }
        return $provider;
    }

    private function extractTradingRules(array $symbol): array
    {
        $minOrderSize = '0';
        $maxOrderSize = null;
        $minNotional = '0';
        $pricePrecision = isset($symbol['quotePrecision']) ? max(0, (int)$symbol['quotePrecision']) : self::DEFAULT_PRECISION;
        $quantityPrecision = isset($symbol['baseAssetPrecision']) ? max(0, (int)$symbol['baseAssetPrecision']) : self::DEFAULT_PRECISION;

        foreach ((array)($symbol['filters'] ?? []) as $filter) {
            if (!is_array($filter)) {
                continue;
            }
            $type = (string)($filter['filterType'] ?? '');
            if ($type === 'LOT_SIZE') {
                $minOrderSize = (string)($filter['minQty'] ?? $minOrderSize);
                $maxOrderSize = isset($filter['maxQty']) ? (string)$filter['maxQty'] : $maxOrderSize;
            } elseif ($type === 'MIN_NOTIONAL') {
                $minNotional = (string)($filter['minNotional'] ?? $minNotional);
            } elseif ($type === 'PRICE_FILTER' && isset($filter['tickSize'])) {
                $pricePrecision = $this->precisionFromStep((string)$filter['tickSize']);
            }
        }

        return [
            'min_order_size' => $minOrderSize,
            'max_order_size' => $maxOrderSize,
            'min_notional' => $minNotional,
            'price_precision' => $pricePrecision,
            'quantity_precision' => $quantityPrecision,
        ];
    }

    private function precisionFromStep(string $step): int
    {
        $step = trim($step);
        if ($step === '' || str_contains($step, 'E') || str_contains($step, 'e')) {
            return self::DEFAULT_PRECISION;
        }
        $parts = explode('.', $step, 2);
        if (count($parts) < 2) {
            return 0;
        }
        $fraction = rtrim($parts[1], '0');
        return strlen($fraction);
    }
}
