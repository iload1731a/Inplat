<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\Database;
use App\Libraries\RequestContext;
use App\Repositories\AdminManagementRepository;
use App\Repositories\TradingEngineRepository;
use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * Trading Engine Service
 *
 * Orchestrates the complete order lifecycle:
 *  - Input validation (type, price, quantity, balance)
 *  - Spot / Futures / Margin order placement
 *  - Simulated matching engine (price-time priority FIFO)
 *  - Position PnL updates
 *  - Liquidation engine
 *  - Fee tier management
 *  - Market data aggregation
 */
final class TradingEngineService
{
    private readonly TradingEngineRepository  $repo;
    private readonly AdminManagementRepository $mgmtRepo;

    public function __construct()
    {
        $this->repo     = new TradingEngineRepository();
        $this->mgmtRepo = new AdminManagementRepository();
    }

    // =========================================================================
    // ORDER PLACEMENT
    // =========================================================================

    /**
     * Validate and place a spot order.
     *
     * @param int   $userId
     * @param array $input {
     *   symbol, order_type, side, quantity, price (optional for market),
     *   stop_price (optional), time_in_force, client_order_id (optional), source
     * }
     * @return array { order_id, message, match_results }
     */
    public function placeSpotOrder(int $userId, array $input): array
    {
        $pair = $this->validatePairAndTrading((string)($input['symbol'] ?? ''), 'spot');
        $this->validateOrderInput($input, $pair, false);

        $orderId = $this->repo->placeOrder([
            'user_id'         => $userId,
            'trading_pair_id' => (int)$pair['id'],
            'order_type'      => (string)($input['order_type'] ?? 'limit'),
            'side'            => (string)($input['side'] ?? 'buy'),
            'time_in_force'   => (string)($input['time_in_force'] ?? 'GTC'),
            'price'           => $input['price'] !== '' ? (string)$input['price'] : null,
            'stop_price'      => isset($input['stop_price']) && $input['stop_price'] !== '' ? (string)$input['stop_price'] : null,
            'quantity'        => (string)$input['quantity'],
            'leverage'        => '1.00',
            'is_reduce_only'  => 0,
            'client_order_id' => $input['client_order_id'] ?? null,
            'source'          => (string)($input['source'] ?? 'web'),
            'market_type'     => 'spot',
        ]);

        $matchResults = $this->runMatchingCycle($orderId, $pair);

        return [
            'order_id'      => $orderId,
            'message'       => 'Order placed successfully',
            'match_results' => $matchResults,
        ];
    }

    /**
     * Validate and place a futures order.
     */
    public function placeFuturesOrder(int $userId, array $input): array
    {
        $pair = $this->validatePairAndTrading((string)($input['symbol'] ?? ''), 'futures');
        $this->validateOrderInput($input, $pair, true);

        $leverage = (string)($input['leverage'] ?? '1.00');
        $maxLev   = (float)$pair['max_leverage'];
        if ((float)$leverage > $maxLev) {
            throw new InvalidArgumentException("Maximum allowed leverage for {$pair['symbol']} is {$maxLev}x.");
        }

        // Calculate margin requirement
        $orderType  = (string)($input['order_type'] ?? 'limit');
        $price      = in_array($orderType, ['market', 'stop_market'], true)
            ? (string)$pair['last_price']
            : (string)$input['price'];
        $quantity   = (string)$input['quantity'];
        $notional   = bcmul($price, $quantity, 18);
        $margin     = bcdiv($notional, $leverage, 18);

        // Liquidation price (simplified)
        $side           = (string)($input['side'] ?? 'buy');
        $positionSide   = $side === 'buy' ? 'long' : 'short';
        $maintenanceRate = '0.005'; // 0.5% maintenance margin
        $liquidationPrice = $this->calculateLiquidationPrice(
            $price, $leverage, $positionSide, $maintenanceRate
        );

        $orderId = $this->repo->placeOrder([
            'user_id'         => $userId,
            'trading_pair_id' => (int)$pair['id'],
            'order_type'      => $orderType,
            'side'            => $side,
            'time_in_force'   => (string)($input['time_in_force'] ?? 'GTC'),
            'price'           => $input['price'] !== '' ? (string)$input['price'] : null,
            'stop_price'      => isset($input['stop_price']) && $input['stop_price'] !== '' ? (string)$input['stop_price'] : null,
            'quantity'        => $quantity,
            'leverage'        => $leverage,
            'is_reduce_only'  => (int)($input['is_reduce_only'] ?? 0),
            'client_order_id' => $input['client_order_id'] ?? null,
            'source'          => (string)($input['source'] ?? 'web'),
            'market_type'     => 'futures',
        ]);

        // If order fills immediately, open a position
        $matchResults = $this->runMatchingCycle($orderId, $pair);

        return [
            'order_id'          => $orderId,
            'message'           => 'Futures order placed successfully',
            'leverage'          => $leverage,
            'margin_required'   => $margin,
            'liquidation_price' => $liquidationPrice,
            'match_results'     => $matchResults,
        ];
    }

    /**
     * Validate and place a margin order (borrows funds if needed).
     */
    public function placeMarginOrder(int $userId, array $input): array
    {
        $pair = $this->validatePairAndTrading((string)($input['symbol'] ?? ''), 'margin');
        $this->validateOrderInput($input, $pair, false);

        // Ensure margin account exists
        $this->repo->getOrCreateMarginAccount($userId);

        $orderId = $this->repo->placeOrder([
            'user_id'         => $userId,
            'trading_pair_id' => (int)$pair['id'],
            'order_type'      => (string)($input['order_type'] ?? 'limit'),
            'side'            => (string)($input['side'] ?? 'buy'),
            'time_in_force'   => (string)($input['time_in_force'] ?? 'GTC'),
            'price'           => $input['price'] !== '' ? (string)$input['price'] : null,
            'stop_price'      => isset($input['stop_price']) && $input['stop_price'] !== '' ? (string)$input['stop_price'] : null,
            'quantity'        => (string)$input['quantity'],
            'leverage'        => (string)($input['leverage'] ?? '3.00'),
            'is_reduce_only'  => 0,
            'client_order_id' => $input['client_order_id'] ?? null,
            'source'          => (string)($input['source'] ?? 'web'),
            'market_type'     => 'margin',
        ]);

        $matchResults = $this->runMatchingCycle($orderId, $pair);

        return [
            'order_id'      => $orderId,
            'message'       => 'Margin order placed successfully',
            'match_results' => $matchResults,
        ];
    }

    /**
     * Cancel an open order.
     */
    public function cancelOrder(int $userId, int $orderId): void
    {
        $this->repo->cancelOrder($orderId, $userId, 'Cancelled by user');
        $this->notifyUser($userId, 'order_cancelled', 'Order Cancelled', "Order #{$orderId} has been cancelled.");
    }

    // =========================================================================
    // MATCHING ENGINE
    // =========================================================================

    /**
     * Run one matching cycle for a newly placed order.
     * Finds matching counter-orders and executes trades until the order is filled
     * or no more matches are available.
     */
    public function runMatchingCycle(int $orderId, array $pair): array
    {
        $executed = [];
        $safetyLimit = 50; // max iterations per cycle to prevent infinite loops
        $iterations  = 0;

        while ($iterations++ < $safetyLimit) {
            $counterOrders = $this->repo->findMatchingOrders($orderId);
            if (empty($counterOrders)) {
                break;
            }

            $counter = $counterOrders[0]; // best price, earliest time

            // Determine buy/sell order IDs
            $order = $this->repo->findOrderForUser($orderId, 0); // 0 = skip user check for engine
            if ($order === null) {
                break;
            }

            // Re-fetch with no user constraint
            $order = $this->fetchOrderById($orderId);
            if ($order === null || !in_array((string)$order['status'], ['open', 'partially_filled'], true)) {
                break;
            }

            if ((string)$order['side'] === 'buy') {
                $buyOrderId  = $orderId;
                $sellOrderId = (int)$counter['id'];
            } else {
                $buyOrderId  = (int)$counter['id'];
                $sellOrderId = $orderId;
            }

            // Trade price = resting order's price (maker price) or market price
            $tradePrice = $counter['price'] ?? $pair['last_price'];
            if (bccomp((string)$tradePrice, '0', 18) <= 0) {
                break;
            }

            // Trade quantity = min of remaining quantities
            $buyOrder   = $this->fetchOrderById($buyOrderId);
            $sellOrder  = $this->fetchOrderById($sellOrderId);
            if ($buyOrder === null || $sellOrder === null) {
                break;
            }

            $tradeQty = bccomp(
                (string)$buyOrder['remaining_quantity'],
                (string)$sellOrder['remaining_quantity'],
                18
            ) <= 0
                ? (string)$buyOrder['remaining_quantity']
                : (string)$sellOrder['remaining_quantity'];

            if (bccomp($tradeQty, '0', 18) <= 0) {
                break;
            }

            // Check min notional
            $notional = bcmul((string)$tradePrice, $tradeQty, 18);
            $minNotional = (string)($pair['min_notional'] ?? '0');
            if (bccomp($notional, $minNotional, 18) < 0) {
                break;
            }

            $tradeId = $this->repo->executeTrade(
                $buyOrderId,
                $sellOrderId,
                (string)$tradePrice,
                $tradeQty,
                $pair
            );

            if ($tradeId > 0) {
                $executed[] = [
                    'trade_id'  => $tradeId,
                    'price'     => $tradePrice,
                    'quantity'  => $tradeQty,
                    'buy_order' => $buyOrderId,
                    'sell_order'=> $sellOrderId,
                ];

                // Notify both parties
                $this->notifyUser((int)$buyOrder['user_id'], 'order_filled',
                    'Order Filled', "Buy order #{$buyOrderId} executed {$tradeQty} @ {$tradePrice}");
                $this->notifyUser((int)$sellOrder['user_id'], 'order_filled',
                    'Order Filled', "Sell order #{$sellOrderId} executed {$tradeQty} @ {$tradePrice}");
            } else {
                break;
            }

            // Check if our order is fully filled
            $freshOrder = $this->fetchOrderById($orderId);
            if ($freshOrder === null || (string)$freshOrder['status'] === 'filled') {
                break;
            }
        }

        return $executed;
    }

    // =========================================================================
    // POSITION MANAGEMENT
    // =========================================================================

    public function closePosition(int $userId, int $positionId, string $closePrice = ''): array
    {
        if ($closePrice === '') {
            // Use current market price from ticker
            $pos = $this->fetchPositionForUser($positionId, $userId);
            if ($pos === null) {
                throw new InvalidArgumentException('Position not found.');
            }
            $closePrice = (string)($pos['current_price'] ?? $pos['entry_price']);
        }

        if (bccomp($closePrice, '0', 18) <= 0) {
            throw new InvalidArgumentException('Invalid close price.');
        }

        $result = $this->repo->closePosition($positionId, $userId, $closePrice);

        $pnl = $result['realized_pnl'];
        $msg = bccomp($pnl, '0', 18) >= 0
            ? "Position closed with profit: {$pnl}"
            : "Position closed with loss: {$pnl}";
        $this->notifyUser($userId, 'position_closed', 'Position Closed', $msg);

        return $result;
    }

    /**
     * Find and liquidate positions below the maintenance margin threshold.
     * Called by cron or admin action.
     */
    public function processLiquidations(): array
    {
        $candidates = $this->repo->getPositionsNearLiquidation();
        $liquidated = [];

        foreach ($candidates as $pos) {
            $positionId       = (int)$pos['id'];
            $liquidationPrice = (string)$pos['liquidation_price'];

            try {
                $this->repo->liquidatePosition($positionId, $liquidationPrice);
                $liquidated[] = ['position_id' => $positionId, 'liquidation_price' => $liquidationPrice];

                $this->notifyUser((int)$pos['user_id'], 'position_liquidated',
                    'Position Liquidated',
                    "Your {$pos['symbol']} {$pos['position_side']} position has been liquidated at {$liquidationPrice}."
                );
            } catch (\Throwable) {
                // Continue processing other positions
            }
        }

        return $liquidated;
    }

    /**
     * Admin force-liquidate a specific position.
     */
    public function forceLiquidate(int $adminId, int $positionId, string $price): void
    {
        if (bccomp($price, '0', 18) <= 0) {
            throw new InvalidArgumentException('Invalid liquidation price.');
        }

        $this->repo->liquidatePosition($positionId, $price);
        $this->mgmtRepo->logAdminAction(
            $adminId, 'force_liquidate', 'positions', (string)$positionId,
            null, ['price' => $price], RequestContext::ipAddress()
        );
    }

    // =========================================================================
    // MARKET DATA
    // =========================================================================

    public function getOrderBook(string $symbol, int $depth = 20): array
    {
        $pair = $this->repo->getTradingPairBySymbol($symbol);
        if ($pair === null) {
            return ['bids' => [], 'asks' => [], 'symbol' => $symbol];
        }

        $book = $this->repo->getOrderBook((int)$pair['id'], $depth);

        return [
            'symbol'     => $symbol,
            'bids'       => $book['bids'],
            'asks'       => $book['asks'],
            'last_price' => $pair['last_price'],
            'timestamp'  => date('c'),
        ];
    }

    public function getCandlesticks(string $symbol, string $interval = '1h', int $limit = 200): array
    {
        $pair = $this->repo->getTradingPairBySymbol($symbol);
        if ($pair === null) {
            return [];
        }

        $candles = $this->repo->getCandlesticks((int)$pair['id'], $interval, $limit);

        return array_map(static function (array $c): array {
            return [
                't' => strtotime((string)$c['open_time']) * 1000,
                'o' => (float)$c['open_price'],
                'h' => (float)$c['high_price'],
                'l' => (float)$c['low_price'],
                'c' => (float)$c['close_price'],
                'v' => (float)$c['volume'],
            ];
        }, $candles);
    }

    public function getTicker(string $symbol): array
    {
        $pair = $this->repo->getTradingPairBySymbol($symbol);
        if ($pair === null) {
            return ['error' => 'Pair not found'];
        }

        return [
            'symbol'             => $pair['symbol'],
            'last_price'         => $pair['last_price'],
            'best_bid'           => $pair['best_bid'],
            'best_ask'           => $pair['best_ask'],
            'change_24h_percent' => $pair['change_24h_percent'],
            'high_24h'           => $pair['high_24h'],
            'low_24h'            => $pair['low_24h'],
            'volume_24h'         => $pair['volume_24h'],
        ];
    }

    public function getAllTickers(): array
    {
        $pairs = $this->repo->getAllActivePairs();
        return array_map(static function (array $p): array {
            return [
                'symbol'             => $p['symbol'],
                'market_type'        => $p['market_type'],
                'last_price'         => $p['last_price'],
                'best_bid'           => $p['best_bid'],
                'best_ask'           => $p['best_ask'],
                'change_24h_percent' => $p['change_24h_percent'],
                'high_24h'           => $p['high_24h'],
                'low_24h'            => $p['low_24h'],
                'volume_24h'         => $p['volume_24h'],
            ];
        }, $pairs);
    }

    public function getRecentTrades(string $symbol, int $limit = 50): array
    {
        $pair = $this->repo->getTradingPairBySymbol($symbol);
        if ($pair === null) {
            return [];
        }
        return $this->repo->getRecentTrades((int)$pair['id'], $limit);
    }

    // =========================================================================
    // USER DATA
    // =========================================================================

    public function getUserOpenOrders(int $userId, string $symbol = ''): array
    {
        $pairId = null;
        if ($symbol !== '') {
            $pair = $this->repo->getTradingPairBySymbol($symbol);
            $pairId = $pair !== null ? (int)$pair['id'] : null;
        }
        return $this->repo->getUserOpenOrders($userId, $pairId);
    }

    public function getUserPositions(int $userId, string $status = 'open'): array
    {
        return $this->repo->getUserPositions($userId, $status);
    }

    // =========================================================================
    // FEE MANAGEMENT
    // =========================================================================

    public function getFeeTiers(): array
    {
        return $this->repo->getFeeTiers();
    }

    public function createFeeTier(int $adminId, array $data): int
    {
        $this->validateFeeTierData($data);
        $id = $this->repo->createFeeTier($data);
        $this->mgmtRepo->logAdminAction(
            $adminId, 'create_fee_tier', 'fee_tiers', (string)$id,
            null, $data, RequestContext::ipAddress()
        );
        return $id;
    }

    public function updateFeeTier(int $adminId, int $tierId, array $data): void
    {
        $this->validateFeeTierData($data);
        $this->repo->updateFeeTier($tierId, $data);
        $this->mgmtRepo->logAdminAction(
            $adminId, 'update_fee_tier', 'fee_tiers', (string)$tierId,
            null, $data, RequestContext::ipAddress()
        );
    }

    // =========================================================================
    // ADMIN ENGINE STATS
    // =========================================================================

    public function getEngineStats(): array
    {
        return $this->repo->getEngineStats();
    }

    public function getDailyVolumeChart(int $days = 30): array
    {
        return $this->repo->getDailyVolumeChart($days);
    }

    public function getTopPairs(): array
    {
        return $this->repo->getTopTradedPairs(10);
    }

    public function getLiquidationCandidates(): array
    {
        return $this->repo->getPositionsNearLiquidation();
    }

    public function getAllOpenPositions(array $filters = []): array
    {
        return $this->repo->getAllOpenPositions($filters);
    }

    public function getAllActivePairs(): array
    {
        return $this->repo->getAllActivePairs();
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    private function validatePairAndTrading(string $symbol, string $requiredMarketType): array
    {
        if ($symbol === '') {
            throw new InvalidArgumentException('Trading pair symbol is required.');
        }

        $pair = $this->repo->getTradingPairBySymbol($symbol);
        if ($pair === null) {
            throw new InvalidArgumentException("Trading pair '{$symbol}' not found or inactive.");
        }

        if (!(bool)$pair['trading_enabled']) {
            throw new RuntimeException("Trading is currently halted for {$symbol}.");
        }

        if ((string)$pair['market_type'] !== $requiredMarketType) {
            throw new InvalidArgumentException("Pair '{$symbol}' is not a {$requiredMarketType} pair.");
        }

        return $pair;
    }

    private function validateOrderInput(array $input, array $pair, bool $requiresLeverage): void
    {
        $orderType = (string)($input['order_type'] ?? '');
        $validTypes = ['market', 'limit', 'stop_limit', 'stop_market', 'trailing_stop'];
        if (!in_array($orderType, $validTypes, true)) {
            throw new InvalidArgumentException("Invalid order type: {$orderType}.");
        }

        $side = (string)($input['side'] ?? '');
        if (!in_array($side, ['buy', 'sell'], true)) {
            throw new InvalidArgumentException('Order side must be buy or sell.');
        }

        $tif = (string)($input['time_in_force'] ?? 'GTC');
        if (!in_array($tif, ['GTC', 'IOC', 'FOK', 'GTD'], true)) {
            throw new InvalidArgumentException("Invalid time_in_force: {$tif}.");
        }

        $quantity = (string)($input['quantity'] ?? '0');
        if (bccomp($quantity, '0', 18) <= 0) {
            throw new InvalidArgumentException('Quantity must be greater than zero.');
        }

        $minQty = (string)($pair['min_order_size'] ?? '0');
        if (bccomp($quantity, $minQty, 18) < 0) {
            throw new InvalidArgumentException("Minimum order size is {$minQty}.");
        }

        $maxQty = (string)($pair['max_order_size'] ?? '0');
        if ($maxQty !== '0' && bccomp($quantity, $maxQty, 18) > 0) {
            throw new InvalidArgumentException("Maximum order size is {$maxQty}.");
        }

        if (in_array($orderType, ['limit', 'stop_limit'], true)) {
            $price = (string)($input['price'] ?? '0');
            if (bccomp($price, '0', 18) <= 0) {
                throw new InvalidArgumentException('Price must be greater than zero for limit orders.');
            }

            $notional = bcmul($price, $quantity, 18);
            $minNotional = (string)($pair['min_notional'] ?? '0');
            if (bccomp($notional, $minNotional, 18) < 0) {
                throw new InvalidArgumentException("Order notional value is below minimum: {$minNotional}.");
            }
        }

        if (in_array($orderType, ['stop_limit', 'stop_market', 'trailing_stop'], true)) {
            $stopPrice = (string)($input['stop_price'] ?? '0');
            if (bccomp($stopPrice, '0', 18) <= 0) {
                throw new InvalidArgumentException('Stop price is required for stop orders.');
            }
        }

        if ($requiresLeverage) {
            $leverage = (string)($input['leverage'] ?? '1');
            if ((float)$leverage < 1.0) {
                throw new InvalidArgumentException('Leverage must be at least 1x.');
            }
        }
    }

    private function validateFeeTierData(array $data): void
    {
        if (empty($data['tier_name'])) {
            throw new InvalidArgumentException('Tier name is required.');
        }

        foreach (['maker_fee_percent', 'taker_fee_percent'] as $field) {
            $val = (float)($data[$field] ?? -1);
            if ($val < 0 || $val > 100) {
                throw new InvalidArgumentException("{$field} must be between 0 and 100.");
            }
        }

        $vol = (float)($data['min_30d_volume'] ?? -1);
        if ($vol < 0) {
            throw new InvalidArgumentException('Minimum 30-day volume must be >= 0.');
        }
    }

    private function calculateLiquidationPrice(
        string $entryPrice,
        string $leverage,
        string $side,
        string $maintenanceRate
    ): string {
        // Long: liq = entry * (1 - 1/leverage + maintenance_rate)
        // Short: liq = entry * (1 + 1/leverage - maintenance_rate)
        $invLev = bcdiv('1', $leverage, 18);
        if ($side === 'long') {
            $factor = bcsub(bcsub('1', $invLev, 18), $maintenanceRate, 18);
            return bcmul($entryPrice, $factor, 18);
        }
        $factor = bcsub(bcadd('1', $invLev, 18), $maintenanceRate, 18);
        return bcmul($entryPrice, $factor, 18);
    }

    /** Fetch any order by ID without user restriction (for matching engine). */
    private function fetchOrderById(int $orderId): ?array
    {
        $stmt = \App\Libraries\Database::connection()->prepare(
            'SELECT o.*, ot.name AS order_type_name, tp.market_type
             FROM orders o
             INNER JOIN order_types ot   ON ot.id = o.order_type_id
             INNER JOIN trading_pairs tp ON tp.id = o.trading_pair_id
             WHERE o.id = :id LIMIT 1'
        );
        $stmt->bindValue(':id', $orderId, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** Fetch position with current market price for a user. */
    private function fetchPositionForUser(int $positionId, int $userId): ?array
    {
        $positions = $this->repo->getUserPositions($userId);
        foreach ($positions as $pos) {
            if ((int)$pos['id'] === $positionId) {
                return $pos;
            }
        }
        return null;
    }

    private function notifyUser(int $userId, string $type, string $title, string $message): void
    {
        try {
            $stmt = Database::connection()->prepare(
                'INSERT INTO notifications (user_id, type, title, message, channel, is_read, created_at)
                 VALUES (:uid, :type, :title, :msg, \'in_app\', 0, NOW())'
            );
            $stmt->bindValue(':uid',   $userId, PDO::PARAM_INT);
            $stmt->bindValue(':type',  $type);
            $stmt->bindValue(':title', $title);
            $stmt->bindValue(':msg',   $message);
            $stmt->execute();
        } catch (\Throwable) {
            // Notification failure must not abort the trading operation
        }
    }
}
