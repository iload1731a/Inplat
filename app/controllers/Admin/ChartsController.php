<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Libraries\Request;
use App\Libraries\Response;
use App\Middleware\AuthMiddleware;
use App\Services\ChartService;
use Throwable;

/**
 * Admin Charts & Market Analytics Controller
 *
 * Routes:
 *   GET /admin/charts              — Market analytics dashboard
 *   GET /admin/charts/data         — AJAX: chart data for any pair/interval
 *   GET /admin/charts/volatility   — Volatility analysis table + heatmap
 *   GET /admin/charts/correlation  — Correlation matrix heatmap
 *   GET /admin/charts/heatmap      — Market heatmap (bubble chart)
 *   GET /admin/charts/export       — Export daily candle CSV for a pair
 */
final class ChartsController extends AdminBaseController
{
    private ChartService $service;

    public function __construct()
    {
        $this->service = new ChartService();
    }

    // =========================================================================
    // ANALYTICS DASHBOARD
    // =========================================================================

    /**
     * Market analytics dashboard.
     * Route: GET /admin/charts
     */
    public function index(Request $request): void
    {
        AuthMiddleware::ensureAdmin();

        try {
            $data = $this->service->getAdminAnalyticsDashboard();
        } catch (Throwable $e) {
            $data = [
                'kpis'         => [],
                'daily_volume' => [],
                'top_movers'   => [],
                'market_types' => [],
                'heatmap'      => [],
                'error'        => $e->getMessage(),
            ];
        }

        $this->render('admin/charts/index', array_merge($data, [
            'title'        => 'Market Analytics',
            'adminSection' => 'charts',
        ]));
    }

    // =========================================================================
    // AJAX — CHART DATA FOR ADMIN PANEL
    // =========================================================================

    /**
     * OHLCV + indicator data (reuses user chart service).
     * Route: GET /admin/charts/data
     */
    public function data(Request $request): void
    {
        AuthMiddleware::ensureAdmin();
        $symbol   = trim((string)$request->input('pair', ''));
        $interval = trim((string)$request->input('interval', '1h'));
        $limit    = max(50, min(1000, (int)$request->input('limit', 300)));
        $indStr   = trim((string)$request->input('indicators', ''));
        $inds     = $indStr !== '' ? array_filter(array_map('trim', explode(',', $indStr))) : [];

        if ($symbol === '') {
            Response::json(['ok' => false, 'message' => 'pair required'], 422);
            return;
        }

        try {
            $result = $this->service->getCandleDataWithIndicators($symbol, $interval, $limit, $inds);
            Response::json($result);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // VOLATILITY ANALYSIS
    // =========================================================================

    /**
     * Volatility analysis page.
     * Route: GET /admin/charts/volatility
     */
    public function volatility(Request $request): void
    {
        AuthMiddleware::ensureAdmin();
        $days = max(7, min(365, (int)$request->input('days', 30)));

        try {
            $data = $this->service->getVolatilityAnalysis($days);
        } catch (Throwable $e) {
            $data = ['scores' => [], 'days' => $days, 'error' => $e->getMessage()];
        }

        $this->render('admin/charts/volatility', array_merge($data, [
            'title'        => 'Volatility Analysis',
            'adminSection' => 'charts',
        ]));
    }

    // =========================================================================
    // CORRELATION MATRIX
    // =========================================================================

    /**
     * Correlation matrix heatmap page.
     * Route: GET /admin/charts/correlation
     */
    public function correlation(Request $request): void
    {
        AuthMiddleware::ensureAdmin();
        $days = max(7, min(365, (int)$request->input('days', 30)));

        try {
            $data = $this->service->getCorrelationMatrix($days);
        } catch (Throwable $e) {
            $data = ['symbols' => [], 'matrix' => [], 'days' => $days, 'error' => $e->getMessage()];
        }

        $this->render('admin/charts/correlation', array_merge($data, [
            'title'        => 'Correlation Matrix',
            'adminSection' => 'charts',
        ]));
    }

    // =========================================================================
    // MARKET HEATMAP
    // =========================================================================

    /**
     * Market heatmap bubble chart page.
     * Route: GET /admin/charts/heatmap
     */
    public function heatmap(Request $request): void
    {
        AuthMiddleware::ensureAdmin();
        $marketType = trim((string)$request->input('market_type', ''));

        try {
            $heatmap = (new \App\Repositories\ChartDataRepository())->getMarketHeatmapData($marketType);
        } catch (Throwable $e) {
            $heatmap = [];
        }

        $this->render('admin/charts/heatmap', [
            'title'        => 'Market Heatmap',
            'adminSection' => 'charts',
            'heatmap'      => $heatmap,
            'marketType'   => $marketType,
        ]);
    }

    // =========================================================================
    // CSV EXPORT
    // =========================================================================

    /**
     * Export daily OHLCV candles for a pair as CSV.
     * Route: GET /admin/charts/export
     */
    public function export(Request $request): void
    {
        AuthMiddleware::ensureAdmin();
        $symbol   = strtoupper(trim((string)$request->input('pair', '')));
        $interval = trim((string)$request->input('interval', '1d'));
        $limit    = max(30, min(1000, (int)$request->input('limit', 365)));

        if ($symbol === '') {
            http_response_code(400);
            echo 'pair parameter required';
            return;
        }

        try {
            $result = $this->service->getCandleDataWithIndicators($symbol, $interval, $limit);
            if (!($result['ok'] ?? false) || empty($result['candles'])) {
                http_response_code(404);
                echo 'No data found for ' . htmlspecialchars($symbol, ENT_QUOTES, 'UTF-8');
                return;
            }
            $filename = sprintf('%s_%s_%s.csv', $symbol, $interval, date('Ymd'));
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            $out = fopen('php://output', 'w');
            fputcsv($out, ['time', 'open', 'high', 'low', 'close', 'volume']);
            foreach ($result['candles'] as $c) {
                fputcsv($out, [
                    $c['time'], $c['open'], $c['high'], $c['low'], $c['close'], $c['volume'],
                ]);
            }
            fclose($out);
        } catch (Throwable $e) {
            http_response_code(500);
            echo 'Export error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        }
    }
}
