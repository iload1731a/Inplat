<?php

declare(strict_types=1);

namespace App\Controllers\User;

use App\Controllers\BaseController;
use App\Libraries\Csrf;
use App\Libraries\Request;
use App\Libraries\Response;
use App\Libraries\Session;
use App\Middleware\AuthMiddleware;
use App\Services\TradingEngineService;
use Throwable;

/**
 * User Trading Controller
 *
 * Serves the premium trading terminal and all real-time AJAX endpoints:
 *  - Trading terminal view (spot / margin / futures)
 *  - Order placement (spot, margin, futures)
 *  - Order cancellation
 *  - Order book, market depth, candlestick data
 *  - Live ticker feed
 *  - User's open orders and positions (AJAX polling)
 */
final class TradingController extends BaseController
{
    // =========================================================================
    // TRADING TERMINAL VIEW
    // =========================================================================

    /**
     * Main trading terminal.
     * Route: GET /trade[/{symbol}]
     */
    public function index(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $userId = (int)(Session::get('auth.user_id') ?? 0);

        $symbol  = trim((string)$request->input('symbol', ''));
        $service = new TradingEngineService();

        try {
            $pairs = $service->getAllActivePairs();

            // Default to first spot pair if no symbol supplied
            if ($symbol === '' && !empty($pairs)) {
                $symbol = (string)($pairs[0]['symbol'] ?? 'BTC/USDT');
            }

            $pair         = null;
            $ticker       = [];
            $orderBook    = ['bids' => [], 'asks' => []];
            $candles      = [];
            $recentTrades = [];
            $openOrders   = [];
            $positions    = [];

            if ($symbol !== '') {
                $pair         = $service->getAllActivePairs(); // re-use list for selected pair info
                $ticker       = $service->getTicker($symbol);
                $orderBook    = $service->getOrderBook($symbol, 15);
                $candles      = $service->getCandlesticks($symbol, '1h', 100);
                $recentTrades = $service->getRecentTrades($symbol, 30);
                $openOrders   = $service->getUserOpenOrders($userId, $symbol);
                $positions    = $service->getUserPositions($userId, 'open');
            }
        } catch (Throwable) {
            $pairs        = [];
            $ticker       = [];
            $orderBook    = ['bids' => [], 'asks' => []];
            $candles      = [];
            $recentTrades = [];
            $openOrders   = [];
            $positions    = [];
        }

        $this->view('user/trading/index', [
            'title'        => 'Trading Terminal — ' . ($symbol ?: 'Markets'),
            'userSection'  => 'trading',
            'symbol'       => $symbol,
            'pairs'        => $pairs,
            'ticker'       => $ticker,
            'orderBook'    => $orderBook,
            'candles'      => $candles,
            'recentTrades' => $recentTrades,
            'openOrders'   => $openOrders,
            'positions'    => $positions,
            'csrfToken'    => Csrf::token(),
        ]);
    }

    // =========================================================================
    // ORDER PLACEMENT (AJAX)
    // =========================================================================

    /**
     * Place a spot order.
     * Route: POST /trading/order/spot
     */
    public function placeSpotOrder(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 422);
        }

        $userId = (int)(Session::get('auth.user_id') ?? 0);
        $input  = $this->sanitizeOrderInput($request);

        try {
            $result = (new TradingEngineService())->placeSpotOrder($userId, $input);
            Response::json(['ok' => true, 'data' => $result]);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Place a futures order.
     * Route: POST /trading/order/futures
     */
    public function placeFuturesOrder(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 422);
        }

        $userId = (int)(Session::get('auth.user_id') ?? 0);
        $input  = $this->sanitizeOrderInput($request);

        try {
            $result = (new TradingEngineService())->placeFuturesOrder($userId, $input);
            Response::json(['ok' => true, 'data' => $result]);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Place a margin order.
     * Route: POST /trading/order/margin
     */
    public function placeMarginOrder(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 422);
        }

        $userId = (int)(Session::get('auth.user_id') ?? 0);
        $input  = $this->sanitizeOrderInput($request);

        try {
            $result = (new TradingEngineService())->placeMarginOrder($userId, $input);
            Response::json(['ok' => true, 'data' => $result]);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Cancel an open order.
     * Route: POST /trading/order/cancel
     */
    public function cancelOrder(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 422);
        }

        $userId  = (int)(Session::get('auth.user_id') ?? 0);
        $orderId = (int)$request->input('order_id');

        if ($orderId <= 0) {
            Response::json(['ok' => false, 'message' => 'Invalid order ID'], 422);
        }

        try {
            (new TradingEngineService())->cancelOrder($userId, $orderId);
            Response::json(['ok' => true, 'message' => 'Order cancelled successfully']);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Close an open futures/margin position.
     * Route: POST /trading/position/close
     */
    public function closePosition(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        if (!Csrf::validate((string)$request->input('_token'))) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 422);
        }

        $userId     = (int)(Session::get('auth.user_id') ?? 0);
        $positionId = (int)$request->input('position_id');
        $closePrice = trim((string)$request->input('close_price', ''));

        if ($positionId <= 0) {
            Response::json(['ok' => false, 'message' => 'Invalid position ID'], 422);
        }

        try {
            $result = (new TradingEngineService())->closePosition($userId, $positionId, $closePrice);
            Response::json(['ok' => true, 'data' => $result, 'message' => 'Position closed successfully']);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // =========================================================================
    // MARKET DATA AJAX ENDPOINTS
    // =========================================================================

    /**
     * Order book for a symbol.
     * Route: GET /trading/orderbook
     */
    public function orderBook(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $symbol = trim((string)$request->input('symbol', ''));
        $depth  = min(50, max(5, (int)$request->input('depth', 20)));

        if ($symbol === '') {
            Response::json(['ok' => false, 'message' => 'Symbol required'], 422);
        }

        try {
            $data = (new TradingEngineService())->getOrderBook($symbol, $depth);
            Response::json(['ok' => true, 'data' => $data]);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * OHLCV candlestick data.
     * Route: GET /trading/candles
     */
    public function candles(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $symbol   = trim((string)$request->input('symbol', ''));
        $interval = trim((string)$request->input('interval', '1h'));
        $limit    = min(500, max(10, (int)$request->input('limit', 200)));

        if ($symbol === '') {
            Response::json(['ok' => false, 'message' => 'Symbol required'], 422);
        }

        try {
            $data = (new TradingEngineService())->getCandlesticks($symbol, $interval, $limit);
            Response::json(['ok' => true, 'data' => $data]);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Single ticker snapshot.
     * Route: GET /trading/ticker
     */
    public function ticker(Request $request): void
    {
        $symbol = trim((string)$request->input('symbol', ''));

        if ($symbol === '') {
            Response::json(['ok' => false, 'message' => 'Symbol required'], 422);
        }

        try {
            $data = (new TradingEngineService())->getTicker($symbol);
            Response::json(['ok' => true, 'data' => $data]);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * All tickers for the markets panel.
     * Route: GET /trading/tickers
     */
    public function tickers(Request $request): void
    {
        try {
            $data = (new TradingEngineService())->getAllTickers();
            Response::json(['ok' => true, 'data' => $data]);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Recent public trades for a pair.
     * Route: GET /trading/recent-trades
     */
    public function recentTrades(Request $request): void
    {
        $symbol = trim((string)$request->input('symbol', ''));
        $limit  = min(100, max(10, (int)$request->input('limit', 30)));

        if ($symbol === '') {
            Response::json(['ok' => false, 'message' => 'Symbol required'], 422);
        }

        try {
            $data = (new TradingEngineService())->getRecentTrades($symbol, $limit);
            Response::json(['ok' => true, 'data' => $data]);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * User's open orders (AJAX polling).
     * Route: GET /trading/my-orders
     */
    public function myOrders(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $userId = (int)(Session::get('auth.user_id') ?? 0);
        $symbol = trim((string)$request->input('symbol', ''));

        try {
            $data = (new TradingEngineService())->getUserOpenOrders($userId, $symbol);
            Response::json(['ok' => true, 'data' => $data]);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * User's open positions (AJAX polling).
     * Route: GET /trading/my-positions
     */
    public function myPositions(Request $request): void
    {
        AuthMiddleware::ensureAuthenticated();
        $userId = (int)(Session::get('auth.user_id') ?? 0);
        $status = trim((string)$request->input('status', 'open'));

        if (!in_array($status, ['open', 'closed', 'liquidated'], true)) {
            $status = 'open';
        }

        try {
            $data = (new TradingEngineService())->getUserPositions($userId, $status);
            Response::json(['ok' => true, 'data' => $data]);
        } catch (Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    private function sanitizeOrderInput(Request $request): array
    {
        return [
            'symbol'          => trim((string)$request->input('symbol', '')),
            'order_type'      => trim((string)$request->input('order_type', 'limit')),
            'side'            => trim((string)$request->input('side', 'buy')),
            'quantity'        => trim((string)$request->input('quantity', '0')),
            'price'           => trim((string)$request->input('price', '')),
            'stop_price'      => trim((string)$request->input('stop_price', '')),
            'time_in_force'   => trim((string)$request->input('time_in_force', 'GTC')),
            'leverage'        => trim((string)$request->input('leverage', '1')),
            'is_reduce_only'  => (int)$request->input('is_reduce_only', 0),
            'client_order_id' => trim((string)$request->input('client_order_id', '')),
            'source'          => 'web',
        ];
    }
}
