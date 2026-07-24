<?php

declare(strict_types=1);

namespace App\Controllers\User;

use App\Controllers\BaseController;
use App\Libraries\Csrf;
use App\Libraries\Request;
use App\Libraries\Response;
use App\Libraries\Session;
use App\Middleware\AuthMiddleware;
use App\Services\ChartService;
use Throwable;

/**
 * User Charts Controller
 *
 * Routes:
 *   GET  /charts                  — Full-screen advanced chart page
 *   GET  /charts/data             — AJAX: OHLCV + indicator data
 *   GET  /charts/volume-profile   — AJAX: volume profile for a pair
 *   GET  /charts/compare          — Multi-pair comparison chart page
 *   GET  /charts/compare/data     — AJAX: comparison series data
 *   POST /charts/preferences      — Save user chart preferences
 *   POST /charts/templates        — Save a named chart template
 *   POST /charts/templates/delete — Delete a saved template
 *   GET  /charts/templates        — List user templates (AJAX)
 */
final class ChartsController extends BaseController
{
    private ChartService $service;

    public function __construct()
    {
        $this->service = new ChartService();
    }

    // =========================================================================
    // MAIN CHART PAGE
    // =========================================================================

    /**
     * Advanced full-screen charting page.
     * Route: GET /charts
     */
    public function index(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $userId   = (int)(Session::get('auth.user_id') ?? 0);
        $symbol   = trim((string)$request->input('pair', 'BTCUSDT'));
        $interval = trim((string)$request->input('interval', '1h'));

        try {
            $data = $this->service->getChartPageData($symbol, $interval, $userId);
        } catch (Throwable $e) {
            $data = [
                'pair'      => null,
                'pairs'     => [],
                'symbol'    => strtoupper($symbol),
                'interval'  => $interval,
                'prefs'     => null,
                'templates' => [],
                'error'     => $e->getMessage(),
            ];
        }

        $this->render('user/charts/index', array_merge($data, [
            'title'       => 'Advanced Charts — ' . strtoupper($symbol),
            'userSection' => 'charts',
            'breadcrumb'  => [['label' => 'Charts']],
            'csrfToken'   => Csrf::token(),
        ]));
    }

    // =========================================================================
    // AJAX — CANDLE + INDICATOR DATA
    // =========================================================================

    /**
     * OHLCV data with optional technical indicators.
     * Route: GET /charts/data
     *
     * Query params:
     *   pair        string   Trading pair symbol
     *   interval    string   Candle interval (1m,5m,15m,30m,1h,4h,1d,1w,1M)
     *   limit       int      Max candles (50-1000, default 300)
     *   indicators  string   Comma-separated: sma_20,ema_50,rsi_14,macd,bb_20,atr_14,stoch_14,vwap
     */
    public function data(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $symbol   = trim((string)$request->input('pair', ''));
        $interval = trim((string)$request->input('interval', '1h'));
        $limit    = max(50, min(1000, (int)$request->input('limit', 300)));
        $indStr   = trim((string)$request->input('indicators', ''));
        $indicators = $indStr !== '' ? array_filter(array_map('trim', explode(',', $indStr))) : [];

        if ($symbol === '') {
            Response::json(['ok' => false, 'message' => 'pair required'], 422);
            return;
        }

        try {
            $result = $this->service->getCandleDataWithIndicators($symbol, $interval, $limit, $indicators);
            Response::json($result);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // AJAX — VOLUME PROFILE
    // =========================================================================

    /**
     * Volume profile (price level distribution) for a pair.
     * Route: GET /charts/volume-profile
     */
    public function volumeProfile(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $symbol  = trim((string)$request->input('pair', ''));
        $hours   = max(1, min(168, (int)$request->input('hours', 24)));
        $buckets = max(10, min(100, (int)$request->input('buckets', 30)));

        if ($symbol === '') {
            Response::json(['ok' => false, 'message' => 'pair required'], 422);
            return;
        }

        try {
            $result = $this->service->getVolumeProfile($symbol, $hours, $buckets);
            Response::json($result);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // COMPARISON CHART
    // =========================================================================

    /**
     * Multi-pair comparison chart page.
     * Route: GET /charts/compare
     */
    public function compare(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $selected = array_filter(array_map(
            'strtoupper',
            array_map('trim', explode(',', (string)$request->input('pairs', 'BTCUSDT,ETHUSDT')))
        ));
        $interval = trim((string)$request->input('interval', '1d'));

        try {
            $data = $this->service->getComparisonPageData(array_values($selected), $interval);
        } catch (Throwable $e) {
            $data = ['pairs' => [], 'selected' => [], 'interval' => $interval];
        }

        $this->render('user/charts/compare', array_merge($data, [
            'title'       => 'Market Comparison Charts',
            'userSection' => 'charts',
            'breadcrumb'  => [
                ['label' => 'Charts', 'url' => '/charts'],
                ['label' => 'Compare'],
            ],
            'csrfToken' => Csrf::token(),
        ]));
    }

    /**
     * AJAX: comparison series data.
     * Route: GET /charts/compare/data
     */
    public function compareData(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $pairsStr = trim((string)$request->input('pairs', ''));
        $interval = trim((string)$request->input('interval', '1d'));
        $limit    = max(20, min(500, (int)$request->input('limit', 100)));
        $symbols  = array_filter(array_map('strtoupper', array_map('trim', explode(',', $pairsStr))));

        if (count($symbols) < 2) {
            Response::json(['ok' => false, 'message' => 'At least 2 pairs required'], 422);
            return;
        }

        try {
            $result = $this->service->getComparisonData(array_values($symbols), $interval, $limit);
            Response::json($result);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // USER PREFERENCES
    // =========================================================================

    /**
     * Save chart preferences (interval, type, active indicators, layout).
     * Route: POST /charts/preferences
     */
    public function savePreferences(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        Csrf::verify($request);
        $userId  = (int)(Session::get('auth.user_id') ?? 0);
        $pairStr = trim((string)$request->input('pair', ''));
        $pairId  = null;

        if ($pairStr !== '') {
            $pairId = $this->service->getPairIdBySymbol($pairStr);
        }

        $indicators = $request->input('indicators');
        if (is_string($indicators)) {
            $indicators = json_decode($indicators, true);
        }
        $layout = $request->input('layout');
        if (is_string($layout)) {
            $layout = json_decode($layout, true);
        }

        try {
            $this->service->saveUserPreferences($userId, $pairId, [
                'interval_code' => $request->input('interval_code', '1h'),
                'chart_type'    => $request->input('chart_type', 'candlestick'),
                'indicators'    => is_array($indicators) ? $indicators : [],
                'layout'        => is_array($layout) ? $layout : [],
            ]);
            Response::json(['ok' => true]);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // CHART TEMPLATES
    // =========================================================================

    /**
     * List user templates + public templates (AJAX).
     * Route: GET /charts/templates
     */
    public function listTemplates(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $userId = (int)(Session::get('auth.user_id') ?? 0);
        try {
            $templates = $this->service->listTemplates($userId);
            Response::json(['ok' => true, 'templates' => $templates]);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Save a named chart template.
     * Route: POST /charts/templates
     */
    public function saveTemplate(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        Csrf::verify($request);
        $userId = (int)(Session::get('auth.user_id') ?? 0);

        $indicators = $request->input('indicators');
        if (is_string($indicators)) {
            $indicators = json_decode($indicators, true);
        }
        $layout = $request->input('layout');
        if (is_string($layout)) {
            $layout = json_decode($layout, true);
        }

        try {
            $result = $this->service->saveTemplate($userId, [
                'name'        => $request->input('name', 'My Template'),
                'description' => $request->input('description', ''),
                'chart_type'  => $request->input('chart_type', 'candlestick'),
                'indicators'  => is_array($indicators) ? $indicators : [],
                'layout'      => is_array($layout) ? $layout : [],
                'is_public'   => (bool)$request->input('is_public', false),
            ]);
            Response::json($result);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete a saved chart template.
     * Route: POST /charts/templates/delete
     */
    public function deleteTemplate(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        Csrf::verify($request);
        $userId     = (int)(Session::get('auth.user_id') ?? 0);
        $templateId = (int)$request->input('id', 0);

        try {
            $ok = $this->service->deleteTemplate($userId, $templateId);
            Response::json(['ok' => $ok]);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
