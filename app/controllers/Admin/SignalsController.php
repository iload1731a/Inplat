<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Libraries\Request;
use App\Libraries\Response;
use App\Middleware\AuthMiddleware;
use App\Services\SignalsService;
use Throwable;

/**
 * Admin Signals Controller
 *
 * Routes:
 *   GET  /admin/signals                     — Dashboard: KPIs, daily chart, top providers, activity
 *   GET  /admin/signals/list                — Signals list with filters + status update
 *   POST /admin/signals/create              — Create a new signal
 *   POST /admin/signals/status              — Update signal status (hit_tp/hit_sl/cancel)
 *   POST /admin/signals/delete              — Delete a signal
 *   GET  /admin/signals/providers           — Provider list + performance
 *   POST /admin/signals/providers/create    — Create provider
 *   POST /admin/signals/providers/update    — Update provider
 *   POST /admin/signals/providers/toggle    — Toggle active/inactive
 *   POST /admin/signals/providers/recalc    — Recalculate all provider performance
 *   GET  /admin/signals/alerts              — Active platform alerts overview
 *   GET  /admin/signals/performance         — Provider performance reports
 */
final class SignalsController extends AdminBaseController
{
    private SignalsService $service;

    public function __construct()
    {
        $this->service = new SignalsService();
    }

    // =========================================================================
    // DASHBOARD
    // =========================================================================

    public function index(Request $request): void
    {
        AuthMiddleware::ensureAdmin();

        try {
            $data = $this->service->getAdminDashboard();
        } catch (Throwable $e) {
            $data = ['error' => $e->getMessage(), 'kpis' => [], 'daily' => []];
        }

        $this->render('admin/signals/index', array_merge($data, [
            'title'        => 'Signals & Alerts',
            'adminSection' => 'signals',
            'username'     => $this->adminUsername(),
        ]));
    }

    // =========================================================================
    // SIGNAL LIST + CREATE + STATUS + DELETE
    // =========================================================================

    public function list(Request $request): void
    {
        AuthMiddleware::ensureAdmin();

        $filters = [
            'provider_id' => (int)($request->input('provider_id') ?? 0) ?: null,
            'status'      => $request->input('status') ?? '',
            'signal_type' => $request->input('signal_type') ?? '',
            'pair_symbol' => $request->input('pair_symbol') ?? '',
        ];
        $page = max(1, (int)($request->input('page') ?? 1));

        try {
            $data = $this->service->getAdminSignalsList(array_filter($filters), $page);
        } catch (Throwable $e) {
            $data = ['error' => $e->getMessage(), 'signals' => [], 'pairs' => []];
        }

        $providers = [];
        try {
            $pp = $this->service->getAdminProvidersPage();
            $providers = $pp['providers'] ?? [];
        } catch (Throwable) {}

        $this->render('admin/signals/list', array_merge($data, [
            'title'        => 'All Signals',
            'adminSection' => 'signals',
            'username'     => $this->adminUsername(),
            'providers'    => $providers,
            'filters'      => $filters,
            'page'         => $page,
        ]));
    }

    public function create(Request $request): void
    {
        AuthMiddleware::ensureAdmin();
        $this->requireCsrf($request);

        $input = [
            'provider_id'      => (int)($request->input('provider_id') ?? 0),
            'trading_pair_id'  => (int)($request->input('trading_pair_id') ?? 0) ?: null,
            'pair_symbol'      => strtoupper(trim((string)($request->input('pair_symbol') ?? ''))),
            'signal_type'      => $request->input('signal_type'),
            'market_type'      => $request->input('market_type') ?? 'spot',
            'timeframe'        => $request->input('timeframe') ?? '1h',
            'entry_price'      => $request->input('entry_price') ?? null,
            'entry_price_high' => $request->input('entry_price_high') ?? null,
            'entry_price_low'  => $request->input('entry_price_low') ?? null,
            'take_profit_1'    => $request->input('take_profit_1') ?? null,
            'take_profit_2'    => $request->input('take_profit_2') ?? null,
            'take_profit_3'    => $request->input('take_profit_3') ?? null,
            'stop_loss'        => $request->input('stop_loss') ?? null,
            'leverage'         => (int)($request->input('leverage') ?? 0) ?: null,
            'risk_reward_ratio'=> $request->input('risk_reward_ratio') ?? null,
            'confidence_score' => (int)($request->input('confidence_score') ?? 0) ?: null,
            'analysis_text'    => $request->input('analysis_text') ?? null,
            'tags'             => $request->input('tags') ? explode(',', (string)$request->input('tags')) : null,
            'expires_at'       => $request->input('expires_at') ?? null,
        ];

        Response::json($this->service->adminCreateSignal($input));
    }

    public function updateStatus(Request $request): void
    {
        AuthMiddleware::ensureAdmin();
        $this->requireCsrf($request);
        $id        = (int)($request->input('signal_id') ?? 0);
        $status    = (string)($request->input('status') ?? '');
        $profitPct = $request->input('profit_pct') !== null ? (float)$request->input('profit_pct') : null;
        Response::json($this->service->adminUpdateSignalStatus($id, $status, $profitPct));
    }

    public function deleteSignal(Request $request): void
    {
        AuthMiddleware::ensureAdmin();
        $this->requireCsrf($request);
        $id = (int)($request->input('signal_id') ?? 0);
        Response::json($this->service->adminDeleteSignal($id));
    }

    // =========================================================================
    // PROVIDERS
    // =========================================================================

    public function providers(Request $request): void
    {
        AuthMiddleware::ensureAdmin();

        try {
            $data = $this->service->getAdminProvidersPage();
        } catch (Throwable $e) {
            $data = ['error' => $e->getMessage(), 'providers' => [], 'performance' => []];
        }

        $this->render('admin/signals/providers', array_merge($data, [
            'title'        => 'Signal Providers',
            'adminSection' => 'signals',
            'username'     => $this->adminUsername(),
        ]));
    }

    public function createProvider(Request $request): void
    {
        AuthMiddleware::ensureAdmin();
        $this->requireCsrf($request);

        $input = [
            'name'                  => trim((string)($request->input('name') ?? '')),
            'description'           => trim((string)($request->input('description') ?? '')),
            'provider_type'         => $request->input('provider_type') ?? 'internal',
            'logo_url'              => trim((string)($request->input('logo_url') ?? '')),
            'website_url'           => trim((string)($request->input('website_url') ?? '')),
            'is_active'             => !empty($request->input('is_active')) ? 1 : 0,
            'is_public'             => !empty($request->input('is_public')) ? 1 : 1,
            'subscription_price'    => (float)($request->input('subscription_price') ?? 0),
            'subscription_currency' => $request->input('subscription_currency') ?? null,
        ];
        Response::json($this->service->adminCreateProvider($input, $this->adminId()));
    }

    public function updateProvider(Request $request): void
    {
        AuthMiddleware::ensureAdmin();
        $this->requireCsrf($request);

        $id    = (int)($request->input('provider_id') ?? 0);
        $input = [
            'name'                  => trim((string)($request->input('name') ?? '')),
            'description'           => trim((string)($request->input('description') ?? '')),
            'provider_type'         => $request->input('provider_type') ?? 'internal',
            'logo_url'              => trim((string)($request->input('logo_url') ?? '')),
            'website_url'           => trim((string)($request->input('website_url') ?? '')),
            'is_active'             => !empty($request->input('is_active')) ? 1 : 0,
            'is_public'             => !empty($request->input('is_public')) ? 1 : 0,
            'subscription_price'    => (float)($request->input('subscription_price') ?? 0),
            'subscription_currency' => $request->input('subscription_currency') ?? null,
        ];
        Response::json($this->service->adminUpdateProvider($id, $input));
    }

    public function toggleProvider(Request $request): void
    {
        AuthMiddleware::ensureAdmin();
        $this->requireCsrf($request);
        $id = (int)($request->input('provider_id') ?? 0);
        Response::json($this->service->adminToggleProvider($id));
    }

    public function recalcPerformance(Request $request): void
    {
        AuthMiddleware::ensureAdmin();
        $this->requireCsrf($request);
        try {
            $this->service->recalculateAllPerformance();
            Response::json(['ok' => true, 'message' => 'Performance recalculated for all providers']);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    // =========================================================================
    // ALERTS OVERVIEW
    // =========================================================================

    public function alerts(Request $request): void
    {
        AuthMiddleware::ensureAdmin();

        try {
            $data = $this->service->getAdminAlertsPage();
        } catch (Throwable $e) {
            $data = ['error' => $e->getMessage(), 'stats' => [], 'active_alerts' => []];
        }

        $this->render('admin/signals/alerts', array_merge($data, [
            'title'        => 'Price Alerts Monitor',
            'adminSection' => 'signals',
            'username'     => $this->adminUsername(),
        ]));
    }

    // =========================================================================
    // PERFORMANCE REPORTS
    // =========================================================================

    public function performance(Request $request): void
    {
        AuthMiddleware::ensureAdmin();

        try {
            $data = $this->service->getAdminPerformancePage();
        } catch (Throwable $e) {
            $data = ['error' => $e->getMessage(), 'providers' => [], 'daily' => []];
        }

        $this->render('admin/signals/performance', array_merge($data, [
            'title'        => 'Signal Performance Reports',
            'adminSection' => 'signals',
            'username'     => $this->adminUsername(),
        ]));
    }
}
