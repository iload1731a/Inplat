<?php

declare(strict_types=1);

namespace App\Controllers\User;

use App\Controllers\BaseController;
use App\Libraries\Csrf;
use App\Libraries\Request;
use App\Libraries\Response;
use App\Libraries\Session;
use App\Middleware\AuthMiddleware;
use App\Services\MarketsService;
use Throwable;

/**
 * User Markets Controller
 *
 * Routes:
 *   GET  /markets                    — full market overview with tickers
 *   GET  /markets/watchlist          — user watchlist page
 *   POST /markets/watchlist/toggle   — AJAX: add/remove pair from watchlist
 *   GET  /markets/detail             — individual market detail (?id=N)
 *   GET  /markets/tickers            — AJAX: live ticker JSON for all/filtered pairs
 *   GET  /markets/ticker             — AJAX: single pair ticker JSON
 */
final class MarketsController extends BaseController
{
    private function userId(): int
    {
        return (int)(Session::get('auth.user_id') ?? 0);
    }

    private function svc(): MarketsService
    {
        return new MarketsService();
    }

    // =========================================================================
    // MARKET OVERVIEW
    // =========================================================================

    public function index(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();

        $filters = [
            'search'      => trim((string)$request->input('search', '')),
            'market_type' => trim((string)$request->input('market_type', '')),
            'quote'       => trim((string)$request->input('quote', '')),
            'sort'        => trim((string)$request->input('sort', 'volume')),
        ];

        $data = $this->svc()->userMarketsIndex($this->userId(), $filters);

        $this->userView('user/markets/index', [
            'title'       => 'Markets',
            'userSection' => 'markets',
            'breadcrumb'  => [['label' => 'Markets']],
            ...$data,
        ]);
    }

    // =========================================================================
    // WATCHLIST
    // =========================================================================

    public function watchlist(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();

        $data = $this->svc()->userWatchlist($this->userId());

        $this->userView('user/markets/watchlist', [
            'title'       => 'My Watchlist',
            'userSection' => 'markets-watchlist',
            'breadcrumb'  => [
                ['label' => 'Markets', 'url' => '/markets'],
                ['label' => 'Watchlist'],
            ],
            ...$data,
        ]);
    }

    public function toggleWatchlist(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();

        $token = (string)($request->input('_token', '') ?? $request->header('X-CSRF-Token', ''));
        if (!Csrf::verify($token)) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token.'], 403);
            return;
        }

        $pairId = (int)$request->input('pair_id', 0);

        try {
            $result = $this->svc()->toggleWatchlist($this->userId(), $pairId);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
            return;
        }

        Response::json(['ok' => true, ...$result]);
    }

    // =========================================================================
    // MARKET DETAIL
    // =========================================================================

    public function detail(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();

        $pairId = (int)$request->input('id', 0);

        try {
            $data = $this->svc()->userMarketDetail($this->userId(), $pairId);
        } catch (Throwable $e) {
            $this->userView('user/markets/detail', [
                'title'        => 'Market Detail',
                'userSection'  => 'markets',
                'breadcrumb'   => [
                    ['label' => 'Markets', 'url' => '/markets'],
                    ['label' => 'Detail'],
                ],
                'error'        => $e->getMessage(),
                'pair'         => null,
                'inWatchlist'  => false,
                'recentTrades' => [],
                'candles1h'    => [],
                'candles1d'    => [],
            ]);
            return;
        }

        $this->userView('user/markets/detail', [
            'title'       => ($data['pair']['symbol'] ?? 'Market') . ' · Market Detail',
            'userSection' => 'markets',
            'breadcrumb'  => [
                ['label' => 'Markets', 'url' => '/markets'],
                ['label' => $data['pair']['symbol'] ?? 'Detail'],
            ],
            ...$data,
        ]);
    }

    // =========================================================================
    // AJAX — LIVE TICKERS
    // =========================================================================

    public function tickers(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $quote = trim((string)$request->input('quote', ''));
        try {
            $pairs = $this->svc()->getTickersJson($quote);
        } catch (Throwable) {
            $pairs = [];
        }
        Response::json(['ok' => true, 'data' => $pairs]);
    }

    public function ticker(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $pairId = (int)$request->input('id', 0);
        try {
            $pair = $this->svc()->getSingleTickerJson($pairId);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 404);
            return;
        }
        Response::json(['ok' => true, 'data' => $pair]);
    }
}

