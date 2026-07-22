<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\RequestContext;
use App\Repositories\MarketsRepository;
use App\Repositories\AdminManagementRepository;
use InvalidArgumentException;

/**
 * Markets Service
 *
 * Business logic for the complete Markets, Assets and Trading Pairs module:
 *  - Admin market overview, statistics, and management
 *  - Price data provider management with audit logging
 *  - Price feed subscription management
 *  - Sync log monitoring
 *  - User market interface (pair listing, watchlist, market detail)
 *  - Ticker data for AJAX endpoints
 */
final class MarketsService
{
    public function __construct(
        private readonly MarketsRepository     $repo     = new MarketsRepository(),
        private readonly AdminManagementRepository $mgmt = new AdminManagementRepository(),
    ) {}

    // =========================================================================
    // ADMIN — MARKET OVERVIEW DASHBOARD
    // =========================================================================

    public function adminMarketOverview(): array
    {
        $stats      = $this->repo->getMarketStats();
        $topPairs   = $this->repo->getTopPairsByVolume(15);
        $gainers    = $this->repo->getTopGainers(5);
        $losers     = $this->repo->getTopLosers(5);
        $volChart   = $this->repo->getDailyVolumeChart(30);
        $typeVols   = $this->repo->getMarketTypeVolumes();
        $providers  = $this->repo->listProviders();
        $syncStats  = $this->repo->getSyncLogStats();

        return compact('stats', 'topPairs', 'gainers', 'losers', 'volChart', 'typeVols', 'providers', 'syncStats');
    }

    // =========================================================================
    // ADMIN — TRADING PAIR LISTING WITH TICKERS
    // =========================================================================

    public function adminPairsIndex(array $filters): array
    {
        return [
            'pairs'    => $this->repo->listPairsWithTickers($filters),
            'filters'  => $filters,
            'quotes'   => $this->repo->getDistinctQuoteCurrencies(),
        ];
    }

    public function adminPairDetail(int $pairId): array
    {
        $pair = $this->repo->getPairDetail($pairId);
        if ($pair === null) {
            throw new InvalidArgumentException('Trading pair not found.');
        }

        $subscription = $this->repo->findFeedSubscription($pairId);
        $providers    = $this->repo->listProviders();
        $recentTrades = $this->repo->getRecentTradesForPair($pairId, 30);
        $candles      = $this->repo->getCandlesticksForPair($pairId, '1h', 24);

        return compact('pair', 'subscription', 'providers', 'recentTrades', 'candles');
    }

    // =========================================================================
    // ADMIN — MARKET STATISTICS
    // =========================================================================

    public function adminMarketStatistics(): array
    {
        $stats      = $this->repo->getMarketStats();
        $topPairs   = $this->repo->getTopPairsByVolume(20);
        $gainers    = $this->repo->getTopGainers(10);
        $losers     = $this->repo->getTopLosers(10);
        $volChart   = $this->repo->getDailyVolumeChart(30);
        $typeVols   = $this->repo->getMarketTypeVolumes();

        return compact('stats', 'topPairs', 'gainers', 'losers', 'volChart', 'typeVols');
    }

    // =========================================================================
    // ADMIN — PRICE DATA PROVIDERS
    // =========================================================================

    public function adminProvidersIndex(): array
    {
        return [
            'providers' => $this->repo->listProviders(),
        ];
    }

    public function createProvider(int $adminId, array $payload): int
    {
        $name = trim((string)($payload['name'] ?? ''));
        $code = strtolower(trim((string)($payload['provider_code'] ?? '')));

        if ($name === '' || $code === '') {
            throw new InvalidArgumentException('Provider name and code are required.');
        }

        if (!preg_match('/^[a-z0-9_]{2,30}$/', $code)) {
            throw new InvalidArgumentException('Provider code must be 2-30 lowercase alphanumeric characters or underscores.');
        }

        $payload['created_by'] = $adminId;
        $id = $this->repo->createProvider($payload);

        $this->mgmt->logAdminAction(
            $adminId, 'create_price_provider', 'price_data_providers',
            (string)$id, null, ['name' => $name, 'code' => $code],
            RequestContext::ipAddress()
        );

        return $id;
    }

    public function updateProvider(int $adminId, int $providerId, array $payload): void
    {
        $provider = $this->repo->findProviderById($providerId);
        if ($provider === null) {
            throw new InvalidArgumentException('Provider not found.');
        }

        $this->repo->updateProvider($providerId, $payload);

        $this->mgmt->logAdminAction(
            $adminId, 'update_price_provider', 'price_data_providers',
            (string)$providerId, null, ['name' => $payload['name'] ?? ''],
            RequestContext::ipAddress()
        );
    }

    public function toggleProvider(int $adminId, int $providerId, bool $active): void
    {
        $provider = $this->repo->findProviderById($providerId);
        if ($provider === null) {
            throw new InvalidArgumentException('Provider not found.');
        }

        $this->repo->toggleProvider($providerId, $active);

        $this->mgmt->logAdminAction(
            $adminId,
            $active ? 'enable_price_provider' : 'disable_price_provider',
            'price_data_providers',
            (string)$providerId, null, ['name' => $provider['name']],
            RequestContext::ipAddress()
        );
    }

    // =========================================================================
    // ADMIN — PRICE FEED SUBSCRIPTIONS
    // =========================================================================

    public function adminFeedIndex(array $filters): array
    {
        return [
            'subscriptions' => $this->repo->listFeedSubscriptions($filters),
            'providers'     => $this->repo->listProviders(),
            'filters'       => $filters,
        ];
    }

    public function saveFeedSubscription(int $adminId, int $pairId, array $payload): void
    {
        $primary = (int)($payload['primary_provider_id'] ?? 0);
        if ($primary <= 0) {
            throw new InvalidArgumentException('A primary price provider is required.');
        }

        $this->repo->upsertFeedSubscription($pairId, $payload);

        $this->mgmt->logAdminAction(
            $adminId, 'update_price_feed', 'price_feed_subscriptions',
            (string)$pairId, null, ['pair_id' => $pairId],
            RequestContext::ipAddress()
        );
    }

    public function toggleFeedSubscription(int $adminId, int $pairId, bool $active): void
    {
        $this->repo->toggleFeedSubscription($pairId, $active);

        $this->mgmt->logAdminAction(
            $adminId,
            $active ? 'enable_price_feed' : 'disable_price_feed',
            'price_feed_subscriptions',
            (string)$pairId, null, [],
            RequestContext::ipAddress()
        );
    }

    // =========================================================================
    // ADMIN — PRICE SYNC LOGS
    // =========================================================================

    public function adminSyncLogsIndex(array $filters): array
    {
        return [
            'logs'      => $this->repo->listSyncLogs($filters, 200),
            'stats'     => $this->repo->getSyncLogStats(),
            'providers' => $this->repo->listProviders(),
            'filters'   => $filters,
        ];
    }

    // =========================================================================
    // ADMIN — ASSET MAPPINGS
    // =========================================================================

    public function adminAssetMappings(int $providerId): array
    {
        $provider = $this->repo->findProviderById($providerId);
        if ($provider === null) {
            throw new InvalidArgumentException('Provider not found.');
        }

        return [
            'provider' => $provider,
            'mappings' => $this->repo->listAssetMappings($providerId),
        ];
    }

    public function saveAssetMapping(int $adminId, int $providerId, int $currencyId, array $payload): void
    {
        if ($providerId <= 0 || $currencyId <= 0) {
            throw new InvalidArgumentException('Provider and currency are required.');
        }

        $this->repo->upsertAssetMapping($providerId, $currencyId, $payload);

        $this->mgmt->logAdminAction(
            $adminId, 'update_asset_mapping', 'provider_asset_mappings',
            "{$providerId}:{$currencyId}", null, [],
            RequestContext::ipAddress()
        );
    }

    public function deleteAssetMapping(int $adminId, int $mappingId): void
    {
        $this->repo->deleteAssetMapping($mappingId);

        $this->mgmt->logAdminAction(
            $adminId, 'delete_asset_mapping', 'provider_asset_mappings',
            (string)$mappingId, null, [],
            RequestContext::ipAddress()
        );
    }

    // =========================================================================
    // USER — MARKETS PAGE
    // =========================================================================

    public function userMarketsIndex(int $userId, array $filters): array
    {
        $pairs         = $this->repo->listPairsWithTickers($filters, 200);
        $quotes        = $this->repo->getDistinctQuoteCurrencies();
        $watchlistIds  = $this->repo->getWatchlistPairIds($userId);
        $topGainers    = $this->repo->getTopGainers(5);
        $topLosers     = $this->repo->getTopLosers(5);
        $topByVolume   = $this->repo->getTopPairsByVolume(5);

        // Attach watchlist flag to each pair
        foreach ($pairs as &$pair) {
            $pair['in_watchlist'] = in_array((int)$pair['id'], $watchlistIds, true);
        }
        unset($pair);

        return compact('pairs', 'quotes', 'watchlistIds', 'topGainers', 'topLosers', 'topByVolume', 'filters');
    }

    // =========================================================================
    // USER — WATCHLIST
    // =========================================================================

    public function userWatchlist(int $userId): array
    {
        return [
            'watchlist'   => $this->repo->getUserWatchlist($userId),
            'quotes'      => $this->repo->getDistinctQuoteCurrencies(),
        ];
    }

    public function toggleWatchlist(int $userId, int $pairId): array
    {
        if ($pairId <= 0) {
            throw new InvalidArgumentException('Invalid trading pair.');
        }

        $pair = $this->repo->getPairDetail($pairId);
        if ($pair === null || !(int)$pair['is_active']) {
            throw new InvalidArgumentException('Trading pair not found or not active.');
        }

        $already = $this->repo->isInWatchlist($userId, $pairId);

        if ($already) {
            $this->repo->removeFromWatchlist($userId, $pairId);
            return ['in_watchlist' => false, 'message' => $pair['symbol'] . ' removed from watchlist.'];
        } else {
            $this->repo->addToWatchlist($userId, $pairId);
            return ['in_watchlist' => true, 'message' => $pair['symbol'] . ' added to watchlist.'];
        }
    }

    // =========================================================================
    // USER — MARKET DETAIL
    // =========================================================================

    public function userMarketDetail(int $userId, int $pairId): array
    {
        $pair = $this->repo->getPairDetail($pairId);
        if ($pair === null || !(int)$pair['is_active']) {
            throw new InvalidArgumentException('Market not found or not active.');
        }

        $inWatchlist  = $this->repo->isInWatchlist($userId, $pairId);
        $recentTrades = $this->repo->getRecentTradesForPair($pairId, 30);
        $candles1h    = $this->repo->getCandlesticksForPair($pairId, '1h', 48);
        $candles1d    = $this->repo->getCandlesticksForPair($pairId, '1d', 30);

        return compact('pair', 'inWatchlist', 'recentTrades', 'candles1h', 'candles1d');
    }

    // =========================================================================
    // AJAX — TICKER DATA
    // =========================================================================

    public function getTickersJson(string $quoteFilter = ''): array
    {
        $filters = [];
        if ($quoteFilter !== '') {
            $filters['quote'] = $quoteFilter;
        }
        return $this->repo->listPairsWithTickers($filters, 200);
    }

    public function getSingleTickerJson(int $pairId): array
    {
        $pair = $this->repo->getPairDetail($pairId);
        if ($pair === null) {
            throw new InvalidArgumentException('Pair not found.');
        }
        return $pair;
    }
}
