<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Libraries\Csrf;
use App\Libraries\Request;
use App\Libraries\Response;
use App\Libraries\Session;
use App\Services\TradingEngineService;
use Throwable;

/**
 * Admin Trading Engine Controller
 *
 * Provides the admin interface for monitoring and managing the trading engine:
 *  - Real-time engine statistics dashboard
 *  - Risk monitoring (positions near liquidation)
 *  - Force-liquidation of positions
 *  - Fee tier management (create, update, toggle active)
 *  - All open positions overview
 */
final class TradingEngineController extends AdminBaseController
{
    // =========================================================================
    // ADMIN DASHBOARD
    // =========================================================================

    /**
     * Trading engine overview dashboard.
     * Route: GET /admin/trading-engine
     */
    public function index(Request $request): void
    {
        $this->bootAdmin();

        $service = new TradingEngineService();

        try {
            $engineStats   = $service->getEngineStats();
            $volumeChart   = $service->getDailyVolumeChart(30);
            $topPairs      = $service->getTopPairs();
            $feeTiers      = $service->getFeeTiers();
            $recentFilters = ['status' => 'open'];
            $openPositions = $service->getAllOpenPositions($recentFilters);
        } catch (Throwable) {
            $engineStats   = [];
            $volumeChart   = [];
            $topPairs      = [];
            $feeTiers      = [];
            $openPositions = [];
        }

        $this->view('admin/trading-engine/index', [
            'title'        => 'Trading Engine',
            'adminSection' => 'trading-engine',
            'username'     => $this->adminUsername(),
            'engineStats'  => $engineStats,
            'volumeChart'  => $volumeChart,
            'topPairs'     => $topPairs,
            'feeTiers'     => $feeTiers,
            'openPositions'=> $openPositions,
        ]);
    }

    // =========================================================================
    // RISK MONITOR
    // =========================================================================

    /**
     * Risk monitoring: positions near liquidation threshold.
     * Route: GET /admin/trading-engine/risk
     */
    public function risk(Request $request): void
    {
        $this->bootAdmin();

        $service = new TradingEngineService();

        try {
            $candidates    = $service->getLiquidationCandidates();
            $engineStats   = $service->getEngineStats();
            $allPositions  = $service->getAllOpenPositions([
                'status' => (string)$request->input('status', 'open'),
            ]);
        } catch (Throwable) {
            $candidates   = [];
            $engineStats  = [];
            $allPositions = [];
        }

        $this->view('admin/trading-engine/risk', [
            'title'        => 'Risk Monitor',
            'adminSection' => 'trading-engine',
            'username'     => $this->adminUsername(),
            'candidates'   => $candidates,
            'engineStats'  => $engineStats,
            'allPositions' => $allPositions,
            'csrfToken'    => Csrf::token(),
        ]);
    }

    /**
     * Force-liquidate a specific position.
     * Route: POST /admin/trading-engine/liquidate
     */
    public function forceLiquidate(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);

        $adminId    = $this->adminId();
        $positionId = (int)$request->input('position_id');
        $price      = trim((string)$request->input('price', ''));

        if ($positionId <= 0) {
            Response::json(['ok' => false, 'message' => 'Invalid position ID'], 422);
        }
        if ($price === '' || bccomp($price, '0', 18) <= 0) {
            Response::json(['ok' => false, 'message' => 'Valid liquidation price is required'], 422);
        }

        try {
            (new TradingEngineService())->forceLiquidate($adminId, $positionId, $price);
            Response::json(['ok' => true, 'message' => "Position #{$positionId} force-liquidated at {$price}"]);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // =========================================================================
    // FEE TIERS
    // =========================================================================

    /**
     * Fee tier management page.
     * Route: GET /admin/trading-engine/fee-tiers
     */
    public function feeTiers(Request $request): void
    {
        $this->bootAdmin();

        try {
            $feeTiers = (new TradingEngineService())->getFeeTiers();
        } catch (Throwable) {
            $feeTiers = [];
        }

        $this->view('admin/trading-engine/fee-tiers', [
            'title'        => 'Fee Tiers',
            'adminSection' => 'trading-engine',
            'username'     => $this->adminUsername(),
            'feeTiers'     => $feeTiers,
            'csrfToken'    => Csrf::token(),
        ]);
    }

    /**
     * Create a new fee tier.
     * Route: POST /admin/trading-engine/fee-tiers/create
     */
    public function createFeeTier(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);

        $adminId = $this->adminId();
        $data    = [
            'tier_name'                      => trim((string)$request->input('tier_name', '')),
            'min_30d_volume'                 => trim((string)$request->input('min_30d_volume', '0')),
            'min_token_holding'              => trim((string)$request->input('min_token_holding', '0')),
            'maker_fee_percent'              => trim((string)$request->input('maker_fee_percent', '0.1')),
            'taker_fee_percent'              => trim((string)$request->input('taker_fee_percent', '0.15')),
            'withdrawal_fee_discount_percent'=> trim((string)$request->input('withdrawal_fee_discount_percent', '0')),
            'is_active'                      => (int)$request->input('is_active', 1),
        ];

        try {
            $id = (new TradingEngineService())->createFeeTier($adminId, $data);
            Response::json(['ok' => true, 'id' => $id, 'message' => 'Fee tier created successfully']);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Update an existing fee tier.
     * Route: POST /admin/trading-engine/fee-tiers/update
     */
    public function updateFeeTier(Request $request): void
    {
        $this->bootAdmin();
        $this->requireCsrf($request);

        $adminId = $this->adminId();
        $tierId  = (int)$request->input('tier_id');

        if ($tierId <= 0) {
            Response::json(['ok' => false, 'message' => 'Invalid tier ID'], 422);
        }

        $data = [
            'tier_name'                      => trim((string)$request->input('tier_name', '')),
            'min_30d_volume'                 => trim((string)$request->input('min_30d_volume', '0')),
            'min_token_holding'              => trim((string)$request->input('min_token_holding', '0')),
            'maker_fee_percent'              => trim((string)$request->input('maker_fee_percent', '0.1')),
            'taker_fee_percent'              => trim((string)$request->input('taker_fee_percent', '0.15')),
            'withdrawal_fee_discount_percent'=> trim((string)$request->input('withdrawal_fee_discount_percent', '0')),
            'is_active'                      => (int)$request->input('is_active', 1),
        ];

        try {
            (new TradingEngineService())->updateFeeTier($adminId, $tierId, $data);
            Response::json(['ok' => true, 'message' => 'Fee tier updated successfully']);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // =========================================================================
    // POSITIONS OVERVIEW
    // =========================================================================

    /**
     * All open positions with filters.
     * Route: GET /admin/trading-engine/positions
     */
    public function positions(Request $request): void
    {
        $this->bootAdmin();

        $filters = [
            'status' => (string)$request->input('status', 'open'),
            'symbol' => trim((string)$request->input('symbol', '')),
        ];

        try {
            $positions   = (new TradingEngineService())->getAllOpenPositions($filters);
            $engineStats = (new TradingEngineService())->getEngineStats();
        } catch (Throwable) {
            $positions   = [];
            $engineStats = [];
        }

        $this->view('admin/trading-engine/positions', [
            'title'        => 'Open Positions',
            'adminSection' => 'trading-engine',
            'username'     => $this->adminUsername(),
            'positions'    => $positions,
            'engineStats'  => $engineStats,
            'filters'      => $filters,
            'csrfToken'    => Csrf::token(),
        ]);
    }
}
