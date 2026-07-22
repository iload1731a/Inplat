<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Libraries\Request;
use App\Libraries\Response;
use App\Services\MarketsService;
use Throwable;

/**
 * Admin Markets Controller
 *
 * Routes:
 *   GET  /admin/markets              — market overview dashboard
 *   GET  /admin/markets/pairs        — all trading pairs + tickers
 *   GET  /admin/markets/pair/detail  — single pair detail + feed config
 *   GET  /admin/markets/statistics   — market statistics + charts
 *   GET  /admin/markets/providers    — price data providers
 *   POST /admin/markets/providers/create
 *   POST /admin/markets/providers/update
 *   POST /admin/markets/providers/toggle
 *   GET  /admin/markets/feed         — price feed subscriptions
 *   POST /admin/markets/feed/save    — upsert feed subscription
 *   POST /admin/markets/feed/toggle  — enable/disable subscription
 *   GET  /admin/markets/sync-logs    — price sync log viewer
 *   GET  /admin/markets/mappings     — provider asset mappings
 *   POST /admin/markets/mappings/save
 *   POST /admin/markets/mappings/delete
 */
final class MarketsController extends AdminBaseController
{
    private function svc(): MarketsService
    {
        return new MarketsService();
    }

    // =========================================================================
    // MARKET OVERVIEW DASHBOARD
    // =========================================================================

    public function index(Request $request): void
    {
        $this->bootAdmin();
        $data = $this->svc()->adminMarketOverview();
        $this->view('admin/markets/index', [
            'title'        => 'Admin · Markets Overview',
            'username'     => $this->adminUsername(),
            'adminSection' => 'markets',
            ...$data,
        ]);
    }

    // =========================================================================
    // ALL TRADING PAIRS WITH LIVE TICKERS
    // =========================================================================

    public function pairs(Request $request): void
    {
        $this->bootAdmin();
        $filters = [
            'search'      => trim((string)$request->input('search', '')),
            'market_type' => trim((string)$request->input('market_type', '')),
            'is_active'   => $request->input('is_active', ''),
            'quote'       => trim((string)$request->input('quote', '')),
            'sort'        => trim((string)$request->input('sort', '')),
        ];
        $data = $this->svc()->adminPairsIndex($filters);
        $this->view('admin/markets/pairs', [
            'title'        => 'Admin · Trading Pairs',
            'username'     => $this->adminUsername(),
            'adminSection' => 'markets',
            ...$data,
        ]);
    }

    // =========================================================================
    // SINGLE PAIR DETAIL
    // =========================================================================

    public function pairDetail(Request $request): void
    {
        $this->bootAdmin();
        $pairId = (int)$request->input('id', 0);
        try {
            $data = $this->svc()->adminPairDetail($pairId);
        } catch (Throwable $e) {
            $this->view('admin/markets/pair-detail', [
                'title'        => 'Admin · Pair Detail',
                'username'     => $this->adminUsername(),
                'adminSection' => 'markets',
                'error'        => $e->getMessage(),
                'pair'         => null,
                'subscription' => null,
                'providers'    => [],
                'recentTrades' => [],
                'candles'      => [],
            ]);
            return;
        }
        $this->view('admin/markets/pair-detail', [
            'title'        => 'Admin · ' . ($data['pair']['symbol'] ?? 'Pair Detail'),
            'username'     => $this->adminUsername(),
            'adminSection' => 'markets',
            ...$data,
        ]);
    }

    // =========================================================================
    // MARKET STATISTICS
    // =========================================================================

    public function statistics(Request $request): void
    {
        $this->bootAdmin();
        $data = $this->svc()->adminMarketStatistics();
        $this->view('admin/markets/statistics', [
            'title'        => 'Admin · Market Statistics',
            'username'     => $this->adminUsername(),
            'adminSection' => 'markets',
            ...$data,
        ]);
    }

    // =========================================================================
    // PRICE DATA PROVIDERS
    // =========================================================================

    public function providers(Request $request): void
    {
        $this->bootAdmin();
        $data = $this->svc()->adminProvidersIndex();
        $this->view('admin/markets/providers', [
            'title'        => 'Admin · Price Data Providers',
            'username'     => $this->adminUsername(),
            'adminSection' => 'markets',
            ...$data,
        ]);
    }

    public function createProvider(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        try {
            $id = $this->svc()->createProvider($this->adminId(), $request->all());
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
            return;
        }
        Response::json(['ok' => true, 'message' => 'Provider created.', 'redirect' => '/admin/markets/providers']);
    }

    public function updateProvider(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $providerId = (int)$request->input('provider_id', 0);
        try {
            $this->svc()->updateProvider($this->adminId(), $providerId, $request->all());
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
            return;
        }
        Response::json(['ok' => true, 'message' => 'Provider updated.', 'redirect' => '/admin/markets/providers']);
    }

    public function toggleProvider(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $providerId = (int)$request->input('provider_id', 0);
        $active     = (bool)(int)$request->input('is_active', '0');
        try {
            $this->svc()->toggleProvider($this->adminId(), $providerId, $active);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
            return;
        }
        Response::json(['ok' => true, 'message' => 'Provider status updated.']);
    }

    // =========================================================================
    // PRICE FEED SUBSCRIPTIONS
    // =========================================================================

    public function feed(Request $request): void
    {
        $this->bootAdmin();
        $filters = [
            'provider_id' => (int)$request->input('provider_id', 0),
            'is_active'   => $request->input('is_active', ''),
        ];
        $data = $this->svc()->adminFeedIndex($filters);
        $this->view('admin/markets/feed', [
            'title'        => 'Admin · Price Feed Subscriptions',
            'username'     => $this->adminUsername(),
            'adminSection' => 'markets',
            ...$data,
        ]);
    }

    public function saveFeed(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $pairId = (int)$request->input('trading_pair_id', 0);
        try {
            $this->svc()->saveFeedSubscription($this->adminId(), $pairId, $request->all());
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
            return;
        }
        Response::json(['ok' => true, 'message' => 'Feed subscription saved.', 'redirect' => '/admin/markets/feed']);
    }

    public function toggleFeed(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $pairId = (int)$request->input('trading_pair_id', 0);
        $active = (bool)(int)$request->input('is_active', '0');
        try {
            $this->svc()->toggleFeedSubscription($this->adminId(), $pairId, $active);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
            return;
        }
        Response::json(['ok' => true, 'message' => 'Feed subscription updated.']);
    }

    // =========================================================================
    // PRICE SYNC LOGS
    // =========================================================================

    public function syncLogs(Request $request): void
    {
        $this->bootAdmin();
        $filters = [
            'provider_id' => (int)$request->input('provider_id', 0),
            'event_type'  => trim((string)$request->input('event_type', '')),
            'since'       => trim((string)$request->input('since', '')),
        ];
        $data = $this->svc()->adminSyncLogsIndex($filters);
        $this->view('admin/markets/sync-logs', [
            'title'        => 'Admin · Price Sync Logs',
            'username'     => $this->adminUsername(),
            'adminSection' => 'markets',
            ...$data,
        ]);
    }

    // =========================================================================
    // ASSET MAPPINGS
    // =========================================================================

    public function mappings(Request $request): void
    {
        $this->bootAdmin();
        $providerId = (int)$request->input('provider_id', 0);
        try {
            $data = $this->svc()->adminAssetMappings($providerId);
        } catch (Throwable $e) {
            $data = ['provider' => null, 'mappings' => []];
        }
        $this->view('admin/markets/mappings', [
            'title'        => 'Admin · Asset Mappings',
            'username'     => $this->adminUsername(),
            'adminSection' => 'markets',
            ...$data,
        ]);
    }

    public function saveMapping(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $providerId  = (int)$request->input('provider_id', 0);
        $currencyId  = (int)$request->input('currency_id', 0);
        try {
            $this->svc()->saveAssetMapping($this->adminId(), $providerId, $currencyId, $request->all());
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
            return;
        }
        Response::json(['ok' => true, 'message' => 'Mapping saved.', 'redirect' => '/admin/markets/mappings?provider_id=' . $providerId]);
    }

    public function deleteMapping(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);
        $mappingId  = (int)$request->input('mapping_id', 0);
        $providerId = (int)$request->input('provider_id', 0);
        try {
            $this->svc()->deleteAssetMapping($this->adminId(), $mappingId);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
            return;
        }
        Response::json(['ok' => true, 'message' => 'Mapping deleted.', 'redirect' => '/admin/markets/mappings?provider_id=' . $providerId]);
    }
}
